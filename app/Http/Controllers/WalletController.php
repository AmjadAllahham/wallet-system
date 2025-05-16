<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WalletController extends Controller
{
    public function index()
    {
        $user = auth()->user(); // لا تستخدم User::auth()

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
