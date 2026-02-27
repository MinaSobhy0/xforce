<?php

namespace Modules\Billing\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Models\TaxRate;

class TaxRateSeeder extends Seeder
{
    public function run(): void
    {
        // Get tenant from various sources
        $tenant = app('currentTenant') ?? null;

        // Try TenantManager if not found
        if (!$tenant) {
            try {
                $tenant = app(\XLinic\Framework\Core\Tenancy\TenantManager::class)->current();
            } catch (\Exception $e) {
                // Ignore
            }
        }

        $tenantId = $tenant?->id;

        if (!$tenantId) {
            $this->command?->warn('No tenant context found, skipping TaxRateSeeder');
            return;
        }

        // Define tax rates for both sales and purchase types
        $taxRateDefinitions = [
            // VAT rates (positive)
            ['name' => ['en' => 'VAT 14%', 'ar' => 'ضريبة القيمة المضافة 14%'], 'rate' => 14, 'is_default' => true],
            ['name' => ['en' => 'VAT 10%', 'ar' => 'ضريبة القيمة المضافة 10%'], 'rate' => 10, 'is_default' => false],
            ['name' => ['en' => 'No Tax', 'ar' => 'بدون ضريبة'], 'rate' => 0, 'is_default' => false],
            // Withholding rates (negative)
            ['name' => ['en' => 'WH 1%', 'ar' => 'خصم من المنبع 1%'], 'rate' => -1, 'is_default' => false],
            ['name' => ['en' => 'WH 3%', 'ar' => 'خصم من المنبع 3%'], 'rate' => -3, 'is_default' => false],
            ['name' => ['en' => 'WH 5%', 'ar' => 'خصم من المنبع 5%'], 'rate' => -5, 'is_default' => false],
        ];

        $types = [TaxRate::TYPE_SALES, TaxRate::TYPE_PURCHASE];

        foreach ($types as $type) {
            foreach ($taxRateDefinitions as $definition) {
                DB::table('tax_rates')->updateOrInsert(
                    [
                        'tenant_id' => $tenantId,
                        'rate' => $definition['rate'],
                        'type' => $type,
                    ],
                    [
                        'tenant_id' => $tenantId,
                        'name' => json_encode($definition['name']),
                        'rate' => $definition['rate'],
                        'type' => $type,
                        'is_default' => $definition['is_default'],
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }
}
