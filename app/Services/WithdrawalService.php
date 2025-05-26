<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Models\Currency;
use App\Models\Withdrawal;
use Illuminate\Support\Str;
use App\Models\TransferCompany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class WithdrawalService
{
    public static function generateReceiptNumber(): string
    {
        do {
            $number = 'WDR-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
        } while (Withdrawal::where('receipt_number', $number)->exists());

        return $number;
    }

    public function createWithdrawalRequest($user, $data): Withdrawal
    {
        if (!isset($data['amount']) || $data['amount'] <= 0) {
            throw ValidationException::withMessages([
                'amount' => ['Withdrawal amount must be a positive number.'],
            ]);
        }

        if (!Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['Incorrect password.'],
            ]);
        }

        if (!isset($data['transfer_company_id'])) {
            throw ValidationException::withMessages([
                'transfer_company_id' => ['Transfer company is required.'],
            ]);
        }

        $transferCompany = TransferCompany::find($data['transfer_company_id']);
        if (!$transferCompany) {
            throw ValidationException::withMessages([
                'transfer_company_id' => ['Selected transfer company is invalid.'],
            ]);
        }

        $wallet = Wallet::where('user_id', $user->id)
            ->where('currency_id', $data['currency_id'])
            ->first();

        if (!$wallet) {
            throw ValidationException::withMessages([
                'wallet' => ['Wallet not found.'],
            ]);
        }

        if ($wallet->balance < $data['amount']) {
            throw ValidationException::withMessages([
                'amount' => ['Insufficient balance.'],
            ]);
        }

        try {
            $receiptNumber = self::generateReceiptNumber();

            return Withdrawal::create([
                'user_id'             => $user->id,
                'currency_id'         => $data['currency_id'],
                'amount'              => $data['amount'],
                'receipt_number'      => $receiptNumber,
                'status'              => 'pending',
                'full_name'           => $data['full_name'],
                'phone'               => $data['phone'],
                'location'            => $data['location'],
                'note'                => $data['note'] ?? null,
                'transfer_company_id' => $transferCompany->id,
            ]);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'error' => ['Unknown error occurred. Please try again later.'],
            ]);
        }
    }

    public function getWithdrawalById(int $id)
    {
        $withdrawal = Withdrawal::with(['user', 'currency', 'transferCompany'])->find($id);

        if (!$withdrawal) {
            throw ValidationException::withMessages([
                'withdrawal' => ['Withdrawal request not found.'],
            ]);
        }

        return $withdrawal;
    }

   public function approveWithdrawal(Withdrawal $withdrawal, $admin): array
{
    Log::info('approveWithdrawal started for withdrawal ID: ' . $withdrawal->id);

    try {
        DB::beginTransaction();

        $wallet = Wallet::where('user_id', $withdrawal->user_id)
            ->where('currency_id', $withdrawal->currency_id)
            ->lockForUpdate()
            ->first();

        if (!$wallet) {
            throw ValidationException::withMessages([
                'wallet' => ['User wallet not found.'],
            ]);
        }

        if ($wallet->balance < $withdrawal->amount) {
            throw ValidationException::withMessages([
                'amount' => ['User does not have sufficient balance.'],
            ]);
        }

        $balanceBefore = $wallet->balance;

        $wallet->decrement('balance', $withdrawal->amount);
        $wallet->refresh();

        $balanceAfter = $wallet->balance;

        // زيادة رصيد الأدمن
        $adminWallet = Wallet::firstOrCreate([
            'user_id'     => $admin->id,
            'currency_id' => $withdrawal->currency_id,
        ], [
            'balance' => 0
        ]);
        $adminWallet->increment('balance', $withdrawal->amount);

        $withdrawal->update([
            'status'   => 'approved',
            'admin_id' => $admin->id,
        ]);

        // إشعار للمستخدم
        $withdrawal->user->notify(new \App\Notifications\WithdrawalStatusNotification($withdrawal));

        DB::commit();

        return [
            'message'    => 'Withdrawal request approved successfully.',
            'withdrawal' => [
                'id'     => $withdrawal->id,
                'amount' => number_format($withdrawal->amount, 2),
                'status' => $withdrawal->status,
            ],
            'wallet_balance' => [
                'currency' => $wallet->currency->code,
                'balance'  => number_format($wallet->balance, 2),
            ],
        ];
    } catch (ValidationException $e) {
        DB::rollBack();
        throw $e;
    } catch (\Throwable $e) {
        DB::rollBack();
        Log::error('approveWithdrawal failed: ' . $e->getMessage());
        throw ValidationException::withMessages([
            'error' => ['Unknown error occurred. Please try again later.'],
        ]);
    }
}




    public function rejectWithdrawal(Withdrawal $withdrawal, $admin): array
    {
        try {
            $withdrawal->update([
                'status'   => 'rejected',
                'admin_id' => $admin->id,
            ]);

            $withdrawal->user->notify(new \App\Notifications\WithdrawalStatusNotification($withdrawal));

            $user = $withdrawal->user->load(['wallets.currency']);
            $withdrawal->load(['user', 'currency']);

            $wallets = $user->wallets->map(function ($wallet) {
                return [
                    'currency' => $wallet->currency->code,
                    'balance'  => number_format($wallet->balance, 2),
                ];
            });

            return [
                'message'    => 'Withdrawal request rejected successfully.',
                'withdrawal' => $withdrawal,
                'wallets'    => $wallets,
            ];
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'error' => ['Unknown error occurred. Please try again later.'],
            ]);
        }
    }



    public function manualWithdrawal(array $data)
    {
        try {
            $admin = auth()->user();

            // تحقق من صلاحية المستخدم (يجب أن يكون أدمن)
            if (!$admin || !$admin->is_admin) {
                return [
                    'error' => true,
                    'message' => 'Unauthorized: You do not have permission to perform this action',
                ];
            }

            // التحقق من صحة البيانات المدخلة
            $validator = Validator::make($data, [
                'account_number' => 'required|string|exists:users,account_number',
                'currency'       => 'required|string|exists:currencies,code',
                'amount'         => 'required|numeric|min:0.01',
                'note'           => 'nullable|string',
                'transfer_company_id' => 'nullable|integer|exists:transfer_companies,id', // إذا كنت تدعم شركات التحويل
            ], [
                'account_number.required' => 'Account number is required',
                'account_number.exists'   => 'User with this account number does not exist',
                'currency.required'       => 'Currency is required',
                'currency.exists'         => 'Invalid currency code',
                'amount.required'         => 'Amount is required',
                'amount.numeric'          => 'Amount must be a number',
                'amount.min'              => 'Amount must be greater than zero',
            ]);

            if ($validator->fails()) {
                return [
                    'error' => true,
                    'message' => $validator->errors()->first(),
                ];
            }

            DB::beginTransaction();

            // جلب المستخدم المستهدف
            $user = User::where('account_number', $data['account_number'])->first();

            // جلب العملة
            $currency = Currency::where('code', strtoupper($data['currency']))->first();

            // جلب المحفظة الخاصة بالمستخدم مع قفل السجل
            $userWallet = $user->wallets()
                ->where('currency_id', $currency->id)
                ->lockForUpdate()
                ->first();

            if (!$userWallet) {
                DB::rollBack();
                return [
                    'error' => true,
                    'message' => 'User wallet for the specified currency not found',
                ];
            }

            // التحقق من توفر رصيد كافٍ
            if ($userWallet->balance < $data['amount']) {
                DB::rollBack();
                return [
                    'error' => true,
                    'message' => 'Insufficient balance in user wallet',
                ];
            }

            $balanceBefore = $userWallet->balance;

            // خصم الرصيد
            $userWallet->balance -= $data['amount'];
            $userWallet->save();

            $balanceAfter = $userWallet->balance;

            // إنشاء رقم إيصال فريد بطول ثابت وأحرف كبيرة
            $receiptNumber = 'REC-' . strtoupper(Str::random(10));

            // تسجيل طلب السحب
            $withdrawal = Withdrawal::create([
                'user_id'            => $user->id,
                'admin_id'           => $admin->id,
                'currency_id'        => $currency->id,
                'amount'             => $data['amount'],
                'receipt_number'     => $receiptNumber,
                'status'             => 'approved',
                'type'               => 'manual',
                'full_name'          => $user->getFullNameAttribute(),
                'phone'              => $user->phone ?? '',
                'location'           => $user->address ?? '',
                'note'               => $data['note'] ?? null,
                'transfer_company_id' => $data['transfer_company_id'] ?? null,
            ]);

            // سجل العملية في سجل التاريخ HistoryService
            HistoryService::log(
                $user->id,            // ✅ user_id
                $userWallet->id,      // ✅ wallet_id (رقم صحيح)
                'withdrawal',         // ✅ type (كلمة)
                $currency->code,      // ✅ currency
                $data['amount'],      // ✅ amount
                $balanceBefore,       // ✅ balance_before
                $balanceAfter,        // ✅ balance_after
                'Manual withdrawal by admin' // ✅ note
            );



            Log::info('Manual withdrawal created with status: ' . $withdrawal->status);

            DB::commit();

            return [
                'error' => false,
                'message' => 'Manual withdrawal completed successfully',
                'receipt_number' => $receiptNumber,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Manual withdrawal error: ' . $e->getMessage());

            return [
                'error' => true,
                'message' => 'Unknown error occurred',
            ];
        }
    }
}
