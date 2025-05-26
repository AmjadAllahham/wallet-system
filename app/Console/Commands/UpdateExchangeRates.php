<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ExchangeRate;
use Illuminate\Support\Facades\Http;

class UpdateExchangeRates extends Command
{
    protected $signature = 'exchange:update';
    protected $description = 'Fetch latest currency exchange rates and store them';

    public function handle()
    {
        try {
            $base = 'USD';
            $currencies = ['USD', 'SYP', 'TRY'];

            $response = Http::get("https://v6.exchangerate-api.com/v6/f8d438f6c76c09554cbd607f/latest/{$base}");

            if ($response->failed()) {
                $this->error('Failed to fetch exchange rates.');
                return 1;
            }

            $rates = $response->json()['conversion_rates']; // المفتاح الصحيح

            foreach ($currencies as $target) {
                if ($target == $base) continue;

                if (!isset($rates[$target])) continue;

                ExchangeRate::updateOrCreate(
                    ['from_currency' => $base, 'to_currency' => $target],
                    ['amount' => $rates[$target]]
                );
            }

            $this->info('Exchange rates updated successfully.');
        } catch (\Exception $e) {
            $this->error('Error updating rates: ' . $e->getMessage());
            return 1;
        }
    }
}
