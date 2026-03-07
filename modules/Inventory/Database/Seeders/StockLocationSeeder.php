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
        // Check if locations already exist for this branch
        $existingCount = $this->connection->table('stock_locations')
            ->where('branch_id', $branchId)
            ->count();

        if ($existingCount > 0) {
            Log::info("StockLocationSeeder: Locations already exist for branch {$branchId}, skipping");
            return;
        }

        // Create a single default stock location for the branch
        $this->connection->table('stock_locations')->insert([
            'tenant_id' => $this->tenantId,
            'branch_id' => $branchId,
            'parent_id' => null,
            'code' => 'WH/STOCK',
            'name' => json_encode(['en' => 'Stock', 'ar' => 'المخزون']),
            'location_type' => StockLocation::TYPE_INTERNAL,
            'parent_path' => null,
            'level' => 0,
            'is_scrap_location' => false,
            'is_return_location' => false,
            'is_active' => true,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Log::info("StockLocationSeeder: Created default stock location for branch {$branchId}");
    }
}
