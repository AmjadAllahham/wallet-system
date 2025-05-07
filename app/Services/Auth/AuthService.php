<?php

namespace App\Services\Auth;

use Exception;
use Carbon\Carbon;
use App\Models\User;
use App\Mail\VerifyEmail;
use Illuminate\Support\Str;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use App\Services\Auth\AccountService;

class AuthService
{
    use ApiResponser;

    public function register($data)
    {
        $validator = Validator::make($data, [
            'first_name'      => 'required|string|max:255',
            'last_name'       => 'required|string|max:255',
            'email'           => 'required|string|email|max:255|unique:users,email',
            'password'        => 'required|string|min:6|max:255',
            'birth_date'      => 'required|date',
            'answer_security' => 'required|string|min:3|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json($this->errorResponse($validator->errors(), 400));
        }

        if (Carbon::parse($data['birth_date'])->age < 18) {
            return response()->json($this->errorResponse(['birth_date' => ['You must be 18 years or older.']], 422));
        }

        $verificationCode = Str::random(6);
        $cacheKey = 'user_registration_' . $data['email'];

        Cache::put($cacheKey, [
            'first_name'        => $data['first_name'],
            'last_name'         => $data['last_name'],
            'email'             => $data['email'],
            'password'          => Hash::make($data['password']),
            'birth_date'        => $data['birth_date'],
            'answer_security'   => $data['answer_security'],
            'verification_code' => $verificationCode,
            'ip_address'        => request()->ip(),
            'attempts'          => 0,
        ], now()->addMinutes(10));

        $this->sendVerificationEmail($data['email'], $verificationCode);

        return response()->json($this->successResponse('A verification code has been sent to your email.'));
    }

    public function verifyEmail($email, $verificationCode)
    {
        $cacheKey = 'user_registration_' . $email;
        $cachedUser = Cache::get($cacheKey);

        if (!$cachedUser || $cachedUser['verification_code'] !== $verificationCode || request()->ip() !== $cachedUser['ip_address']) {
            return response()->json($this->errorResponse('Invalid code or request.', 400));
        }

        if (User::where('email', $cachedUser['email'])->exists()) {
            return response()->json($this->errorResponse('User already exists.', 400));
        }

        $accountNumber = AccountService::generateUniqueAccountNumber();

        User::create([
            'first_name'        => $cachedUser['first_name'],
            'last_name'         => $cachedUser['last_name'],
            'email'             => $cachedUser['email'],
            'password'          => $cachedUser['password'],
            'email_verified_at' => now(),
            'ip_address'        => $cachedUser['ip_address'],
            'answer_security'   => $cachedUser['answer_security'],
            'birth_date'        => $cachedUser['birth_date'],
            'account_number'    => $accountNumber,
        ]);

        Cache::forget($cacheKey);

        return response()->json($this->successResponse('Email verified successfully.'));
    }

    public function login($data)
    {
        $validator = Validator::make($data, [
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json($this->errorResponse($validator->errors(), 400));
        }

        if (!Auth::guard('web')->attempt(['email' => $data['email'], 'password' => $data['password']])) {
            return response()->json($this->errorResponse('Invalid credentials.', 401));
        }

        $user = Auth::user();
        $accessToken = $user->createToken('access_token')->plainTextToken;
        $refreshToken = $user->createToken('refresh_token')->plainTextToken;

        return response()->json($this->successResponse('Login successful.', [
            'user'                 => $user,
            'access_token'         => $accessToken,
            'refresh_token'        => $refreshToken,
            'access_token_expiry'  => now()->addMinutes(10),
        ]));
    }

    public function logout()
    {
        try {
            Auth::user()->tokens()->delete();
            return response()->json($this->successResponse('Logged out successfully.'));
        } catch (Exception $e) {
            return response()->json($this->errorResponse($e->getMessage(), 500));
        }
    }

    public function refreshToken()
    {
        try {
            $user = Auth::user();
            $user->tokens()->delete();
            $newToken = $user->createToken('refresh_token')->plainTextToken;

            return response()->json($this->successResponse('Token refreshed successfully.', [
                'refresh_token'         => $newToken,
                'refresh_token_expiry'  => now()->addMinutes(20),
            ]));
        } catch (Exception $e) {
            return response()->json($this->errorResponse($e->getMessage(), 500));
        }
    }

    public function resendVerificationCode($email)
    {
        try {
            $cacheKey = 'user_registration_' . $email;
            $cachedUser = Cache::get($cacheKey);

            if (!$cachedUser || request()->ip() !== $cachedUser['ip_address']) {
                throw new Exception('Invalid request. Session expired or device changed.');
            }

            if ($cachedUser['attempts'] >= 2) {
                Cache::put($cacheKey, $cachedUser, now()->addMinutes(5));
                throw new Exception('Maximum attempts reached. Wait 5 minutes.');
            }

            $verificationCode = Str::random(6);
            $cachedUser['verification_code'] = $verificationCode;
            $cachedUser['attempts'] += 1;

            Cache::put($cacheKey, $cachedUser, now()->addMinutes(10));

            if (!$this->sendVerificationEmail($email, $verificationCode)) {
                throw new Exception('Failed to send verification code.');
            }

            return response()->json($this->successResponse('Verification code resent.'));
        } catch (Exception $e) {
            return response()->json($this->errorResponse($e->getMessage(), 400));
        }
    }

    private function sendVerificationEmail($email, $verificationCode)
    {
        try {
            Mail::to($email)->send(new VerifyEmail($verificationCode));
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
