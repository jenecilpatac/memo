<?php

use App\Http\Controllers\API\MemoController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\ApprovalProcessController;
use App\Http\Controllers\API\ApproverController;
use App\Http\Controllers\API\BranchController;
use App\Http\Controllers\API\NotificationController;
use App\Http\Controllers\API\ExplainController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Test\PusherController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::post('register',[UserController::class,"register"]);
Route::post('login',[UserController::class,"login"]);
Route::put('update-role',[UserController::class,'updateRole'])->name('update.user.role');
Route::get('get-role',[UserController::class,'getRole'])->name('get.user.role');
Route::get("view-branch", [BranchController::class,"viewBranch"])->name('view.branch');
Route::post("password/email", [UserController::class,"sendResetLinkEmail"])->name("password.forgot");
Route::get("view-all-users", [UserController::class,"viewAllUsers"])->name('view.all.users');

Route::middleware('auth:sanctum')->group(function () {
Route::get("view-user/{id}", [UserController::class,"viewUser"])->name('view.user');
Route::post("update-profile/{id}", [UserController::class,"updateProfile"])->name('update.profile');
Route::post("password/reset", [UserController::class,"reset"])->name("password.reset");
Route::put('change-password/{id}', [UserController::class, 'changePassword'])->name('change.password');
Route::post("update-profilepic/{id}", [UserController::class,"updateProfilePic"])->name('update.profile.picture');

//MEMO
Route::group(['middleware' => ['role:Creator']], function () {
Route::post('create-memo',[MemoController::class,'createMemo'])->name('create.memo');
Route::post("update-memo/{id}", [MemoController::class,"updateMemo"])->name('update.memo');
Route::get('view-memo/{currentUserId}',[MemoController::class,'viewMemo'])->name('view.memo'); });
Route::group(['middleware' => ['role:approver,User,BranchHead']], function () {
Route::put('memo/{memo_id}/process', [ApprovalProcessController::class, 'processMemo'])->name('process.memo'); 
Route::get('memo/for-approval/{user_id}', [ApprovalProcessController::class, 'getMemoForApproval'])->name('get.memo.for.approval');});

//EXPLAIN
Route::group(['middleware' => ['role:User,BranchHead']], function () {
Route::post('create-explain',[ExplainController::class,'createExplain'])->name('create.explain');
Route::post("update-explain/{id}", [ExplainController::class,"updateExplain"])->name('update.explain');
Route::get('view-explain/{currentUserId}',[ExplainController::class,'viewExplain'])->name('view.explain');});

Route::group(['middleware' => ['role:BranchHead,Creator']], function () {
Route::put('explain/{explain_id}/process', [ExplainController::class, 'processExplain'])->name('process.explain');
Route::get('explain/for-approval/{user_id}', [ExplainController::class, 'getExplainForApproval'])->name('get.explain.for.approval');});

//APPROVERS
Route::group(['middleware' => ['role:Creator']], function () {
Route::get('get-approvers/{user_id}',[ApproverController::class,'getApprovers'])->name('get.approvers');});

Route::group(['middleware' => ['role:BranchHead,User']], function () {
Route::get('get-explain-approvers/{user_id}',[ApproverController::class,'getExplainApprovers'])->name('get.explain.approvers');});

Route::group(['middleware' => ['role:Admin']], function () {
Route::delete('delete-approver',[ApproverController::class,'deleteApprover'])->name('delete.approver');

//BRANCH
Route::post("add-branch", [BranchController::class,"createBranch"])->name('create.branch');
Route::post("update-branch/{id}", [BranchController::class,"updateBranch"])->name('update.branch');
Route::delete("delete-branch/{id}", [BranchController::class,"deleteBranch"])->name('delete.branch');});

Route::get('total-memo-sent', [UserController::class, 'totalMemoSent'])->name('total.memo.sent');
Route::get('total-memo-received', [UserController::class, 'totalMemoReceived'])->name('total.memo.received');
Route::get('total-explain-sent', [UserController::class, 'totalExplainSent'])->name('total.explain.sent');
Route::get('total-explain-received', [UserController::class, 'totalExplainReceived'])->name('total.explain.received');

//NOTIFICATION
Route::get('notifications/{id}/all', [NotificationController::class, 'getAllNotifications'])->name('get.all.notification');
Route::get('notifications/{id}', [NotificationController::class, 'getNotifications'])->name('get.notification');
Route::get('notifications/{id}/unread', [NotificationController::class, 'getUnreadNotifications'])->name('get.unread.notification');
Route::put('notifications/{id}/mark-as-read', [NotificationController::class, 'markAsRead'])->name('put.mark.as.read.notification');
Route::get('notifications/{id}/count-unread-notification', [NotificationController::class, 'countUnreadNotifications'])->name('count.unread.notification');
Route::put('notifications/mark-all-as-read/{userId}', [NotificationController::class, 'markAllAsRead'])->name('get.mark.all.as.read.notification');
});
