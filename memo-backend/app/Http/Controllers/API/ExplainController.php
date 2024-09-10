<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ExplainApprovalProcess;
use App\Models\Explain;
use App\Models\User;
use App\Models\Memo;
use App\Notifications\ExplainApprovalProcessNotification;
use App\Notifications\ExplainReturnRequestNotification;
use App\Notifications\ExplainEmployeeNotification;
use Illuminate\Support\Facades\DB;

class ExplainController extends Controller
{
    public function createExplain(Request $request)
{
    DB::beginTransaction(); // Begin the transaction to ensure data integrity

    try {
        // Validate request data
        $explainvalidate = $request->validate([
            'user_id' => 'required|exists:users,id',
            'memo_id' => 'required|exists:memos,id',
            'date' => 'required|',
            'explain_body' => 'required|string',
            'noted_by' => 'required|array',
            'noted_by.*' => 'exists:users,id', 
        ]);

        $userID = $explainvalidate['user_id'];
        $notedbyid = $explainvalidate['noted_by']; 
        $memoId = $explainvalidate['memo_id'];

        $memo = Memo::findOrFail($memoId);
        $whocreatedMemoId = $memo->user_id;        

        $explain = Explain::create([
            'user_id' => $userID,
            'memo_id' => $memoId,
            'date' => $request->date,
            'header_name' =>[
                'name' => 'CHARISSE RAMISO',
                'position' => 'HR Manager',
                'branch' => 'SMCT Group of Companies Inc'
            ],
            'explain_body' => $request->explain_body,
            'noted_by' => json_encode($notedbyid), 
            'createdMemo' => $whocreatedMemoId,
        ]);

        // Initialize level
        $level = 1;
        $firstApprover = null;

        foreach ($notedbyid as $byId) {
            ExplainApprovalProcess::create([
                'user_id' => $byId, // Now each approver ID is handled correctly
                'explain_id' => $explain->id,
                'level' => $level,
                'status' => 'Pending',
            ]);

            if ($level === 1) {
                $firstApprover = $byId; // Store the first approver ID

            }

            $level++;
        }

        // Create approval process for the user who created the memo
        ExplainApprovalProcess::create([
            'user_id' => $whocreatedMemoId,
            'explain_id' => $explain->id,
            'level' => $level, // Level after noted_by
            'status' => 'Pending',
        ]);

        // Retrieve requester's first name and last name
        $userExplain = User::find($userID);
        $userExplainFirstName = $userExplain->firstName;
        $userExplainLastName = $userExplain->lastName;

        // Notify the first approver
        if ($firstApprover) {
            $firstApproverUser = User::find($firstApprover);

            if ($firstApproverUser) {
                $firstApprovalProcess = ExplainApprovalProcess::where('explain_id', $explain->id)
                    ->where('user_id', $firstApprover)
                    ->where('level', 1)
                    ->first();

                $firstname = $firstApproverUser->firstName;
                $firstApproverUser->notify(new ExplainApprovalProcessNotification($firstApprovalProcess, $firstname, $userExplainFirstName, $userExplainLastName));
            }
        }

        DB::commit(); // Commit the transaction

        return response()->json([
            'status' => true,
            'message' => 'Explain created successfully',
            'data' => $explain,
        ]);
    } catch (\Exception $e) {
        DB::rollBack(); // Roll back the transaction in case of error
        return response()->json([
            'error' => $e->getMessage(),
        ]);
    }
}


