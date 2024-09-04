<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Memo;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\ApprovalProcess;
use App\Models\User;
use App\Notifications\ApprovalProcessNotification;


class MemoController extends Controller
{
    public function createMemo(Request $request)
    {
        try {
            // Validate request data
            $memovalidate = $request->validate([
                'user_id' => 'required|exists:users,id',
                'to' => 'required|exists:users,id',
                're' => 'required|string',
                'memo_body' => 'required',
                'by' => 'required|array|exists:users,id',
                'approved_by' => 'required|array|exists:users,id',
            ]);
    
            $userID = $memovalidate['user_id'];
            $toUserId = $memovalidate['to'];
            $byIds = $memovalidate['by'];
            $approvedByIds = $memovalidate['approved_by'];
    
            // Create a new memo
            $memo = Memo::create([
                'user_id' => $userID,
                'to' => $toUserId,
                'from' => 'HR Department',
                're' => $request->re,
                'memo_body' => $request->memo_body,
                'by' => json_encode($byIds),
                'approved_by' => json_encode($approvedByIds),
            ]);
    
            // Initialize level
            $level = 1;
            $firstApprover = null;
    
            // Create approval processes for 'by' users
            foreach ($byIds as $byId) {
                ApprovalProcess::create([
                    'user_id' => $byId,
                    'memo_id' => $memo->id,
                    'level' => $level,
                    'status' => 'Pending',
                ]);
    
                if ($level === 1) {
                    $firstApprover = $byId; // Store the first approver ID
                }
    
                $level++;
            }
    
            // Create approval processes for 'approved_by' users
            foreach ($approvedByIds as $approvedById) {
                ApprovalProcess::create([
                    'user_id' => $approvedById,
                    'memo_id' => $memo->id,
                    'level' => $level,
                    'status' => 'Pending',
                ]);
                $level++;
            }
    
            // Create approval process for 'to' user
            ApprovalProcess::create([
                'user_id' => $toUserId,
                'memo_id' => $memo->id,
                'level' => $level,
                'status' => 'Pending',
            ]);
    
            // Retrieve requester's first name and last name
            $to = User::find($toUserId);
            $toFirstName = $to->firstName;
            $toLastName = $to->lastName;
    
            // Notify the first approver
            if ($firstApprover) {
                $firstApproverUser = User::find($firstApprover);
    
                if ($firstApproverUser) {
                    $firstApprovalProcess = ApprovalProcess::where('memo_id', $memo->id)
                        ->where('user_id', $firstApprover)
                        ->where('level', 1)
                        ->first();
    
                    $firstname = $firstApproverUser->firstName;
                    $firstApproverUser->notify(new ApprovalProcessNotification($firstApprovalProcess, $firstname, $memo, $toFirstName, $toLastName));
                }
            }
    
            DB::commit();
    
            return response()->json([
                'status' => true,
                'message' => 'Memo created successfully',
                'data' => $memo
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => $e->getMessage()
            ]);
        }
    }
    

    public function viewMemo()
    {
        try {


            //$currentUserId = auth()->user()->id;

            // Fetch request forms where user_id matches the current user's ID
            $memos = Memo:://where('user_id', $currentUserId)
                 select('id', 'user_id', 'to','re', 'memo_body', 'status', 'by', 'approved_by','created_at','updated_at')
                ->with('approvalProcess')
                ->get();
    

            // Initialize an array to hold the response data
            $response = $memos->map(function ($memo) {
                // Decode the approvers_id fields, defaulting to empty arrays if null
                $ByIds = is_string($memo->by) ? json_decode($memo->by, true) : [];
                $approvedByIds = is_string($memo->approved_by) ? json_decode($memo->approved_by, true) : [];
                $toID = $memo->to;
                $toID = [$toID]; 
                // Ensure both are arrays
                $ByIds = is_array($ByIds) ? $ByIds : [];
                $approvedByIds = is_array($approvedByIds) ? $approvedByIds : [];

                $allApproversIds = array_merge($ByIds, $approvedByIds, $toID );


                // Fetch all approvers in one query
                $allApprovers = User::whereIn('id', $allApproversIds)
                    ->select('id', 'firstName', 'lastName', 'position', 'signature', 'branch')
                    ->get()
                    ->keyBy('id');


                $to = User::with('branch')
                    ->where('id', $toID)
                    ->select('id', 'firstName', 'lastName', 'position','signature', 'branch_code') // Ensure 'branch_code' is selected
                    ->first();

                $branchName = $to->branch ? $to->branch->branch_code : 'No branch assigned';
        
                // Fetch all approval statuses and comments in one query
                $approvalData = ApprovalProcess::whereIn('user_id', $allApproversIds)
                    ->where('memo_id', $memo->id)
                    ->get()
                    ->keyBy('user_id');

                // Format noted_by users
                $formattedNotedBy = $ByIds
                    ? collect($ByIds)->map(function ($userId) use ($allApprovers, $approvalData) {
                        if (isset($allApprovers[$userId])) {
                            $user = $allApprovers[$userId];
                            $approval = $approvalData[$userId] ?? null;

                            return [
                                'firstname' => $user->firstName,
                                'lastname' => $user->lastName,
                                'status' => $approval->status ?? '',
                                'position' => $user->position,
                                'signature' => $user->signature,
                            ];
                        }
                    })->filter()->values()->all()
                    : [];

                // Format approved_by users
                $formattedApprovedBy = $approvedByIds
                    ? collect($approvedByIds)->map(function ($userId) use ($allApprovers, $approvalData) {
                        if (isset($allApprovers[$userId])) {
                            $user = $allApprovers[$userId];
                            $approval = $approvalData[$userId] ?? null;

                            return [
                                'firstname' => $user->firstName,
                                'lastname' => $user->lastName,
                                'status' => $approval->status ?? '',
                                'position' => $user->position,
                                'signature' => $user->signature,
                            ];
                        }
                    })->filter()->values()->all()
                    : [];

                    $formattedReceivedBy = $toID
                    ? collect($toID)->map(function ($userId) use ($allApprovers, $approvalData) {
                        if (isset($allApprovers[$userId])) {
                            $user = $allApprovers[$userId];
                            $approval = $approvalData[$userId] ?? null;

                            return [
                                'firstname' => $user->firstName,
                                'lastname' => $user->lastName,
                                'status' => $approval->status ?? '',
                                'signature' => $user->signature,
                                'updated_at' => $approval->updated_at
                            ];
                        }
                    })->filter()->values()->all()
                    : [];

                // Get the pending approver
                $pendingApprover = $memo->approvalProcess()
                    ->where('status', 'Pending')
                    ->orderBy('level')
                    ->first()?->user;
                    

                return [
                    'id' => $memo->id,
                    'date' =>$memo->created_at,
                    'user_id' => $memo->user_id,
                    'to' => "$to->firstName $to->lastName - $to->position - $branchName ",
                    're' => $memo->re,
                    'memo_body' => $memo->memo_body,
                    'status' => $memo->status,
                    'by' =>  $formattedNotedBy,
                    'approved_by' => $formattedApprovedBy,
                    'received_by' => $formattedReceivedBy,
                    'pending_approver' => $pendingApprover ? [
                        'approver_name' => "{$pendingApprover->firstName} {$pendingApprover->lastName}",
                    ] : "No Pending Approver",
                ];
            });

            return response()->json([
                'message' => 'Memo retrieved successfully',
                'data' => $response
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'An error occurred while retrieving memo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    



}
