<?php

namespace App\Services\Admin;

use App\Models\User;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Hash;
use App\Services\Auth\AccountService;
use Illuminate\Support\Facades\Validator;

class UserService
{
    use ApiResponser;

    public function walletBalances()
    {
        $users = User::with('wallets.currency')->get();

        if ($users->isEmpty()) {
            return $this->errorResponse('No users found', null, 404);
        }

        $data = $users->map(function ($user) {
            return [
                'user_id'          => $user->id,
                'account_number'   => $user->account_number,
                'first_name'       => $user->first_name,
                'last_name'        => $user->last_name,
                'email'            => $user->email,
                'email_verified'   => (bool) $user->email_verified_at,
                'birth_date'       => $user->birth_date,
                'job'              => $user->job,
                'phone'            => $user->phone,
                'address'          => $user->address,
                'is_admin'         => (bool) $user->is_admin,
                'created_at'       => $user->created_at->toDateTimeString(),
                'updated_at'       => $user->updated_at->toDateTimeString(),
                'wallets' => $user->wallets->map(function ($wallet) {
                    return [
                        'currency_name' => $wallet->currency->name, // 👈 هنا الاسم
                        'currency_code' => $wallet->currency->code, // أو الكود مثل USD
                        'balance'       => $wallet->balance,
                    ];
                }),
            ];
        });

        return $this->successResponse('Users with wallets retrieved successfully', $data, 200);
    }


    public function show($id)
    {
        $user = User::find($id);
        if (!$user) {
            return $this->errorResponse('User not found', null, 404);
        }
        return $this->successResponse('User found successfully', $user, 200);
    }

    public function countUsers()
    {
        $count = User::count();
        return $this->successResponse('Users counted successfully', ['count' => $count], 200);
    }

    public function create($data)
    {
        $validator = Validator::make($data, [
            'first_name' => 'required|string|min:3|max:25',
            'last_name' => 'required|string|min:3|max:25',
            'email' => 'required|email|min:3|max:25|string|unique:users,email',
            'password' => 'required|string|min:6|max:32|confirmed',
            'answer_security' => 'required|string|min:3|max:32',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors(), 400);
        }

        // Generate unique account number
        $accountNumber = AccountService::generateUniqueAccountNumber();

        // Create user with the new account_number field
        $user = User::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'account_number' => $accountNumber, // New field added
            'answer_security' => $data['answer_security'] ?? null, // Optional field
        ]);

        return $this->successResponse('User created successfully', $user, 201);
    }

    public function update($data, $id)
    {
        $user = User::find($id);
        if (!$user) {
            return $this->errorResponse('User not found', null, 404);
        }

        $validator = Validator::make($data, [
            'first_name'       => 'sometimes|string|min:3|max:25',
            'last_name'        => 'sometimes|string|min:3|max:25',
            'password'         => 'sometimes|string|min:6|max:32|confirmed',
            'birth_date'       => 'sometimes|date',
            'answer_security'  => 'sometimes|string|min:3|max:25',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors(), 400);
        }

        $user->update([
            'first_name'      => $data['first_name'] ?? $user->first_name,
            'last_name'       => $data['last_name'] ?? $user->last_name,
            'birth_date'      => $data['birth_date'] ?? $user->birth_date,
            'password'        => isset($data['password']) ? Hash::make($data['password']) : $user->password,
            'answer_security' => $data['answer_security'] ?? $user->answer_security,
        ]);

        return $this->successResponse('User updated successfully', $user, 200);
    }

    public function destroy($id)
    {
        $user = User::find($id);
        if (!$user) {
            return $this->errorResponse('User not found', null, 404);
        }

        $user->delete();
        return $this->successResponse('User deleted successfully', null, 200);
    }

    public function getWalletBalances(?User $user)
    {
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'data' => []
            ], 401);
        }

        $wallets = $user->wallets()->with('currency')->get()->map(function ($wallet) {
            return [
                'currency' => $wallet->currency->code,
                'balance' => (float) $wallet->balance,  // cast to float for cleaner json
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Wallet balances retrieved successfully.',
            'data' => $wallets
        ]);
    }
}
