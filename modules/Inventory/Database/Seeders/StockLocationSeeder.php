<?php

namespace Modules\Inventory\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Core\Database\Seeders\Concerns\ResolveTenantId;
use Modules\Inventory\Models\StockLocation;

class StockLocationSeeder extends Seeder
{
    use ResolveTenantId;

    protected $connection;
    protected ?string $tenantId = null;

    public function run(): void
    {
        // Resolve connection and tenant
        $this->connection = $this->resolveConnection();
        $this->tenantId = $this->resolveTenantId();

        if (!$this->tenantId) {
            Log::warning('StockLocationSeeder: No tenant ID found, skipping');
            return;
        }

        // Get all branches for this tenant
        $branches = $this->connection->table('branches')
            ->where('tenant_id', $this->tenantId)
            ->get();

        foreach ($branches as $branch) {
            $this->seedLocationsForBranch($branch->id);
        }
    }

    /**
     * Resolve the database connection to use.
     */
    protected function resolveConnection()
    {
        // Check if we're in a tenant context
        try {
            $result = DB::connection('tenant')->select('SHOW search_path');
            $searchPath = $result[0]->search_path ?? 'public';

            if (str_starts_with($searchPath, 'tenant_') || str_starts_with($searchPath, '"tenant_')) {
                return DB::connection('tenant');
            }
        } catch (\Exception $e) {
            // Ignore
        }

        // Default to pgsql connection (should have search_path set)
        return DB::connection('pgsql');
    }

    /**
     * Seed default stock location for a branch.
     */
    protected function seedLocationsForBranch(int|string $branchId): void
    {
        // Check if internal locations already exist for this branch
        $existingInternalCount = $this->connection->table('stock_locations')
            ->where('branch_id', $branchId)
            ->where('location_type', StockLocation::TYPE_INTERNAL)
            ->count();

        if ($existingInternalCount === 0) {
            // Create physical locations
            $this->seedPhysicalLocations($branchId);
        }

        // Always ensure virtual locations exist (Odoo-like)
        $this->seedVirtualLocations($branchId);

        Log::info("StockLocationSeeder: Completed seeding locations for branch {$branchId}");
    }

    /**
     * Seed physical (internal) locations for a branch.
     */
    protected function seedPhysicalLocations(int|string $branchId): void
    {
        $physicalLocations = [
            [
                'code' => 'WH/STOCK',
                'name' => ['en' => 'Stock', 'ar' => 'المخزون'],
                'location_type' => StockLocation::TYPE_INTERNAL,
                'sort_order' => 1,
            ],
            [
                'code' => 'WH/TREATMENT',
                'name' => ['en' => 'Treatment Room', 'ar' => 'غرفة العلاج'],
                'location_type' => StockLocation::TYPE_INTERNAL,
                'is_treatment_default' => true,
                'sort_order' => 2,
            ],
        ];

        foreach ($physicalLocations as $location) {
            $exists = $this->connection->table('stock_locations')
                ->where('branch_id', $branchId)
                ->where('code', $location['code'])
                ->exists();

            if (!$exists) {
                $this->connection->table('stock_locations')->insert([
                    'tenant_id' => $this->tenantId,
                    'branch_id' => $branchId,
                    'parent_id' => null,
                    'code' => $location['code'],
                    'name' => json_encode($location['name']),
                    'location_type' => $location['location_type'],
                    'parent_path' => null,
                    'level' => 0,
                    'is_scrap_location' => false,
                    'is_return_location' => false,
                    'is_treatment_default' => $location['is_treatment_default'] ?? false,
                    'is_active' => true,
                    'sort_order' => $location['sort_order'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Seed virtual locations for Odoo-like stock transfers.
     * These are required for the new transfer-based inventory model.
     */
    protected function seedVirtualLocations(int|string $branchId): void
    {
        $virtualLocations = [
            [
                'code' => 'Partner/Vendors',
                'name' => ['en' => 'Vendors', 'ar' => 'الموردين'],
                'location_type' => StockLocation::TYPE_SUPPLIER,
                'sort_order' => 100,
            ],
            [
                'code' => 'Partner/Customers',
                'name' => ['en' => 'Customers', 'ar' => 'العملاء'],
                'location_type' => StockLocation::TYPE_CUSTOMER,
                'sort_order' => 101,
            ],
            [
                'code' => 'Virtual/Adjustment',
                'name' => ['en' => 'Inventory Adjustment', 'ar' => 'تسوية المخزون'],
                'location_type' => StockLocation::TYPE_INVENTORY,
                'sort_order' => 102,
            ],
            [
                'code' => 'Virtual/Scrap',
                'name' => ['en' => 'Scrap', 'ar' => 'الهالك'],
                'location_type' => StockLocation::TYPE_INTERNAL,
                'is_scrap_location' => true,
                'sort_order' => 103,
            ],
        ];

        foreach ($virtualLocations as $location) {
            $exists = $this->connection->table('stock_locations')
                ->where('branch_id', $branchId)
                ->where('code', $location['code'])
                ->exists();

            if (!$exists) {
                $this->connection->table('stock_locations')->insert([
                    'tenant_id' => $this->tenantId,
                    'branch_id' => $branchId,
                    'parent_id' => null,
                    'code' => $location['code'],
                    'name' => json_encode($location['name']),
                    'location_type' => $location['location_type'],
                    'parent_path' => null,
                    'level' => 0,
                    'is_scrap_location' => $location['is_scrap_location'] ?? false,
                    'is_return_location' => false,
                    'is_treatment_default' => false,
                    'is_active' => true,
                    'sort_order' => $location['sort_order'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
