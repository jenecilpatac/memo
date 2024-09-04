<?php

use App\Http\Controllers\API\MemoController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\ApprovalProcessController;
use App\Http\Controllers\API\ApproverController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::post('register',[UserController::class,"register"]);
Route::post('login',[UserController::class,"login"]);
Route::put('update-role',[UserController::class,'updateRole'])->name('update.user.role');
Route::get('get-role',[UserController::class,'getRole'])->name('get.user.role');
Route::post('create-memo',[MemoController::class,'createMemo'])->name('create.memo');
Route::get('view-memo',[MemoController::class,'viewMemo'])->name('view.memo');
Route::put('memo/{memo_id}/process', [ApprovalProcessController::class, 'processMemo'])->name('process.memo');
Route::get('get-approvers',[ApproverController::class,'getApprovers'])->name('get.approvers');
Route::delete('delete-approver',[ApproverController::class,'deleteApprover'])->name('dete.approver');
