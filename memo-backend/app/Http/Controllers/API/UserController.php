<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{

//REGISTER
public function register(Request $request)
    {
        try {
            $positionData = [
                "Accounting Clerk",
                "Accounting Manager",
                "Accounting Staff",
                "Accounting Supervisor",
                "Admin",
                "Area Manager",
                "Assistant Manager",
                "Assistant Web Developer",
                "Audit Manager",
                "Audit Staff",
                "Audit Supervisor",
                "AVP - Finance",
                "AVP - Sales and Marketing",
                "Branch Supervisor/Manager",
                "Cashier",
                "CEO",
                "HR Manager",
                "HR Staff",
                "IT Staff",
                "IT/Automation Manager",
                "Juinor Web Developer",
                "Managing Director",
                "Payroll Manager",
                "Payroll Staff",
                "Sales Representative",
                "Senior Web Developer",
                "Vice - President",
              ];
              
            $uservalidate = Validator::make($request->all(), [
                "firstName" => 'required|string|max:255',
                "lastName" => 'required|string|max:255',
                "contact" => 'required|string|max:255',
                "branch_code" => 'string|exists:branches,id',
                "userName" => 'required|string|max:255',
                "email" => "required|email|unique:users,email",
                "password" => "required|min:5",
                "position" => 'required|string|max:255|in:'.implode(',', $positionData),
                "signature" => "sometimes",
                "branch" => "required|string|max:255",
                "employee_id" => "required|string|max:255|unique:users,employee_id",
           
            

            ]);

            if ($uservalidate->fails()) {
                return response()->json([
                    "errors" => $uservalidate->errors(),
                ]);
            }
            
            $signature = $request->input('signature');
            $user = User::create([
                "firstName" => $request->firstName,
                "lastName" => $request->lastName,
                "contact" => $request->contact,
                "branch_code" => $request->branch_code,
                "userName" => $request->userName,
                "email" => $request->email,
                "password" => bcrypt($request->password),
                "position" => $request->position,
                'signature' => $signature,
                'role'=> 'User',
                'branch'=> $request->branch,
                'employee_id'=> $request->employee_id,
            ]);

            return response()->json([
                "status" => true,
                "message" => "Registered Successfully",
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                "errors" => $th->getMessage(),
            ]);
        }
    }

//LOGIN

public function login(Request $request){
    try{
    $uservalidate = Validator::make($request->all(), 
    [
        "email"=> "required|email",
        "password"=> "required", 
    ]);

    if($uservalidate->fails()){
        return response()->json(
            [      
                'status' => false,
                'message' => 'Validation Error.',
                "errors" => $uservalidate->errors(),        
            ]);
    }

    if(!Auth::attempt(request()->only("email","password"))){
        return response()->json(
        [  
            "status"=> false,
            "message"=> "Emails and password does not matched with our records",
            'errors' => $uservalidate->errors()
        ]);

    }

    /** @var \App\Models\User $user */
    $user = Auth::user();
     

    return response()->json(
        [
            "status" => true,
            "message" => "Login successfully. Redirecting you to Dashboard",
            'token' => $user->createToken("API TOKEN")->plainTextToken,
            'role' => $user->role,
            'id' => $user->id ,
            'firstName' => $user->firstName,
            'lastName' => $user->lastName,
            'branch_code' => $user->branch_code,
            'contact' => $user->contact,
            'signature' => $user->signature,
            'email' => $user->email,
            'profile_picture' => $user->profile_picture,
            'employee_id' => $user->employee_id,
        ]);
    } catch (\Throwable $th) {
        return response()->json([
            'status' => false,
            'message' => $th->getMessage(),
        ], 500);
    }
}


public function updateRole(Request $request)
{
    try {
        // Validate request
        $request->validate([
            'role' => 'required|string|max:255',
            'userIds' => 'required|array',
        ]);

        $role = $request->input('role');
        $userIds = $request->input('userIds');

        // Fetch users to update
        $users = User::whereIn('id', $userIds)->get();

        foreach ($users as $user) {
            $user->role = $role;
            $user->save();
        }

        return response()->json([
            'message' => 'Users roles updated successfully',
            'data' => $users,
        ], 200);

    } catch (Exception $e) {
        return response()->json([
            'message' => 'Failed to update users roles',
            'error' => $e->getMessage(),
        ], 500);
    }
}

public function getRole($id)
{
    try {

        $user = User::findOrFail($id);

        return response()->json([
            'message' => 'User role retrieved successfully',
            'user_id' => $user->id,
            'user_role' => $user->role,

        ], 200);

    } catch (Exception $e) {

        return response()->json([
            'message' => 'User not found',
        ], 404);

    } catch (Exception $e) {

        return response()->json([
            'message' => 'An error occurred while retrieving the user role',
            'error' => $e->getMessage()
        ], 500);
    }
}

}
