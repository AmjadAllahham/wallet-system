<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\WithdrawalController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\UserHistoryController;
use App\Http\Controllers\AdminDepositController;
use App\Http\Controllers\AdminHistoryController;
use App\Http\Controllers\WalletTransferController;
use App\Http\Controllers\AdminWithdrawalController;
use App\Http\Controllers\TransferCompanyController;
use App\Http\Controllers\Admin\CheckAdminController;
use App\Http\Controllers\CurrencyExchangeController;




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
    Route::post('updateUser/{id}', [CheckAdminController::class, 'updateUser']);
});

Route::get('admin/admins', [UserController::class, 'getAdmins'])->middleware(['auth:sanctum', 'admin']);

Route::middleware('auth:sanctum')->get('/profile', [AuthController::class, 'profile']);
Route::middleware('auth:sanctum')->put('/profile/update', [ProfileController::class, 'update']);
Route::middleware('auth:sanctum')->post('/profile/change-password', [ProfileController::class, 'changePassword']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/wallets', [UserController::class, 'walletBalances']);
});

Route::middleware(['auth:sanctum', 'is_admin'])->post('/admin/deposit', [AdminDepositController::class, 'store']);

Route::middleware('auth:sanctum')->group(function () {
    // طلب السحب (يقدم المستخدم طلب السحب)
    Route::post('/withdrawals/request', [WithdrawalController::class, 'requestWithdrawal']);
    // عرض طلبات السحب الخاصة بالمستخدم
    Route::get('/withdrawals/my', [WithdrawalController::class, 'myWithdrawals']);
});

// مجموعة الراوت التي تحميها صلاحيات الأدمن (مثلاً middleware:auth:api + middleware:admin)
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    // عرض جميع طلبات السحب المعلقة
    Route::get('/withdrawals', [AdminWithdrawalController::class, 'index']);
    // الموافقة على طلب سحب
    Route::post('/withdrawals/{id}/approve', [AdminWithdrawalController::class, 'approve']);
    // رفض طلب سحب
    Route::post('/withdrawals/{id}/reject', [AdminWithdrawalController::class, 'reject']);
    Route::post('/manual-withdrawal', [WithdrawalController::class, 'manualWithdrawal']);

});

Route::get('/transfer-companies', [TransferCompanyController::class, 'index']);

Route::middleware('auth:sanctum')->post('/crosswalletexchange', [WalletTransferController::class, 'crosswalletexchange']);

Route::middleware('auth:sanctum')->post('/wallet/exchange', [CurrencyExchangeController::class, 'convert']);

// للمستخدم العادي
Route::middleware('auth:sanctum')->get('/history', [UserHistoryController::class, 'index']);
// للأدمن فقط
Route::middleware(['auth:sanctum', 'admin'])->get('/admin/history', [AdminHistoryController::class, 'index']);

