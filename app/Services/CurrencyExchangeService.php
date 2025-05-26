<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\CurrencyExchange;
use App\Models\History;
use Illuminate\Support\Facades\DB;

class CurrencyExchangeService
{
    public function convert(User $user, string $fromCurrency, string $toCurrency, float $amount)
    {
        try {
            if ($amount <= 0) {
                return $this->error('Invalid amount. Amount must be greater than zero.');
            }

            $from = Currency::where('code', $fromCurrency)->first();
            $to = Currency::where('code', $toCurrency)->first();

            if (!$from) {
                return $this->error("Invalid currency: from_currency '{$fromCurrency}' not found.");
            }

            if (!$to) {
                return $this->error("Invalid currency: to_currency '{$toCurrency}' not found.");
            }

            $rate = ExchangeRate::where('from_currency', $fromCurrency)
                ->where('to_currency', $toCurrency)
                ->first();

            if (!$rate) {
                return $this->error('Exchange rate not found.');
            }

            $fromWallet = Wallet::where('user_id', $user->id)
                ->where('currency_id', $from->id)
                ->first();

            if (!$fromWallet) {
                return $this->error("Wallet for currency '{$fromCurrency}' not found.");
            }

            if ($fromWallet->balance < $amount) {
                return $this->error('Insufficient balance.');
            }

            DB::beginTransaction();

            $converted = round($amount * $rate->amount, 2);

            $balanceBeforeFrom = $fromWallet->balance;
            $fromWallet->decrement('balance', $amount);
            $balanceAfterFrom = $fromWallet->balance;

            $toWallet = Wallet::firstOrCreate([
                'user_id' => $user->id,
                'currency_id' => $to->id,
            ], ['balance' => 0]);

            $balanceBeforeTo = $toWallet->balance;
            $toWallet->increment('balance', $converted);
            $balanceAfterTo = $toWallet->balance;

            CurrencyExchange::create([
                'user_id' => $user->id,
                'from_currency' => $fromCurrency,
                'to_currency' => $toCurrency,
                'amount' => $amount,
                'converted_amount' => $converted,
                'rate' => $rate->amount,
            ]);

            // 🔸 حفظ العملية في سجل الأرشفة (history)
            History::create([
                'user_id' => $user->id,
                'wallet_id' => $fromWallet->id,
                'type' => 'currency_exchange_out',
                'currency' => $fromCurrency,
                'amount' => -1 * $amount,
                'balance_before' => $balanceBeforeFrom,
                'balance_after' => $balanceAfterFrom,
                'note' => "Exchanged to {$toCurrency} at rate {$rate->amount}",
            ]);

            History::create([
                'user_id' => $user->id,
                'wallet_id' => $toWallet->id,
                'type' => 'currency_exchange_in',
                'currency' => $toCurrency,
                'amount' => $converted,
                'balance_before' => $balanceBeforeTo,
                'balance_after' => $balanceAfterTo,
                'note' => "Exchanged from {$fromCurrency} at rate {$rate->amount}",
            ]);

            DB::commit();

            return [
                'error' => false,
                'message' => 'Currency converted successfully.',
                'converted_amount' => $converted,
                'rate' => $rate->amount
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Unknown error.');
        }
    }

    private function error(string $message): array
    {
        return [
            'error' => true,
            'message' => $message
        ];
    }
}
