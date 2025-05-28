<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ExchangeRate;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class UpdateExchangeRates extends Command
{
    protected $signature = 'exchange:update';
    protected $description = 'Fetch latest currency exchange rates and store all combinations';

    public function handle()
    {
        try {
            $base = 'USD';
            $currencies = ['USD', 'SYP', 'TRY'];

            // جلب أسعار التحويل من USD إلى باقي العملات
            $response = Http::get("https://v6.exchangerate-api.com/v6/f8d438f6c76c09554cbd607f/latest/{$base}");

            if ($response->failed()) {
                $this->error('Failed to fetch exchange rates.');
                return 1;
            }

            $rates = $response->json()['conversion_rates']; // [ 'USD' => 1, 'SYP' => xxx, 'TRY' => xxx ]

            $combinations = [];

            // حساب كل التحويلات بين العملات (من إلى) وليس فقط من USD
            foreach ($currencies as $from) {
                foreach ($currencies as $to) {
                    if ($from === $to) continue;

                    // تحويل مباشر من USD
                    if ($from === $base && isset($rates[$to])) {
                        $amount = $rates[$to];
                    }
                    // تحويل مباشر إلى USD
                    elseif ($to === $base && isset($rates[$from])) {
                        $amount = 1 / $rates[$from];
                    }
                    // تحويل بين عملتين عبر USD (مثل SYP → TRY)
                    elseif (isset($rates[$from], $rates[$to])) {
                        $amount = $rates[$to] / $rates[$from];
                    } else {
                        continue; // تجاهل إذا كانت البيانات ناقصة
                    }

                    // تخزين أو تحديث سعر التحويل
                    ExchangeRate::updateOrCreate(
                        ['from_currency' => $from, 'to_currency' => $to],
                        ['amount' => round($amount, 8), 'updated_at' => Carbon::now()]
                    );
                }
            }

            $this->info('All currency exchange rates updated successfully.');
        } catch (\Exception $e) {
            $this->error('Error updating exchange rates: ' . $e->getMessage());
            return 1;
        }
    }
}



//            $response = Http::get("https://v6.exchangerate-api.com/v6/f8d438f6c76c09554cbd607f/latest/{$base}");
