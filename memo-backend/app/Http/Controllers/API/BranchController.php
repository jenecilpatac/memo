<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Branch;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class BranchController extends Controller
{
    // CREATE BRANCH
    public function createBranch(Request $request)
    {
       
            $validator = Validator::make($request->all(), [
                'branch_code' => 'required|string|max:255|unique:branches,branch_code',
                // Add other validation rules as needed
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'errors' => $validator->errors(),
                ], 400);
            }

            $branch = Branch::create([
                'user_id' => auth()->id(),
                'branch_code' => $request->branch_code,
                'branch' => $request->branch,

            ]);

            return response()->json([
                "status" => true,
                'message' => 'Branch created successfully',
                'data' => $branch,
                
            ]); 
       
    }

    // VIEW BRANCH
    public function viewBranch($ids = null)
{
    try {
        if ($ids) {
            // Convert comma-separated string of IDs to an array
            $idsArray = explode(',', $ids);

            // Fetch branches with specific IDs
            $branches = Branch::whereIn('id', $idsArray)->get();
        } else {
            // Fetch all branches if no IDs are provided
            $branches = Branch::all();
        }

        return response()->json([
            'message' => 'Branches retrieved successfully',
            'data' => $branches,
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Failed to retrieve branches',
            'error' => $e->getMessage(),
        ], 500);
    }
}

    
    // UPDATE BRANCH
    public function updateBranch(Request $request, $id)
    {
        try {

            $validated = $request->validate([
                'branch_code' => 'required|string|max:255',
                'branch' => 'required|string|max:255',

            ]);

            $branch = Branch::findOrFail($id);

            $branch->update($validated);

            return response()->json([
                'message' => 'Branch updated successfully',
                'data' => $branch
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while updating the branch',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    // DELETE BRANCH
    public function deleteBranch($id)
    {
        try {
            $branch = Branch::where('id', $id)->first();

            if (!$branch) {
                return response()->json([
                    'message' => 'Branch not found',
                ], 404);
            }

            $branch->delete();

            return response()->json([
                'message' => 'Branch deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            Log::error('An error occurred while deleting branch: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to delete branch',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
