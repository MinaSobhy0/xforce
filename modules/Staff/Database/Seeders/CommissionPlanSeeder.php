<?php

namespace Modules\Staff\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CommissionPlanSeeder extends Seeder
{
    protected $connection;
    protected ?string $tenantId = null;

    public function run(): void
    {
        $this->connection = $this->resolveConnection();
        $this->tenantId = $this->resolveTenantId();

        $plans = [
            [
                'name' => 'Standard 10%',
                'description' => 'Standard commission plan with 10% on all services',
                'commission_type' => 'percentage',
                'default_percentage' => 10.00,
                'default_flat_amount_minor' => 0,
            ],
            [
                'name' => 'Premium 15%',
                'description' => 'Premium commission plan for senior staff',
                'commission_type' => 'percentage',
                'default_percentage' => 15.00,
                'default_flat_amount_minor' => 0,
            ],
            [
                'name' => 'Entry Level 5%',
                'description' => 'Entry level commission for new staff',
                'commission_type' => 'percentage',
                'default_percentage' => 5.00,
                'default_flat_amount_minor' => 0,
            ],
        ];

        foreach ($plans as $plan) {
            // Check if exists
            $exists = $this->connection->table('commission_plans')
                ->where('name', $plan['name'])
                ->where('tenant_id', $this->tenantId)
                ->exists();

            if ($exists) {
                continue;
            }

            $this->connection->table('commission_plans')->insert([
                'id' => Str::orderedUuid()->toString(),
                'tenant_id' => $this->tenantId,
                'name' => $plan['name'],
                'description' => $plan['description'],
                'commission_type' => $plan['commission_type'],
                'default_percentage' => $plan['default_percentage'],
                'default_flat_amount_minor' => $plan['default_flat_amount_minor'],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Resolve the database connection to use.
     */
    protected function resolveConnection()
    {
        try {
            $result = DB::connection('tenant')->select('SHOW search_path');
            $searchPath = $result[0]->search_path ?? 'public';

            if (str_starts_with($searchPath, 'tenant_') || str_starts_with($searchPath, '"tenant_')) {
                return DB::connection('tenant');
            }
        } catch (\Exception $e) {
            // Ignore
        }

        return DB::connection('tenant');
    }

    /**
     * Resolve the tenant ID from various sources.
     */
    protected function resolveTenantId(): ?string
    {
        try {
            $tenantManager = app(\XLinic\Framework\Core\Tenancy\TenantManager::class);
            if ($tenantManager->current()) {
                return $tenantManager->current()->id;
            }
        } catch (\Exception $e) {
            // Ignore
        }

        try {
            $result = DB::connection('tenant')->select('SHOW search_path');
            $searchPath = $result[0]->search_path ?? 'public';

            if (preg_match('/tenant[_-]([^,\s"]+)/', $searchPath, $matches)) {
                $slug = str_replace('_', '-', $matches[1]);

                $tenant = DB::connection('pgsql')
                    ->table('tenants')
                    ->where('slug', $slug)
                    ->orWhere('slug', $matches[1])
                    ->first();

                if ($tenant) {
                    return $tenant->id;
                }
            }
        } catch (\Exception $e) {
            // Ignore
        }

        return null;
    }
}
