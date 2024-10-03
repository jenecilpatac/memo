<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Mail\ResetPasswordMail;
use App\Models\Branch;

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
                "Junior Web Developer",
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
                'data' =>$user
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

public function viewAllUsers()
{
    try {
        $users = User::with('branch:id,branch_code,branch')->select('id', 'firstName', 'lastName', 'branch_code','email','userName','role','position','contact','employee_id')->get();

        $allUsers = $users->map(function ($user) {
    
            return [
                'id' => $user->id,
                'firstName' => $user->firstName,
                'lastName' => $user->lastName,
                'branch_id' => $user->branch_code,
                'branch_code' => $user->branch ? $user->branch->branch_code : null,
                'branch' =>  $user->branch ? $user->branch->branch : null,
                'email' => $user->email,
                'userName' => $user->userName,
                'role' => $user->role,
                'position' => $user->position,
                'contact' => $user->contact,
                'employee_id' => $user->employee_id, 
            ];
        });

        return response()->json([
            'message' => 'Users retrieved successfully',
            'data' => $allUsers
        ], 200);
    } catch (Exception $e) {
        return response()->json([
            'message' => 'An error occurred while retrieving users',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function updateProfile(Request $request, $id)
    {
        try {
            // Validate the incoming request
            $validated = $request->validate([
                'firstName' => 'nullable|string|max:255',
                'lastName' => 'nullable|string|max:255',
                'contact' => 'nullable|string|max:255',
                'branch_code' => 'nullable',
                'role'  => 'nullable|string|max:255',
                'userName' => 'nullable|string|max:255',
                'email' => 'nullable|email',
                'position' => 'nullable|string|max:255',
                'profile_picture' => 'nullable|file|mimes:png,jpg,jpeg',
            ]);
    
            $user = User::findOrFail($id);
    
            DB::beginTransaction();
    
            // Save the profile picture if provided
            if ($request->hasFile('profile_picture')) {
                $profilePicture = $request->file('profile_picture');
                $profilePicturePath = $profilePicture->store('profile_pictures', 'public');
                $user->profile_picture = $profilePicturePath; // Save only the path
            }
    
            // Update user details with provided values
            $user->firstName = $validated['firstName'] ?? $user->firstName;
            $user->lastName = $validated['lastName'] ?? $user->lastName;
            $user->contact = $validated['contact'] ?? $user->contact;
            $user->branch_code = $validated['branch_code'] ?? $user->branch_code;
            $user->userName = $validated['userName'] ?? $user->userName;
            $user->email = $validated['email'] ?? $user->email;
            $user->position = $validated['position'] ?? $user->position;
    
            $user->save();
    
            DB::commit();
    
            return response()->json([
                'message' => 'User updated successfully',
                'data' => $user
            ], 200);
    
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation Error',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'An error occurred while updating the user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateProfilePic(Request $request, $id)
    {
        $validatedData = $request->validate([ 
            'profile_picture' => 'nullable|image', 
        ]);
    
        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }
    
        if ($request->hasFile('profile_picture')) {
            $file = $request->file('profile_picture');
            $path = $file->store('profile_pictures', 'public');
            $user->profile_picture = $path;
        }
    
        $user->save();
    
        return response()->json(['message' => 'Profile picture updated successfully'], 200);
    }

    
    public function sendResetLinkEmail(Request $request)
    {

        $request->validate(['email' => 'required|email|exists:users,email']);
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'We can\'t find a user with that email address.'], 404);
        }

        // Generate a 6-letter password
        $newPassword = Str::random(6);

        // Hash the new password
        $hashedPassword = Hash::make($newPassword);

        // Update the user's password
        $user->password = $hashedPassword;
        $user->save();


        // Send the new password to the user
        $firstname = $user->firstName;
        Mail::to($user->email)->send(new ResetPasswordMail($newPassword, $firstname));

        return response()->json([
            'message' => 'We have sent your new password to your email.'], 200);

    }


    //RESET PASSWORD
    public function reset(Request $request)
    {

        $request->validate([
            'token' => 'required',
            'email' => 'required|email|exists:users,email',
            'password' => 'required|min:8|confirmed',
        ]);


        $updatePassword = DB::table('password_reset_tokens')
            ->where([
                "email" => $request->email,
                "token" => $request->token,
            ])->first();

        if (!$updatePassword) {
            return response()->json(['message' => 'Invalid.'], 400);
        }

        User::where("email", $request->email)
            ->update(["password" => Hash::make($request->password)]);

        DB::table(table: "password_reset_tokens")
            ->where(["email" => $request->email])
            ->delete();

        return response()->json(['message' => 'Password reset successfully.'], 200);
    }

    //PROFILE - CHANGE PASSWORD
    public function changePassword(Request $request, $id)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        $user = User::findOrFail($id); // Retrieve the user by ID

        // Verify if the current password matches the user's password in the database
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['error' => 'Current password is incorrect.'], 422);
        }

        // Update the user's password
        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json(['success' => 'Password changed successfully.'], 200);
    }
    //VIEW USER


    public function viewUser($id)
    {
        try {

            $user = User::findOrFail($id);

            return response()->json([
                'message' => 'Users retrieved successfully',
                'data' => $user,
                'status' => true,

            ], 200);

        } catch (Exception $e) {

            return response()->json([
                'message' => 'Users not found',
            ], 404);

        } catch (Exception $e) {

            return response()->json([
                'message' => 'An error occurred while retrieving the user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

}
