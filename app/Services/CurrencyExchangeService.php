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

            // ✅ محاولة إيجاد السعر المباشر أو حساب السعر المعكوس
            $rate = ExchangeRate::where('from_currency', $fromCurrency)
                ->where('to_currency', $toCurrency)
                ->first();

            if ($rate) {
                $rateAmount = $rate->amount;
            } else {
                // ✅ محاولة إيجاد السعر المعكوس
                $inverseRate = ExchangeRate::where('from_currency', $toCurrency)
                    ->where('to_currency', $fromCurrency)
                    ->first();

                if ($inverseRate && $inverseRate->amount > 0) {
                    $rateAmount = round(1 / $inverseRate->amount, 6); // ✅ حساب السعر المعكوس
                } else {
                    return $this->error('Exchange rate not found.');
                }
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

            $converted = round($amount * $rateAmount, 2);

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
                'rate' => $rateAmount,
            ]);

            // 🔸 تسجيل العملية في سجل الـ history
            History::create([
                'user_id' => $user->id,
                'wallet_id' => $fromWallet->id,
                'type' => 'currency_exchange_out',
                'currency' => $fromCurrency,
                'amount' => -1 * $amount,
                'balance_before' => $balanceBeforeFrom,
                'balance_after' => $balanceAfterFrom,
                'note' => "Exchanged to {$toCurrency} at rate {$rateAmount}",
            ]);

            History::create([
                'user_id' => $user->id,
                'wallet_id' => $toWallet->id,
                'type' => 'currency_exchange_in',
                'currency' => $toCurrency,
                'amount' => $converted,
                'balance_before' => $balanceBeforeTo,
                'balance_after' => $balanceAfterTo,
                'note' => "Exchanged from {$fromCurrency} at rate {$rateAmount}",
            ]);

            DB::commit();

            return [
                'error' => false,
                'message' => 'Currency converted successfully.',
                'converted_amount' => $converted,
                'rate' => $rateAmount
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
