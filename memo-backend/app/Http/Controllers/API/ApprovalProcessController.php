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
                    $employee = $memo->user;
                    $requesterFirstname = $employee->firstName;
                    $requesterLastname = $employee->lastName;
                    $nextApprover->notify(new ApprovalProcessNotification($nextApprovalProcess, $firstname,$memo,$requesterFirstname,$requesterLastname));
                } else {
                    $memo->status = 'Approved';
                    $memo->save();
                    $employee = $memo->user;
                    $firstname = $employee->firstName;
                    // Notify employee
                    //$employee->notify(new EmployeeNotification($memo, 'approved', $firstname, $memo->form_type));
                }
            } elseif ($action === 'receive') {
                $memo->status = 'Received';
                $memo->save();
                $employee = $memo->user;
                $firstname = $employee->firstName;
                // Notify employee
               // $employee->notify(new EmployeeNotification($memo, 'received', $firstname, $memo->form_type));
            } else { // disapprove
                $memo->status = 'Disapproved';
                $memo->save();
                $employee = $memo->user;
                $firstname = $employee->firstName;
                $approverFirstname = $approvalProcess->user->firstName;
                $approverLastname = $approvalProcess->user->lastName;
                // Notify employee
                //$employee->notify(new ReturnRequestNotification($memo, 'disapproved', $firstname, $approverFirstname, $approverLastname, $comment));
    
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
    

    
    /* public function getRequestFormsForApproval($user_id)
    {
        try {
            // Retrieve all approval processes where the current user is involved
            $approvalProcesses = ApprovalProcess::where('user_id', $user_id)
                ->orderBy('level')
                ->with(['requestForm.user', 'user']) // Eager load request form with user
                ->get();
    
            // Process each approval process
            $transformedApprovalProcesses = $approvalProcesses->map(function ($approvalProcess) use ($user_id) {
                $requestForm = $approvalProcess->requestForm;
                $requester = $requestForm->user; // Eager loaded user
    
                // Check if any previous level is disapproved
                $previousLevelsDisapproved = $requestForm->approvalProcess
                    ->where('level', '<', $approvalProcess->level)
                    ->contains('status', 'Disapproved');
    
                // Check if all previous levels are approved
                $previousLevelsApproved = $requestForm->approvalProcess
                    ->where('level', '<', $approvalProcess->level)
                    ->every(function ($process) {
                        return $process->status == 'Approved';
                    });
    
                // Determine if it's the user's turn to approve
                $isUserTurn = $previousLevelsApproved && $approvalProcess->status == 'Pending' && $approvalProcess->user_id == $user_id;
    
                // Include request forms where the previous level has statuses of Approved, Disapproved, or Rejected by...
                $isRelevantStatus = in_array($approvalProcess->status, ['Approved', 'Disapproved']) ||
                    preg_match('/^Rejected by/', $approvalProcess->status);
    
                if (!$isRelevantStatus && !$isUserTurn) {
                    return null; // Skip if the status is not relevant and it's not the user's turn to approve
                }
    
                // Determine if the current user is the last approver
                $isLastApprover = $requestForm->approvalProcess()
                    ->where('status', 'Pending')
                    ->where('level', '>', $approvalProcess->level)
                    ->count() === 0;
    
                // Determine if the request form has been disapproved
                $isDisapproved = $requestForm->approvalProcess()
                    ->where('status', 'Disapproved')
                    ->count() > 0;
    
                // Determine the next approver
                $pendingApproverr = $requestForm->approvalProcess()
                    ->where('status', 'Pending')
                    ->orderBy('level')
                    ->first()?->user; // Get the next approver
    
       // Fetch approvers details
          $notedByIds = $requestForm->noted_by ?? [];
          $approvedByIds = $requestForm->approved_by ?? [];
    
          $allApproversIds = array_merge($notedByIds, $approvedByIds);
    
          // Fetch all approvers in one query
          $allApprovers = User::whereIn('id', $allApproversIds)
              ->select('id', 'firstName', 'lastName', 'position', 'signature', 'branch')
              ->get()
              ->keyBy('id');
    
          // Fetch all approval statuses and comments in one query
          $approvalData = ApprovalProcess::whereIn('user_id', $allApproversIds)
              ->where('request_form_id', $requestForm->id)
              ->get()
              ->keyBy('user_id');
    
          // Format noted_by users
          $formattedNotedBy = $notedByIds
              ? collect($notedByIds)->map(function ($userId) use ($allApprovers, $approvalData) {
                  if (isset($allApprovers[$userId])) {
                      $user = $allApprovers[$userId];
                      $approval = $approvalData[$userId] ?? null;
    
                      return [
                          'firstname' => $user->firstName,
                          'lastname' => $user->lastName,
                          'status' => $approval->status ?? '',
                          'comment' => $approval->comment ?? '',
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
                                'comment' => $approval->comment ?? '',
                                'position' => $user->position,
                                'signature' => $user->signature,
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
                    'id' => $requestForm->id,
                    'form_type' => $requestForm->form_type,
                    'form_data' => $requestForm->form_data, // Assuming form_data is JSON
                    'status' => $approvalProcess->status, // Include the actual status of the approval process
                    'created_at' => $approvalProcess->created_at,
                    'updated_at' => $approvalProcess->updated_at,
                    'user_id' => $requestForm->user_id,
                    'requested_by' => ($requester ? "{$requester->firstName} {$requester->lastName}" : "Unknown"), // Handle null requester
                    //'approvers_id' => $approvalProcess->user_id, // Include approvers id 
                        'noted_by' =>  $formattedNotedBy,
                        'approved_by' => $formattedApprovedBy,
    
                    'pending_approver' => $pendingApprover, // Update pending approver logic
                    'attachment' => $requestForm->attachment,
                ];
            })->filter(); // Filter out null values
    
            return response()->json([
                'message' => 'Approval processes you are involved in',
                'request_forms' => $transformedApprovalProcesses->values(), // Ensure it's a zero-indexed array
            ], 200);
    
        } catch (\Exception $e) {
            // Log the exception for debugging purposes
            Log::error('Error in getRequestFormsForApproval', ['error' => $e->getMessage()]);
    
            return response()->json([
                'message' => 'An error occurred',
                'error' => 'An error occurred while processing your request.',
            ], 500);
        }
    } */
}
