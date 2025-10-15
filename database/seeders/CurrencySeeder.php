<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Currency::factory()->createMany([
            [
                'code' => 'USD',
            ],
            [
                'code' => 'EUR',
                'rate' => 0.8612,
            ],
            [
                'code' => 'HKD',
                'rate' => 0.1286,
            ],
            [
                'code' => 'RMB',
                'rate' => 0.1401,
            ],
            [
                'code' => 'JPY',
                'rate' => 0.006589,
            ],
        ]);
    }
}
