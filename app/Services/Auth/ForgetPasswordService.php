<?php

namespace App\Services\Auth;

use Exception;
use App\Models\User;
use App\Mail\ResetPassword;
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
    public function forgotPassword(string $email)
    {
        $user = User::where('email', $email)->first();

        if (!$user) {
            // لتفادي كشف وجود إيميلات، يمكن إرجاع رسالة عامة
            return response()->json(['message' => 'If the email exists, a reset code has been sent.'], 200);
        }

        $cacheKey = 'password_reset_' . $email;

        // تحقق من عدد المحاولات لمنع الهجمات
        $cachedData = Cache::get($cacheKey);
        if ($cachedData && isset($cachedData['attempts']) && $cachedData['attempts'] >= 3) {
            return response()->json(['message' => 'You have reached the maximum attempts. Please try again later.'], 429);
        }

        // توليد رمز من 6 أحرف أرقام (digits) فقط لسهولة الإدخال
        $resetCode = $this->generateNumericCode(6);

        if (!$this->sendPasswordResetEmail($email, $resetCode)) {
            return response()->json(['message' => 'Failed to send password reset code.'], 500);
        }

        // إعداد بيانات الكاش
        $attempts = $cachedData['attempts'] ?? 0;

        Cache::put($cacheKey, [
            'email' => $email,
            'reset_code' => $resetCode,
            'ip_address' => request()->ip(),
            'attempts' => $attempts + 1,
            'created_at' => now(),
        ], now()->addMinutes(10));

        return response()->json(['message' => 'If the email exists, a reset code has been sent.'], 200);
    }

    /**
     * التحقق من رمز إعادة التعيين وتحديث كلمة المرور
     */
    public function resetPassword(array $data)
    {
        $validator = Validator::make($data, [
            'email' => 'required|email',
            'reset_code' => 'required|string|size:6',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $cacheKey = 'password_reset_' . $data['email'];
        $cachedData = Cache::get($cacheKey);

        $isProduction = app()->environment('production');

        if (
            !$cachedData ||
            $cachedData['reset_code'] !== $data['reset_code'] ||
            ($isProduction && $cachedData['ip_address'] !== request()->ip())
        ) {
            return response()->json(['message' => 'Invalid reset code or request from different device.'], 400);
        }

        $user = User::where('email', $data['email'])->first();

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        // تحديث كلمة المرور
        $user->password = Hash::make($data['new_password']);
        $user->save();

        // حذف بيانات إعادة التعيين من الكاش
        Cache::forget($cacheKey);

        return response()->json(['message' => 'Password reset successfully.'], 200);
    }

    /**
     * إرسال بريد إعادة تعيين كلمة المرور
     */
    private function sendPasswordResetEmail(string $email, string $resetCode): bool
    {
        try {
            Mail::to($email)->send(new ResetPassword($resetCode));
            return true;
        } catch (Exception $e) {
            Log::error('Failed to send reset code: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * توليد رمز أرقام عشوائي
     */
    private function generateNumericCode(int $length = 6): string
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $code = '';
        $maxIndex = strlen($characters) - 1;

        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[random_int(0, $maxIndex)];
        }

        return $code;
    }
}
