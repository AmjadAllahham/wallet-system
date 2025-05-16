<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use App\Services\Auth\AuthService;
use App\Http\Controllers\Controller;
use App\Services\Auth\ForgetPasswordService;

class AuthController extends Controller
{
    protected $authService;
    protected $forgetPasswordService;

    public function __construct(AuthService $authService, ForgetPasswordService $forgetPasswordService)
    {
        $this->authService = $authService;
        $this->forgetPasswordService = $forgetPasswordService;
    }

    public function register(Request $request)
    {
        $data = $request->all();
        $data['ip_address'] = $request->ip();
        return $this->authService->register($data);
    }

    public function login(Request $request)
    {
        return $this->authService->login($request->all());
    }

    public function verifyEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'verification_code' => 'required|string',
        ]);

        return $this->authService->verifyEmail(
            $request->input('email'),
            $request->input('verification_code')
        );
    }

    public function resendVerificationCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        return $this->authService->resendVerificationCode($request->input('email'));
    }

    public function forgotPassword(Request $request)
    {
        return $this->forgetPasswordService->forgotPassword($request->input('email'));
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'reset_code' => 'required|string',
            'security_answer' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        // التحقق من إجابة سؤال الأمان قبل الإرسال إلى ForgetPasswordService
        $user = \App\Models\User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        if (strtolower(trim($user->answer_security)) !== strtolower(trim($request->security_answer))) {
            return response()->json(['message' => 'Security answer is incorrect.'], 400);
        }

        return $this->forgetPasswordService->resetPassword($request->all());
    }


    public function logout()
    {
        return $this->authService->logout();
    }

    public function refreshToken()
    {
        return $this->authService->refreshToken();
    }

    public function profile(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'message' => 'User profile retrieved successfully.',
            'data' => [
                'account_number' => $user->account_number,
                'full_name' => $user->first_name . ' ' . $user->last_name,
                'email' => $user->email,
            ]
        ]);
    }

    

}