    public function viewExplain($currentUserId)
    {
        try {
        
            //$currentUserId = auth()->user()->id;

            // Fetch request forms where user_id matches the current user's ID
            $explains = Explain::where('user_id', $currentUserId)
                ->select('id', 'user_id', 'date','memo_id', 'header_name', 'explain_body', 'status','noted_by','createdMemo')
                ->with('approvalProcess')
                ->get();


            // Initialize an array to hold the response data
            $response = $explains->map(function ($explain) use ($currentUserId){
                // Decode the approvers_id fields, defaulting to empty arrays if null

                $memo = Memo::findOrFail($explain->memo_id);
                $memoNameRe = $memo->re;

                $notedByIds = is_string($explain->noted_by) ? json_decode($explain->noted_by, true) : [];
        
                // Ensure both are arrays
                $notedByIds = is_array($notedByIds) ? $notedByIds : [];
                $createdMemo = $explain->createdMemo;
                $by = $explain->user_id;

                $whoExplains = ($currentUserId == $explain->user_id);

                $allApproversIds = array_merge($notedByIds,[$createdMemo],[$by]);


                // Fetch all approvers in one query
                $allApprovers = User::whereIn('id', $allApproversIds)
                    ->select('id', 'firstName', 'lastName', 'position', 'signature', 'branch')
                    ->get()
                    ->keyBy('id');

        
                // Fetch all approval statuses and comments in one query
                $approvalData = ExplainApprovalProcess::whereIn('user_id', $allApproversIds)
                    ->where('explain_id', $explain->id)
                    ->get()
                    ->keyBy('user_id');


                // Format approved_by users
                $formattedNotedBy = $notedByIds
                    ? collect($notedByIds)->map(function ($userId) use ($allApprovers, $approvalData) {
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
                $formattedCreatedMemo = $createdMemo
                    ? collect($createdMemo)->map(function ($userId) use ($allApprovers, $approvalData) {
                        if (isset($allApprovers[$userId])) {
                            $user = $allApprovers[$userId];
                            $approval = $approvalData[$userId] ?? null;

                            return [
                                'createdMemo' => "$user->firstName $user->lastName",
                            ];
                        }
                    })->filter()->values()->all()
                    : [];
                $formattedBy = $by
                ? collect($by)->map(function ($userId) use ($allApprovers, $approvalData) {
                    if (isset($allApprovers[$userId])) {
                        $user = $allApprovers[$userId];
                        $approval = $approvalData[$userId] ?? null;

                        return [
                            'firstname' => $user->firstName,
                            'lastname' => $user->lastName,
                            'position' => $user->position,
                            'signature' => $user->signature,
                        ];
                    }
                })->filter()->values()->all()
                : [];

                // Get the pending approver
                $pendingApprover = $explain->approvalProcess()
                    ->where('status', 'Pending')
                    ->orderBy('level')
                    ->first()?->user;
                    

                return [
                    'id' => $explain->id,
                    'user_id' => $explain->user_id,
                    'memo_id' =>$explain->memo_id,
                    'memo_re' => $memoNameRe,
                    'date' =>$explain->date,
                    'header_name' => $explain->header_name,
                    'explain_body' => $explain->explain_body,
                    'status' => $explain->status,
                    'sincerely' => $formattedBy,
                    'noted_by' => $formattedNotedBy,
                    'createdMemo' => $formattedCreatedMemo,
                    'pending_approver' => $pendingApprover ? [
                        'approver_name' => "{$pendingApprover->firstName} {$pendingApprover->lastName}",
                    ] : "No Pending Approver",
                    'if_whoExplains' => $whoExplains
                ];
            });

            return response()->json([
                'message' => 'Explain retrieved successfully',
                'data' => $response
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while retrieving memo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateExplain(Request $request, $id)
    {
        DB::beginTransaction(); // Begin the transaction to ensure data integrity

        $approvalProcesses = ExplainApprovalProcess::where('explain_id', $id)->get();

        $firstApprovalProcess = $approvalProcesses->firstWhere('level', 1);
        if ($firstApprovalProcess && in_array($firstApprovalProcess->status, ['Approved', 'Disapproved'])) {
            return response()->json(['message' => 'Memo cannot be updated because the first approver has already acted on it'], 400);
        }

        try {
            // Validate request data
            $explainvalidate = $request->validate([
                'user_id' => 'required|exists:users,id',
                'memo_id' => 'required|exists:memos,id',
                'date' => 'required',
                'explain_body' => 'required|string',
                'noted_by' => 'required|array',
                'noted_by.*' => 'exists:users,id',
            ]);

            // Retrieve and update the explain
            $explain = Explain::findOrFail($id);

            // Only update fields that changed
            $explain->update(array_filter([
                'user_id' => $explainvalidate['user_id'],
                'memo_id' => $explainvalidate['memo_id'],
                'date' => $request->date,
                'header_name' => [
                    'name' => 'CHARISSE RAMISO',
                    'position' => 'HR Manager',
                    'branch' => 'SMCT Group of Companies Inc'
                ],
                'explain_body' => $request->explain_body,
                'noted_by' => json_encode($explainvalidate['noted_by']),
            ]));

            // Initialize level and approval process
            $notedbyid = $explainvalidate['noted_by'];
            $level = 1;
            $approvalProcesses = [];

            // Delete old approval processes in a single query
            ExplainApprovalProcess::where('explain_id', $explain->id)->delete();

            // Batch create approval processes for noted_by
            foreach ($notedbyid as $byId) {
                $approvalProcesses[] = [
                    'user_id' => $byId,
                    'explain_id' => $explain->id,
                    'level' => $level,
                    'status' => 'Pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $level++;
            }

            // Add the process for the user who created the memo
            $memo = Memo::findOrFail($explainvalidate['memo_id']);

            $approvalProcesses[] = [
                'user_id' => $memo->user_id,
                'explain_id' => $explain->id,
                'level' => $level,
                'status' => 'Pending',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Insert all approval processes in one query
            ExplainApprovalProcess::insert($approvalProcesses);

            // Notify the first approver only if there was a change in approvers
            $firstApprover = $notedbyid[0];
            $firstApproverUser = User::find($firstApprover);

            if ($firstApproverUser) {
                $firstApprovalProcess = ExplainApprovalProcess::where('explain_id', $explain->id)
                    ->where('user_id', $firstApprover)
                    ->where('level', 1)
                    ->first();

                $userExplain = User::find($explainvalidate['user_id']);
                $firstApproverUser->notify(new ExplainApprovalProcessNotification(
                    $firstApprovalProcess, 
                    $firstApproverUser->firstName, 
                    $userExplain->firstName, 
                    $userExplain->lastName
                ));
            }

            DB::commit(); // Commit the transaction

            return response()->json([
                'status' => true,
                'message' => 'Explain updated successfully',
                'data' => $explain,
            ]);
        } catch (\Exception $e) {
            DB::rollBack(); // Roll back the transaction in case of error
            return response()->json([
                'error' => $e->getMessage(),
            ]);
        }
    }


    public function processExplain(Request $request, $explain_id)
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
                $explain = Explain::findOrFail($explain_id);
        
                $approvalProcess = ExplainApprovalProcess::where('explain_id', $explain_id)
                    ->where('user_id', $user_id)
                    ->where('status', 'Pending')
                    ->first();
        
                if (!$approvalProcess) {
                    return response()->json([
                        'message' => 'You are not authorized to process this explaination or it has already been processed.',
                    ], 403);
                }
        
                $currentApprovalLevel = ExplainApprovalProcess::where('explain_id', $explain_id)
                    ->where('status', 'Pending')
                    ->orderBy('level')
                    ->first();
        
            // Ensure the user is the current approver
            if ($currentApprovalLevel && $currentApprovalLevel->user_id !== $user_id) {
                return response()->json([
                    'message' => 'It is not your turn to process this explaination.',
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
                    $firstApprovalProcess = ExplainApprovalProcess::where('explain_id', $explain_id)
                        ->orderBy('level')
                        ->first();
        
                    if ($firstApprovalProcess && $firstApprovalProcess->user_id == $user_id) {
                        $explain->status = 'Ongoing';
                        $explain->save();
                    }
        
                    $nextApprovalProcess = ExplainApprovalProcess::where('explain_id', $explain_id)
                        ->where('status', 'Pending')
                        ->orderBy('level')
                        ->first();
        
                    if ($nextApprovalProcess) {
                        $nextApprover = $nextApprovalProcess->user;
                        $firstname = $nextApprover->firstName;
                        $employee = $explain->user;
                        $requesterFirstname = $employee->firstName;
                        $requesterLastname = $employee->lastName;
                        $nextApprover->notify(new ExplainApprovalProcessNotification($nextApprovalProcess, $firstname,$requesterFirstname,$requesterLastname));
                    } else {
                        $explain->status = 'Approved';
                        $explain->save();
                        $employee = $explain->user;
                        $firstname = $employee->firstName;
                        // Notify employee
                        $employee->notify(new ExplainEmployeeNotification($explain, 'approved', $firstname));
                    }
                } elseif ($action === 'receive') {
                    $explain->status = 'Received';
                    $explain->save();
                    $employee = $explain->user;
                    $firstname = $employee->firstName;
                    // Notify employee
                     $employee->notify(new ExplainEmployeeNotification($explain, 'received', $firstname));
                } else { // disapprove
                    $explain->status = 'Disapproved';
                    $explain->save();
                    $employee = $explain->user;
                    $firstname = $employee->firstName;
                    $approverFirstname = $approvalProcess->user->firstName;
                    $approverLastname = $approvalProcess->user->lastName;
                    // Notify employee
                    $employee->notify(new ExplainReturnRequestNotification('disapproved', $firstname, $approverFirstname, $approverLastname));
        
                    // Notify previous approvers
                    $previousApprovalProcesses = ExplainApprovalProcess::where('explain_id', $explain_id)
                        ->where('status', 'Approved')
                        ->orderBy('level', 'asc')
                        ->get();
        
                    foreach ($previousApprovalProcesses as $previousApprovalProcess) {
                        $previousApprover = $previousApprovalProcess->user;
                        $prevFirstName = $previousApprover->firstName;
        
                        $previousApprovalProcess->update([
                            'status' => "Rejected by $approverFirstname $approverLastname",
                        ]);
        
                        $requesterFirstname = $employee->firstName;
                        $requesterLastname = $employee->lastName;
                        // Notify previous approver
                        //$previousApprover->notify(new PreviousReturnRequestNotification($memo, 'disapproved', $prevFirstName, $approverFirstname, $approverLastname, $comment, $requesterFirstname, $requesterLastname));
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

    public function getExplainForApproval($user_id)
        {
            try {
                // Retrieve all approval processes where the current user is involved
                $approvalProcesses = ExplainApprovalProcess::where('user_id', $user_id)
                    ->orderBy('level')
                    ->with(['explain.user', 'user']) // Eager load explain and user
                    ->get();
        
                // Process each approval process
                $transformedApprovalProcesses = $approvalProcesses->map(function ($approvalProcess) use ($user_id) {
                    $explain = $approvalProcess->explain;
                    $requester = $explain->user ?? null; // Ensure $explain->user is not null
                    $memo = Memo::findOrFail($explain->memo_id);
                    $memoNameRe = $memo->re;
        
                    // Check if previous levels are disapproved or approved
                    $previousLevelsDisapproved = $explain->approvalProcess
                        ->where('level', '<', $approvalProcess->level)
                        ->contains('status', 'Disapproved');
        
                    $previousLevelsApproved = $explain->approvalProcess
                        ->where('level', '<', $approvalProcess->level)
                        ->every(function ($process) {
                            return $process->status == 'Approved';
                        });
        
                    // Determine if current user is the last approver
                    $isLastApprover = $explain->approvalProcess()
                        ->where('status', 'Pending')
                        ->where('level', '>', $approvalProcess->level)
                        ->exists();
        
                    // Check if the request is disapproved
                    $isDisapproved = $explain->approvalProcess()
                        ->where('status', 'Disapproved')
                        ->exists();
        
                    // Determine the next approver
                    $nextApprover = $explain->approvalProcess()
                        ->where('status', 'Pending')
                        ->orderBy('level')
                        ->first()?->user; // Safely access the next approver
        
                    // Determine if it's the user's turn to approve
                    $isUserTurn = $previousLevelsApproved && $approvalProcess->status == 'Pending' && $approvalProcess->user_id == $user_id;

                    
        
                    // Only include relevant statuses
                    $isRelevantStatus = in_array($approvalProcess->status, ['Approved', 'Disapproved','Received']) ||
                        preg_match('/^Rejected by/', $approvalProcess->status);
        
                    if (!$isRelevantStatus && !$isUserTurn) {
                        return null; // Skip if not relevant and not user's turn
                    }
        
                    // Handle noted_by and createdMemo approvers
                    $notedByIds = is_string($explain->noted_by) ? json_decode($explain->noted_by, true) : [];
                    $notedByIds = is_array($notedByIds) ? $notedByIds : []; // Ensure it's an array
        
                    $createdMemoId = $explain->createdMemo ?? null;
                    $userExplainId = $explain->user_id;
                    // Fetch all approvers in one query, ensuring that $createdMemoId is not null
                    $allApproversIds = array_merge($notedByIds,[$userExplainId],[$createdMemoId]);
                    $allApprovers = User::whereIn('id', $allApproversIds)
                        ->select('id', 'firstName', 'lastName', 'position', 'signature', 'branch')
                        ->get()
                        ->keyBy('id');
        
                    // Fetch all approval statuses and comments in one query
                    $approvalData = ExplainApprovalProcess::whereIn('user_id', $allApproversIds)
                        ->where('explain_id', $explain->id)
                        ->get()
                        ->keyBy('user_id');
        
                    // Format noted_by users
                    $formattedNotedBy = collect($notedByIds)->map(function ($userId) use ($allApprovers, $approvalData) {
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
                    })->filter()->values()->all();
        
                    $formattedCreatedMemo = collect($createdMemoId)->map(function ($userId) use ($allApprovers, $approvalData) {
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
                    })->filter()->values()->all();

                    $formattedBy = collect($userExplainId)->map(function ($userId) use ($allApprovers, $approvalData) {
                        if (isset($allApprovers[$userId])) {
                            $user = $allApprovers[$userId];
                            $approval = $approvalData[$userId] ?? null;
        
                            return [
                                'firstname' => $user->firstName,
                                'lastname' => $user->lastName,
                                'position' => $user->position,
                                'signature' => $user->signature,
                            ];
                        }
                    })->filter()->values()->all();
        
                    // Determine the pending approver
                    $pendingApprover = $isUserTurn && $isLastApprover
                        ? "{$approvalProcess->user->firstName} {$approvalProcess->user->lastName}"
                        : ($isDisapproved ? 'No Pending Approver' : ($nextApprover ? "{$nextApprover->firstName} {$nextApprover->lastName}" : 'No Pending Approver'));
        

                        $createdMemo = ($approvalProcess->user_id === (int) $explain->createdMemo);


                    return [
                        'id' => $explain->id,
                        'memo_id' =>$explain->memo_id,
                        'memo_re' => $memoNameRe,
                        'date' => $explain->date,
                        'header_name' => $explain->header_name,
                        'explain_body' => $explain->explain_body,
                        'status' => $approvalProcess->status,
                        'sincerely' => $formattedBy,
                        'noted_by' => $formattedNotedBy,
                        'createdMemo' => $formattedCreatedMemo,
                        'pending_approver' => $pendingApprover,
                        'is_createdMemo' =>$createdMemo,
                        'created_at' => $approvalProcess->created_at,
                        'updated_at' => $approvalProcess->updated_at,
                        'if_receiver' => ($approvalProcess->user_id == $explain->createdMemo),                    
                    ];
                })->filter(); // Filter out null values
        
                return response()->json([
                    'message' => 'Approval processes you are involved in',
                    'memo' => $transformedApprovalProcesses->values(), // Ensure it's a zero-indexed array
                ], 200);
        
            } catch (\Exception $e) {
                return response()->json([
                    'message' => 'An error occurred',
                    'error' => $e->getMessage(), // Include the actual error message for debugging
                ], 500);
            }
        }
    
    
}


