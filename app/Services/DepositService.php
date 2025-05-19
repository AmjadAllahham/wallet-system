<?php

namespace App\Services;

use App\Models\Deposit;
use Illuminate\Support\Str;

class DepositService
{
    public static function generateReceiptNumber(): string
    {
        do {
            $number = 'DEP-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
        } while (Deposit::where('receipt_number', $number)->exists());

        return $number;
    }
}
