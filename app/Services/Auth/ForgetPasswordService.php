<?php

namespace App\Services\Auth;

use Exception;
use App\Models\User;
use App\Mail\ResetPassword;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class ForgetPasswordService
{
    /**
     * إرسال رمز إعادة تعيين كلمة المرور للبريد الإلكتروني المُحدد
     */
    public function forgotPassword($email)
    {
        $user = User::where('email', $email)->first();

        if (!$user) {
            return response()->json(['message' => 'Email address not found.'], 404);
        }

        // توليد رمز من 6 أحرف
        $resetCode = Str::random(6);

        // محاولة إرسال البريد الإلكتروني مع رمز إعادة التعيين
        if (!$this->sendPasswordResetEmail($email, $resetCode)) {
            return response()->json(['message' => 'Failed to send password reset code.'], 500);
        }

        // تخزين بيانات إعادة التعيين في الكاش لمدة 10 دقائق
        $cacheKey = 'password_reset_' . $email;
        Cache::put($cacheKey, [
            'email'      => $email,
            'reset_code' => $resetCode,
            'ip_address' => request()->ip(),
        ], now()->addMinutes(10));

        return response()->json(['message' => 'A password reset code has been sent to your email.'], 200);
    }

    /**
     * التحقق من رمز إعادة التعيين وتحديث كلمة المرور
     */
    public function resetPassword($data)
    {
        // التحقق من صحة البيانات المدخلة
        $validator = Validator::make($data, [
            'email'         => 'required|email',
            'reset_code'    => 'required|string|size:6',
            'new_password'  => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        $cacheKey   = 'password_reset_' . $data['email'];
        $cachedData = Cache::get($cacheKey);

        // يتم التحقق من عنوان الـ IP فقط في بيئة الإنتاج
        $isProduction = app()->environment('production');

        if (
            !$cachedData ||
            $cachedData['reset_code'] !== $data['reset_code'] ||
            ($isProduction && $cachedData['ip_address'] !== request()->ip())
        ) {
            return response()->json(['message' => 'Invalid verification code or from a different device.'], 400);
        }

        $user = User::where('email', $data['email'])->first();

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        // تحديث كلمة المرور مع عملية الهاش
        $user->password = Hash::make($data['new_password']);
        $user->save();

        // إزالة بيانات الكاش بعد إتمام العملية
        Cache::forget($cacheKey);

        return response()->json(['message' => 'Password reset successfully.'], 200);
    }

    /**
     * إرسال بريد إعادة تعيين كلمة المرور
     */
    private function sendPasswordResetEmail($email, $resetCode)
    {
        try {
            Mail::to($email)->send(new ResetPassword($resetCode));
            return true;
        } catch (Exception $e) {
            // يمكن تسجيل الخطأ للمراجعة
            Log::error('Failed to send reset code: ' . $e->getMessage());
            return false;
        }
    }
}
