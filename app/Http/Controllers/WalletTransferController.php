<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\WalletTransferService;

class WalletTransferController extends Controller
{
    public function __construct(private WalletTransferService $service) {}

    public function crosswalletexchange(Request $request)
    {
        $request->validate([
            'account_number' => 'required|string',
            'currency'       => 'required|string',
            'amount'         => 'required|numeric|min:0.01',
            'note'           => 'nullable|string',
        ]);

        return $this->service->crosswalletexchange($request->all(), auth()->user());
    }
}
