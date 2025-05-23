<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Wallet;
use App\Models\Currency;
use App\Models\Deposit;
use Illuminate\Http\Request;
use App\Services\DepositService;

class AdminDepositController extends Controller
{
    public function store(Request $request)
    {
        try {
            // Validate request
            $data = $request->validate([
                'account_number' => 'required|string',
                'currency_code'  => 'required|string',
                'amount'         => 'required|numeric',
            ]);

            // Check for negative or zero amount
            if ($data['amount'] <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Deposit amount must be greater than zero.'
                ], 422);
            }

            // Check if user exists
            $user = User::where('account_number', $data['account_number'])->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'The provided account number does not exist.'
                ], 404);
            }

            // Check if currency exists
            $currency = Currency::where('code', $data['currency_code'])->first();

            if (!$currency) {
                return response()->json([
                    'success' => false,
                    'message' => 'The selected currency code is invalid or not supported.'
                ], 404);
            }

            // Create or retrieve wallet
            $wallet = Wallet::firstOrCreate([
                'user_id'     => $user->id,
                'currency_id' => $currency->id,
            ], [
                'balance' => 0
            ]);

            // Update wallet balance
            $wallet->balance += $data['amount'];
            $wallet->save();

            // Generate receipt number
            $receiptNumber = DepositService::generateReceiptNumber();

            // Create deposit record
            Deposit::create([
                'user_id'        => $user->id,
                'currency_id'    => $currency->id,
                'amount'         => $data['amount'],
                'receipt_number' => $receiptNumber,
                'admin_id'       => auth()->id(),
            ]);

            // Get updated wallet balances
            $allWallets = Wallet::with('currency')
                ->where('user_id', $user->id)
                ->get()
                ->map(function ($wallet) {
                    return [
                        'currency' => $wallet->currency->code,
                        'balance'  => $wallet->balance
                    ];
                });

            return response()->json([
                'success'        => true,
                'message'        => 'Deposit completed successfully.',
                'receipt_number' => $receiptNumber,
                'wallets'        => $allWallets,
                'user'           => [
                    'id'   => $user->id,
                    'name' => $user->name,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred. Please try again later.',
                // Uncomment the next line for debugging only (not in production):
                // 'error' => $e->getMessage()
            ], 500);
        }
    }
}
