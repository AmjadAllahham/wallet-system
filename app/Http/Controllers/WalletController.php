<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function index()
    {
        $user = auth()->user(); // المستخدم الحالي المسجل دخوله
        /** @var User $user */
        $wallets = $user->wallets()->with('currency')->get()->map(function ($wallet) {
            return [
                'currency_name' => $wallet->currency->name,
                'currency_code' => $wallet->currency->code,
                'balance'       => $wallet->balance,
            ];
        });

        return response()->json([
            'status'  => true,
            'wallets' => $wallets
        ]);
    }
}
