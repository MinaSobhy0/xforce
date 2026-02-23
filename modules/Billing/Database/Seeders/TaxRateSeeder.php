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

        $taxRates = [
            [
                'tenant_id' => $tenantId,
                'name' => ['en' => 'VAT', 'ar' => 'ضريبة القيمة المضافة'],
                'rate' => 14,
                'is_default' => true,
                'is_active' => true,
            ],
            [
                'tenant_id' => $tenantId,
                'name' => ['en' => 'No Tax', 'ar' => 'بدون ضريبة'],
                'rate' => 0,
                'is_default' => false,
                'is_active' => true,
            ],
        ];

        foreach ($taxRates as $taxRate) {
            // Use direct DB insert to bypass any model issues
            DB::table('tax_rates')->updateOrInsert(
                ['tenant_id' => $tenantId, 'rate' => $taxRate['rate']],
                array_merge($taxRate, [
                    'name' => json_encode($taxRate['name']),
                    'id' => $taxRate['id'] ?? \Illuminate\Support\Str::orderedUuid()->toString(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
