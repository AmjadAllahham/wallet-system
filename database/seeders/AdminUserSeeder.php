<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Currency;
use App\Services\Auth\AccountService;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admins = [
            [
                'first_name' => 'amjad',
                'last_name' => 'allahham',
                'email' => 'amjadallahham@gmail.com',
                'password' => Hash::make('1234567890'),
                'is_admin' => true,
                'email_verified_at' => now(),
                'birth_date' => '2000-01-01',
                'answer_security' => 'blue',
            ],
            [
                'first_name' => 'lelas',
                'last_name' => 'alasad',
                'email' => 'lelasalasad0@gmail.com',
                'password' => Hash::make('1234567890'),
                'is_admin' => true,
                'email_verified_at' => now(),
                'birth_date' => '2000-01-01',
                'answer_security' => 'blue',
            ],
            [
                'first_name' => 'nader',
                'last_name' => 'qas',
                'email' => 'nader.qas.crypto@gmail.com',
                'password' => Hash::make('1234567890'),
                'is_admin' => true,
                'email_verified_at' => now(),
                'birth_date' => '2000-01-01',
                'answer_security' => 'blue',
            ],
        ];

        foreach ($admins as $admin) {
            $admin['account_number'] = AccountService::generateUniqueAccountNumber();
            $user = User::updateOrCreate(['email' => $admin['email']], $admin);

            $currencyCodes = ['SYP', 'USD', 'TRY'];
            foreach ($currencyCodes as $code) {
                $currency = Currency::where('code', $code)->first();

                if ($currency) {
                    Wallet::firstOrCreate([
                        'user_id'     => $user->id,
                        'currency_id' => $currency->id,
                    ], [
                        'balance' => 10000.00
                    ]);
                }
            }
        }
    }
}
