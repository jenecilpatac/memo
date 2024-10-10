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
                'branch_name' => ['required', 'string', 'max:150'],
            // Add other validation rules as needed
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'errors' => $validator->errors(),
                ], 400);
            }

            $branch = Branch::create([
                'branch_code' => $request->branch_code,
                'branch_name' => strtoupper($request->branch_code),
                'branch' => $request->branch,
                'acronym' => match ($request->branch) {
                    "Strong Moto Centrum, Inc." => "SMCT",
                    "Des Strong Motors, Inc." => "DSM",
                    "Honda Des, Inc." => "HD",
                    "Des Appliance, Inc." => "DAP",
                    default => "HO"
                }
            ]);

            return response()->json([
                "status" => true,
                'message' => 'Branch created successfully',
                'data' => $branch,
                
            ], 201); 
        // Return the created branch as a JSON response
    }

    // VIEW BRANCH
    public function viewBranch($ids = null)
{
    try {
        if ($ids) {
            // Convert comma-separated string of IDs to an array
            $idsArray = explode(',', $ids);

            // Fetch branches with specific IDs
            $branches = Branch::whereIn('id', $idsArray)->orderBy('created_at', 'desc')->get();
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

            $branch = Branch::findOrFail($id);

            $validated = $request->validate([
                'branch_code' => 'required|string|max:255|unique:branches,branch_code,' . $branch->id,
                'branch' => 'required|string|max:255',
                'branch_name' => ['required', 'string', 'max:150'],
            ]);

            $branch->update([
                'branch_code' => $request->branch_code,
                'branch_name' => strtoupper($request->branch_name),
                'branch' => $request->branch,
                'acronym' => match ($request->branch) {
                    "Strong Moto Centrum, Inc." => "SMCT",
                    "Des Strong Motors, Inc." => "DSM",
                    "Honda Des, Inc." => "HD",
                    "Des Appliance, Inc." => "DAP",
                    default => "HO"
                }
            ]);

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
