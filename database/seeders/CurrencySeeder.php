<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    // database/seeders/CurrencySeeder.php
    public function run()
    {
        \App\Models\Currency::insert([
            ['name' => 'الليرة السورية', 'code' => 'SYP'],
            ['name' => 'الدولار الأمريكي', 'code' => 'USD'],
            ['name' => 'الليرة التركية', 'code' => 'TRY'],
        ]);
    }
}
