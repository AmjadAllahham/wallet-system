<?php

use App\Services\Admin\UserService;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\CheckAdminController;
use App\Http\Controllers\Admin\UserController;

// -----------------------
// Auth Routes
// -----------------------


Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);
Route::post('verify_email', [AuthController::class, 'verifyEmail']);
Route::post('resend_verification_code', [AuthController::class, 'resendVerificationCode']);

Route::post('forgot_password', [AuthController::class, 'forgotPassword']);
Route::post('reset_password', [AuthController::class, 'resetPassword']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('refresh_token', [AuthController::class, 'refreshToken']);

    Route::get('security_question', [AuthController::class, 'getSecurityQuestionByEmail']);
    Route::post('set_security_question', [AuthController::class, 'setSecurityQuestion']);
});


Route::prefix('admin')->middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('users', [UserController::class, 'index']);
    Route::get('users/{id}', [UserController::class, 'show']);
    Route::get('users-count', [UserController::class, 'countUsers']);
    Route::post('users', [UserController::class, 'create']);
    Route::post('users/{id}', [UserController::class, 'update']);
    Route::delete('users/{id}', [UserController::class, 'destroy']);

    Route::post('updateUser/{id}',[CheckAdminController::class,'updateUser']);
});
