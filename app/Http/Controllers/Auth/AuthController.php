<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use App\Services\Auth\AuthService;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
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
        return $this->authService->verifyEmail(
            $request->input('email'),
            $request->input('verification_code')
        );
    }

    public function resendVerificationCode(Request $request)
    {
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
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        return $this->forgetPasswordService->resetPassword(
            $request->input('email'),
            $request->input('reset_code'),
            $request->input('new_password'),
            $request->input('new_password_confirmation')
        );
    }

    public function logout()
    {
        return $this->authService->logout();
    }

    public function refreshToken()
    {
        return $this->authService->refreshToken();
    }

    // public function getSecurityQuestionByEmail(Request $request)
    // {
    //     $request->validate([
    //         'email' => 'required|email|exists:users,email',
    //     ]);

    //     return $this->authService->getSecurityQuestionByEmail($request->email);
    // }

    // public function setSecurityQuestion(Request $request)
    // {
    //     $request->validate([
    //         'security_answer' => 'required|string|min:3|max:255',
    //     ]);

    //     return $this->authService->setSecurityQuestion($request->all());
    // }
}
