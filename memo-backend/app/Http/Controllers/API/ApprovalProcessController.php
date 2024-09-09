<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\Memo;
use App\Models\User;
use App\Models\ApprovalProcess;
use App\Notifications\ApprovalProcessNotification;
use App\Notifications\EmployeeNotification;
use App\Notifications\ReturnRequestNotification;
use App\Notifications\PreviousReturnRequestNotification;
use App\Models\Explain;

class ApprovalProcessController extends Controller
{
    public function processMemo(Request $request, $memo_id)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'action' => 'required|in:approve,disapprove,receive',
            'comment' => 'nullable|string', // Optional field, may be missing
        ]);
    
        $user_id = $validated['user_id'];
        $action = $validated['action'];
        $comment = $validated['comment'] ?? ''; // Use an empty string if 'comment' is not provided
    
        DB::beginTransaction();
    
        try {
            $memo = Memo::findOrFail($memo_id);
    
            $approvalProcess = ApprovalProcess::where('memo_id', $memo_id)
                ->where('user_id', $user_id)
                ->where('status', 'Pending')
                ->first();
    
            if (!$approvalProcess) {
                return response()->json([
                    'message' => 'You are not authorized to process this memo or it has already been processed.',
                ], 403);
            }
    
            $currentApprovalLevel = ApprovalProcess::where('memo_id', $memo_id)
                ->where('status', 'Pending')
                ->orderBy('level')
                ->first();
    
           // Ensure the user is the current approver
        if ($currentApprovalLevel && $currentApprovalLevel->user_id !== $user_id) {
            return response()->json([
                'message' => 'It is not your turn to process this memo.',
            ], 403);
        }
    
            // Update the approval process
            $approvalProcess->update([
                'status' => match($action) {
                    'approve' => 'Approved',
                    'disapprove' => 'Disapproved',
                    'receive' => 'Received',
                },
                'comment' => $comment,
            ]);
    
            if ($action === 'approve') {
                $firstApprovalProcess = ApprovalProcess::where('memo_id', $memo_id)
                    ->orderBy('level')
                    ->first();
    
                if ($firstApprovalProcess && $firstApprovalProcess->user_id == $user_id) {
                    $memo->status = 'Ongoing';
                    $memo->save();
                }
    
                $nextApprovalProcess = ApprovalProcess::where('memo_id', $memo_id)
                    ->where('status', 'Pending')
                    ->orderBy('level')
                    ->first();
    
                if ($nextApprovalProcess) {
                    $nextApprover = $nextApprovalProcess->user;
                    $firstname = $nextApprover->firstName;
                    $createdMemo = $memo->user;
                    $requesterFirstname = $createdMemo->firstName;
                    $requesterLastname = $createdMemo->lastName;
                    $nextApprover->notify(new ApprovalProcessNotification($nextApprovalProcess, $firstname,$memo,$requesterFirstname,$requesterLastname));
                } else {
                    $memo->status = 'Approved';
                    $memo->save();
                    $createdMemo = $memo->user;
                    $firstname = $createdMemo->firstName;
                    // Notify employee
                    $createdMemo->notify(new EmployeeNotification($memo, 'approved', $firstname, $memo->re));
                }
            } elseif ($action === 'receive') {
                $memo->status = 'Received';
                $memo->save();
                $createdMemo = $memo->user;
                $firstname = $createdMemo->firstName;
                // Notify employee
               $createdMemo->notify(new EmployeeNotification($memo, 'received', $firstname, $memo->form_type));
            } else { // disapprove
                $memo->status = 'Disapproved';
                $memo->save();
                $createdMemo= $memo->user;
                $firstname = $createdMemo->firstName;
                $approverFirstname = $approvalProcess->user->firstName;
                $approverLastname = $approvalProcess->user->lastName;
                // Notify employee
                $createdMemo->notify(new ReturnRequestNotification($memo, 'disapproved', $firstname, $approverFirstname, $approverLastname, $comment));
    
                // Notify previous approvers
                $previousApprovalProcesses = ApprovalProcess::where('memo_id', $memo_id)
                    ->where('status', 'Approved')
                    ->orderBy('level', 'asc')
                    ->get();
    
                foreach ($previousApprovalProcesses as $previousApprovalProcess) {
                    $previousApprover = $previousApprovalProcess->user;
                    $prevFirstName = $previousApprover->firstName;
    
                    $previousApprovalProcess->update([
                        'status' => "Rejected by $approverFirstname $approverLastname",
                    ]);
    
                    $requesterFirstname = $createdMemo->firstName;
                    $requesterLastname = $createdMemo->lastName;
                    // Notify previous approver
                    $previousApprover->notify(new PreviousReturnRequestNotification($memo, 'disapproved', $prevFirstName, $approverFirstname, $approverLastname, $comment, $requesterFirstname, $requesterLastname));
                }
            }
    
            DB::commit();
    
            return response()->json([
                'message' => 'Memo processed successfully',
            ], 200);
    
        } catch (\Exception $e) {
            DB::rollBack();
    
            return response()->json([
                'message' => 'An error occurred',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    

    
   public function getMemoForApproval($user_id)
    {
        try {
            // Retrieve all approval processes where the current user is involved
            $approvalProcesses = ApprovalProcess::where('user_id', $user_id)
                ->orderBy('level')
                ->with(['memo.user', 'user']) // Eager load request form with user
                ->get();
    
            // Process each approval process
            $transformedApprovalProcesses = $approvalProcesses->map(function ($approvalProcess) use ($user_id) {
                $memo = $approvalProcess->memo;
                $requester = $memo->user; // Eager loaded user
    
                // Check if any previous level is disapproved
                $previousLevelsDisapproved = $memo->approvalProcess
                    ->where('level', '<', $approvalProcess->level)
                    ->contains('status', 'Disapproved');
    
                // Check if all previous levels are approved
                $previousLevelsApproved = $memo->approvalProcess
                    ->where('level', '<', $approvalProcess->level)
                    ->every(function ($process) {
                        return $process->status == 'Approved';
                    });

              
    
                // Determine if it's the user's turn to approve
                $isUserTurn = $previousLevelsApproved && $approvalProcess->status == 'Pending' && $approvalProcess->user_id == $user_id;
    
    
                // Determine if the current user is the last approver
                $isLastApprover = $memo->approvalProcess()
                    ->where('status', 'Pending')
                    ->where('level', '>', $approvalProcess->level)
                    ->count() === 0;
    
                // Determine if the request form has been disapproved
                $isDisapproved = $memo->approvalProcess()
                    ->where('status', 'Disapproved')
                    ->count() > 0;
    
                // Determine the next approver
                $pendingApproverr = $memo->approvalProcess()
                    ->where('status', 'Pending')
                    ->orderBy('level')
                    ->first()?->user; // Get the next approver

                 // Determine if it's the user's turn to approve
                 $isUserTurn = $previousLevelsApproved && $approvalProcess->status == 'Pending' && $approvalProcess->user_id == $user_id;

                 // Include request forms where the previous level has statuses of Approved, Disapproved, or Rejected by...
                 $isRelevantStatus = in_array($approvalProcess->status, ['Approved', 'Disapproved','Received']) ||
                     preg_match('/^Rejected by/', $approvalProcess->status);
 
                 if (!$isRelevantStatus && !$isUserTurn) {
                     return null; // Skip if the status is not relevant and it's not the user's turn to approve
                 }
    
       // Fetch approvers details
       $ByIds = is_string($memo->by) ? json_decode($memo->by, true) : [];
       $approvedByIds = is_string($memo->approved_by) ? json_decode($memo->approved_by, true) : [];
       $toID = $memo->to;
       $toID = [$toID]; 
       // Ensure both are arrays
       $ByIds = is_array($ByIds) ? $ByIds : [];
       $approvedByIds = is_array($approvedByIds) ? $approvedByIds : [];

       $allApproversIds = array_merge($ByIds, $approvedByIds, $toID );
       $receiver = ($approvalProcess->user_id === $memo->to);

       // Fetch all approvers in one query
       $allApprovers = User::whereIn('id', $allApproversIds)
           ->select('id', 'firstName', 'lastName', 'position', 'signature', 'branch')
           ->get()
           ->keyBy('id');


       $to = User::with('branch')
           ->where('id', $toID)
           ->select('id', 'firstName', 'lastName', 'position','signature', 'branch_code') // Ensure 'branch_code' is selected
           ->first();

       $branchCode = $to->branch ? $to->branch->branch_code : 'No branch assigned';
       $branchName = $to->branch ? $to->branch->branch : 'No branch assigned';

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
    
                // Prepare the response format
                $approver = $approvalProcess->user; // Eager loaded approver
    
                // Determine pending approver
                $pendingApprover = null;
                if ($isUserTurn && $isLastApprover ) {
                    $pendingApprover = "{$approver->firstName} {$approver->lastName}";
                } elseif ($isDisapproved) {
                    $pendingApprover = 'No Pending Approver';
                } else {
                    $pendingApprover = $pendingApproverr ? "{$pendingApproverr->firstName} {$pendingApproverr->lastName}" : 'No Pending Approver';
                }
    
    
    
                return [
                    'id' => $memo->id,
                    'date' =>$memo->date,
                    'to' => "$to->firstName $to->lastName - $to->position - $branchName - $branchCode ",
                    'from' => $memo->from,
                    're' => $memo->re, 
                    'memo_body' => $memo->memo_body,// Assuming form_data is JSON
                    'status' => $approvalProcess->status, // Include the actual status of the approval process
                    'created_at' => $approvalProcess->created_at,
                    'updated_at' => $approvalProcess->updated_at,
                    //'user_id' => $memo->user_id,
                    //'created_by' => ($requester ? "{$requester->firstName} {$requester->lastName}" : "Unknown"), // Handle null requester
                    'by' =>  $formattedNotedBy,
                    'approved_by' => $formattedApprovedBy,
                    'received_by' => $formattedReceivedBy,
                    'pending_approver' => $pendingApprover, // Update pending approver logic
                    'if_receiver' => $receiver, 
                    'if_replied' => Explain::where('memo_id', $memo->id)->exists(),
                ];
            })->filter(); // Filter out null values
    
            return response()->json([
                'message' => 'Approval processes you are involved in',
                'memo' => $transformedApprovalProcesses->values(), // Ensure it's a zero-indexed array
            ], 200);
    
        } catch (\Exception $e) {
            // Log the exception for debugging purposes
            Log::error('Error in getRequestFormsForApproval', ['error' => $e->getMessage()]);
    
            return response()->json([
                'message' => 'An error occurred',
                'error' => 'An error occurred while processing your request.',
            ], 500);
        }
    }
}
