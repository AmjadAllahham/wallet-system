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
        $data = $request->validate([
            'account_number'   => 'required|string|exists:users,account_number',
            'currency_code'    => 'required|string|exists:currencies,code',
            'amount'           => 'required|numeric|min:0.01',
        ]);

        $user = User::where('account_number', $data['account_number'])->first();
        $currency = Currency::where('code', $data['currency_code'])->first();

        if (!$user || !$currency) {
            return response()->json(['success' => false, 'message' => 'User or currency not found.'], 404);
        }

        $wallet = Wallet::firstOrCreate([
            'user_id'     => $user->id,
            'currency_id' => $currency->id,
        ], [
            'balance' => 0
        ]);

        // تحديث الرصيد
        $wallet->balance += $data['amount'];
        $wallet->save();

        // توليد رقم الإيصال تلقائيًا
        $receiptNumber = DepositService::generateReceiptNumber();

        // تسجيل الإيداع
        Deposit::create([
            'user_id'        => $user->id,
            'currency_id'    => $currency->id,
            'amount'         => $data['amount'],
            'receipt_number' => $receiptNumber,
            'admin_id'       => auth()->id(), // الآدمن الحالي
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Deposit completed successfully.',
            'wallet_balance' => $wallet->balance,
            'receipt_number' => $receiptNumber,
        ]);
    }
}
