<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Exception;

class ApproverController extends Controller
{
    public function getApprovers(){
        
        try{

            $approvers = User::where('role','approver')
            ->select('firstName','lastName','position','role')
            ->get();

            return response()->json([
                'message' => 'Memo Approvers retrieved successfully',
                'approvers' => $approvers,
            ]);


        }catch(Exception $e){
            return response()->json([
                'error' => $e->getMessage(),
            ]);
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
}
