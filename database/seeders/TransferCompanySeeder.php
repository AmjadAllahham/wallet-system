<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TransferCompany;

class TransferCompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $companies = ['Al-Haram', 'Al-Fouad', 'Western Union'];

        foreach ($companies as $name) {
            TransferCompany::firstOrCreate(['name' => $name]);
        }
    }
}
