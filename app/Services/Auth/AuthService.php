<?php

namespace App\Services\Auth;

use Exception;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Currency;
use App\Mail\VerifyEmail;
use Illuminate\Support\Str;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Services\Auth\AccountService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;


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
            'ip_address'      => 'required|ip',
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
            'ip_address'        => $data['ip_address'],
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
        $isUserExisited = User::where('email', $cachedUser['email'])->exists();
        if ($isUserExisited) {
            return response()->json($this->errorResponse('User already exists.', 400));
        }

        $accountNumber = AccountService::generateUniqueAccountNumber();

        $user = User::create([
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

        // التحقق من المحافظ وإنشاؤها فقط إذا لم تكن موجودة
        $currencies = Currency::all();

        // don't make onnetion into loop plz
        foreach ($currencies as $currency) {
            $walletExists = Wallet::where('user_id', $user->id)
                ->where('currency_id', $currency->id)
                ->exists();

                
            if (!$walletExists) {
                Wallet::create([
                    'user_id'     => $user->id,
                    'currency_id' => $currency->id,
                    'balance'     => 1000,
                ]);
            }
        }

        Cache::forget($cacheKey);

        // جلب المحافظ مع العملة وتحويلها إلى شكل key=>value للعملة والرصيد
        $wallets = $user->wallets()->with('currency')->get();
        $walletBalances = [];
        foreach ($wallets as $wallet) {
            $walletBalances[$wallet->currency->code] = (float) $wallet->balance;
        }

        return response()->json($this->successResponse('Email verified and wallets initialized with balances.', [
            'wallets' => $walletBalances
        ]));
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
        /** @var User $user */
        $accessToken = $user->createToken('access_token')->plainTextToken;
        $refreshToken = $user->createToken('refresh_token')->plainTextToken;

        $wallets = Wallet::with('currency')
            ->where('user_id', $user->id)
            ->get()
            ->map(function ($wallet) {
                return [
                    'currency_name' => $wallet->currency->name,
                    'currency_code' => $wallet->currency->code,
                    'balance'       => $wallet->balance,
                ];
            });

        return response()->json($this->successResponse('Login successful.', [
            'user' => [
                'id'    => $user->id,
                'name'  => $user->first_name . ' ' . $user->last_name,
                'email' => $user->email,
                'is_admin' => (int) $user->is_admin,

            ],
            'wallets' => $wallets,
            'access_token'        => $accessToken,
            'refresh_token'       => $refreshToken,
            'access_token_expiry' => now()->addMinutes(10),
        ]));
    }

    public function logout()
    {
        try {
            /** @var User $user */
            $user = Auth::user();
            $user->tokens()->delete();
            // Auth::user()->tokens()->delete();
            return response()->json($this->successResponse('Logged out successfully.'));
        } catch (Exception $e) {
            return response()->json($this->errorResponse($e->getMessage(), 500));
        }
    }

    public function refreshToken()
    {
        try {
            /** @var User $user */
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
