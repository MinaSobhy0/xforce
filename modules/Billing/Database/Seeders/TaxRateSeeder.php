<?php

namespace Modules\Billing\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Billing\Models\TaxRate;

class TaxRateSeeder extends Seeder
{
    public function run(): void
    {
        $taxRates = [
            [
                'name' => ['en' => 'VAT', 'ar' => 'ضريبة القيمة المضافة'],
                'rate' => 14,
                'is_default' => true,
                'is_active' => true,
            ],
            [
                'name' => ['en' => 'No Tax', 'ar' => 'بدون ضريبة'],
                'rate' => 0,
                'is_default' => false,
                'is_active' => true,
            ],
        ];

        foreach ($taxRates as $taxRate) {
            TaxRate::firstOrCreate(
                ['rate' => $taxRate['rate']],
                $taxRate
            );
        }
    }
}
