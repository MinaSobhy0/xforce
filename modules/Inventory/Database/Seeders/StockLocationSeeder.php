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
     * Seed default locations for a branch.
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

        $locations = [
            // Warehouse (View - Container)
            [
                'code' => 'WH',
                'name' => ['en' => 'Warehouse', 'ar' => 'المستودع'],
                'location_type' => StockLocation::TYPE_VIEW,
                'parent_path' => null,
                'level' => 0,
                'sort_order' => 1,
                'children' => [
                    [
                        'code' => 'WH/STOCK',
                        'name' => ['en' => 'Stock', 'ar' => 'المخزون'],
                        'location_type' => StockLocation::TYPE_INTERNAL,
                        'sort_order' => 1,
                    ],
                    [
                        'code' => 'WH/INPUT',
                        'name' => ['en' => 'Receiving Zone', 'ar' => 'منطقة الاستلام'],
                        'location_type' => StockLocation::TYPE_INTERNAL,
                        'sort_order' => 2,
                    ],
                ],
            ],
            // Partners (View - Container for virtual locations)
            [
                'code' => 'PARTNERS',
                'name' => ['en' => 'Partners', 'ar' => 'الشركاء'],
                'location_type' => StockLocation::TYPE_VIEW,
                'parent_path' => null,
                'level' => 0,
                'sort_order' => 2,
                'children' => [
                    [
                        'code' => 'PARTNERS/SUPPLIERS',
                        'name' => ['en' => 'Suppliers', 'ar' => 'الموردين'],
                        'location_type' => StockLocation::TYPE_SUPPLIER,
                        'sort_order' => 1,
                    ],
                    [
                        'code' => 'PARTNERS/CUSTOMERS',
                        'name' => ['en' => 'Customers', 'ar' => 'العملاء'],
                        'location_type' => StockLocation::TYPE_CUSTOMER,
                        'sort_order' => 2,
                    ],
                ],
            ],
            // Virtual (View - Container for adjustment locations)
            [
                'code' => 'VIRTUAL',
                'name' => ['en' => 'Virtual Locations', 'ar' => 'المواقع الافتراضية'],
                'location_type' => StockLocation::TYPE_VIEW,
                'parent_path' => null,
                'level' => 0,
                'sort_order' => 3,
                'children' => [
                    [
                        'code' => 'VIRTUAL/INVENTORY',
                        'name' => ['en' => 'Inventory Adjustment', 'ar' => 'تسوية المخزون'],
                        'location_type' => StockLocation::TYPE_INVENTORY,
                        'sort_order' => 1,
                    ],
                    [
                        'code' => 'VIRTUAL/SCRAP',
                        'name' => ['en' => 'Scrap', 'ar' => 'الهالك'],
                        'location_type' => StockLocation::TYPE_INVENTORY,
                        'is_scrap_location' => true,
                        'sort_order' => 2,
                    ],
                ],
            ],
        ];

        foreach ($locations as $locationData) {
            $this->createLocation($branchId, $locationData);
        }

        Log::info("StockLocationSeeder: Created default locations for branch {$branchId}");
    }

    /**
     * Create a location and its children recursively.
     */
    protected function createLocation(int|string $branchId, array $data, ?int $parentId = null, ?string $parentPath = null): int
    {
        $children = $data['children'] ?? [];
        unset($data['children']);

        $insertData = [
            'tenant_id' => $this->tenantId,
            'branch_id' => $branchId,
            'parent_id' => $parentId,
            'code' => $data['code'],
            'name' => json_encode($data['name']),
            'location_type' => $data['location_type'],
            'parent_path' => $parentPath,
            'level' => $data['level'] ?? ($parentId ? 1 : 0),
            'is_scrap_location' => $data['is_scrap_location'] ?? false,
            'is_return_location' => $data['is_return_location'] ?? false,
            'is_active' => true,
            'sort_order' => $data['sort_order'] ?? 0,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $locationId = $this->connection->table('stock_locations')->insertGetId($insertData);

        // Create child locations
        $newParentPath = $parentPath ? $parentPath . '/' . $data['code'] : $data['code'];
        foreach ($children as $childData) {
            $childData['level'] = ($data['level'] ?? 0) + 1;
            $this->createLocation($branchId, $childData, $locationId, $newParentPath);
        }

        return $locationId;
    }
}
