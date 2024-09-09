<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Branch;
use Exception;

class ApproverController extends Controller
{
    public function getApprovers($userId)
    {
        try {
    
            // Fetch the ID for the 'HO' branch
            $HObranchID = (int) Branch::where('branch_code', 'HO')->value('id');
    
            // Fetch approvers from the HO branch, excluding the requester if they are an approver
            $HOapprovers = User::where('branch_code', $HObranchID)
                ->where('role', 'approver')
                ->where('id', '!=', $userId)
                ->select('id', 'firstName', 'lastName', 'role', 'position', 'branch_code')
                ->get();
    
    
            return response()->json([
                'message' => 'Approvers retrieved successfully',
                'HOApprovers' => $HOapprovers,
            ], 200);
    
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'User not found',
                'error' => $e->getMessage(),
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'An error occurred while retrieving approvers',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function deleteApprover($user_id)
{
    try {
        
        $user = User::findOrFail($user_id);
        $user->role = 'User'; // Set the role to "user"
        $user->save();

        return response()->json([
            'message' => 'Approver deleted and user role updated successfully',
        ], 200);

    } catch (Exception $e) {
        return response()->json([
            'message' => 'An error occurred while deleting the approver',
            'error' => $e->getMessage()
        ], 500);
    }
}
    public function getExplainApprovers($userId)
    {
        try {

            $user = User::find($userId);

            // Fetch approvers from the branch, excluding the requester if they are an approver
            $BranchHead = User::select('id', 'firstName', 'lastName', 'role', 'position', 'branch_code')
            ->where('branch_code', $user->branch_code)
            ->where('role', 'approver')
            ->where('position', 'Branch Supervisor/Manager')
            ->where('id', '!=', $userId)
            ->get();


            return response()->json([
                'message' => 'Explain approver retrieved successfully',
                'branchHead' => $BranchHead,
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Approver not found',
                'error' => $e->getMessage(),
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'An error occurred while retrieving approvers',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

}
