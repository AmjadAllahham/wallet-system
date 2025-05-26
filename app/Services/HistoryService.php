<?php

namespace App\Services;

use App\Models\History;
use Illuminate\Support\Facades\Log;

class HistoryService
{
    public static function log($userId, $walletId, $type, $currency, $amount, $balanceBefore, $balanceAfter, $note = null)
    {
        try {
            Log::info('Trying to create history record...', [
                'user_id' => $userId,
                'wallet_id' => $walletId,
                'type' => $type,
                'currency' => $currency,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'note' => $note,
            ]);

            return History::create([
                'user_id' => $userId,
                'wallet_id' => $walletId,
                'type' => $type,
                'currency' => $currency,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'note' => $note,
            ]);
        } catch (\Exception $e) {
            Log::error('HistoryService log error: ' . $e->getMessage());
            return null;
        }
    }
}
