<?php

namespace Modules\Inventory\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Database\Seeders\Concerns\ResolveTenantId;
use Modules\Inventory\Models\Uom;

class UomSeeder extends Seeder
{
    use ResolveTenantId;

    protected $connection;
    protected ?string $tenantId = null;
    protected bool $usesUuid = true;

    public function run(): void
    {
        // Resolve connection and tenant
        $this->connection = $this->resolveConnection();
        $this->tenantId = $this->resolveTenantId();

        if (!$this->tenantId) {
            return;
        }

        // Detect if using UUID or bigint for IDs
        $this->detectIdType();

        $this->seedCategories();
    }

    /**
     * Detect whether the schema uses UUID or bigint for IDs.
     */
    protected function detectIdType(): void
    {
        try {
            // Get the current schema
            $searchPathResult = $this->connection->select('SHOW search_path');
            $searchPath = $searchPathResult[0]->search_path ?? 'public';

            // Extract schema name (remove quotes if present)
            $schemaName = trim(explode(',', $searchPath)[0], '" ');

            // Check the data type for uom_categories.id in this schema
            $result = $this->connection->select("
                SELECT data_type
                FROM information_schema.columns
                WHERE table_schema = ?
                AND table_name = 'uom_categories'
                AND column_name = 'id'
            ", [$schemaName]);

            $this->usesUuid = !empty($result) && $result[0]->data_type === 'uuid';
        } catch (\Exception $e) {
            $this->usesUuid = true; // Default to UUID
        }
    }

    /**
     * Generate an ID appropriate for the schema.
     */
    protected function generateId()
    {
        return $this->usesUuid ? Str::uuid()->toString() : null;
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

    protected function seedCategories(): void
    {
        $categories = [
            [
                'name' => ['en' => 'Unit', 'ar' => 'وحدة'],
                'description' => ['en' => 'Countable units', 'ar' => 'وحدات قابلة للعد'],
                'sort_order' => 1,
                'units' => [
                    [
                        'name' => ['en' => 'Piece', 'ar' => 'قطعة'],
                        'abbreviation' => 'pcs',
                        'uom_type' => Uom::TYPE_REFERENCE,
                        'ratio' => 1,
                        'is_reference' => true,
                    ],
                    [
                        'name' => ['en' => 'Dozen', 'ar' => 'درزن'],
                        'abbreviation' => 'doz',
                        'uom_type' => Uom::TYPE_BIGGER,
                        'ratio' => 12,
                        'is_reference' => false,
                    ],
                    [
                        'name' => ['en' => 'Box (6)', 'ar' => 'علبة (6)'],
                        'abbreviation' => 'box6',
                        'uom_type' => Uom::TYPE_BIGGER,
                        'ratio' => 6,
                        'is_reference' => false,
                    ],
                    [
                        'name' => ['en' => 'Box (12)', 'ar' => 'علبة (12)'],
                        'abbreviation' => 'box12',
                        'uom_type' => Uom::TYPE_BIGGER,
                        'ratio' => 12,
                        'is_reference' => false,
                    ],
                ],
            ],
            [
                'name' => ['en' => 'Weight', 'ar' => 'الوزن'],
                'description' => ['en' => 'Weight measurements', 'ar' => 'قياسات الوزن'],
                'sort_order' => 2,
                'units' => [
                    [
                        'name' => ['en' => 'Kilogram', 'ar' => 'كيلوغرام'],
                        'abbreviation' => 'kg',
                        'uom_type' => Uom::TYPE_REFERENCE,
                        'ratio' => 1,
                        'is_reference' => true,
                    ],
                    [
                        'name' => ['en' => 'Gram', 'ar' => 'غرام'],
                        'abbreviation' => 'g',
                        'uom_type' => Uom::TYPE_SMALLER,
                        'ratio' => 0.001,
                        'is_reference' => false,
                    ],
                    [
                        'name' => ['en' => 'Milligram', 'ar' => 'ميليغرام'],
                        'abbreviation' => 'mg',
                        'uom_type' => Uom::TYPE_SMALLER,
                        'ratio' => 0.000001,
                        'is_reference' => false,
                    ],
                ],
            ],
            [
                'name' => ['en' => 'Volume', 'ar' => 'الحجم'],
                'description' => ['en' => 'Volume measurements', 'ar' => 'قياسات الحجم'],
                'sort_order' => 3,
                'units' => [
                    [
                        'name' => ['en' => 'Liter', 'ar' => 'لتر'],
                        'abbreviation' => 'l',
                        'uom_type' => Uom::TYPE_REFERENCE,
                        'ratio' => 1,
                        'is_reference' => true,
                    ],
                    [
                        'name' => ['en' => 'Milliliter', 'ar' => 'ميليلتر'],
                        'abbreviation' => 'ml',
                        'uom_type' => Uom::TYPE_SMALLER,
                        'ratio' => 0.001,
                        'is_reference' => false,
                    ],
                    [
                        'name' => ['en' => 'Centiliter', 'ar' => 'سنتيلتر'],
                        'abbreviation' => 'cl',
                        'uom_type' => Uom::TYPE_SMALLER,
                        'ratio' => 0.01,
                        'is_reference' => false,
                    ],
                ],
            ],
            [
                'name' => ['en' => 'Length', 'ar' => 'الطول'],
                'description' => ['en' => 'Length measurements', 'ar' => 'قياسات الطول'],
                'sort_order' => 4,
                'units' => [
                    [
                        'name' => ['en' => 'Meter', 'ar' => 'متر'],
                        'abbreviation' => 'm',
                        'uom_type' => Uom::TYPE_REFERENCE,
                        'ratio' => 1,
                        'is_reference' => true,
                    ],
                    [
                        'name' => ['en' => 'Centimeter', 'ar' => 'سنتيمتر'],
                        'abbreviation' => 'cm',
                        'uom_type' => Uom::TYPE_SMALLER,
                        'ratio' => 0.01,
                        'is_reference' => false,
                    ],
                    [
                        'name' => ['en' => 'Millimeter', 'ar' => 'ميليمتر'],
                        'abbreviation' => 'mm',
                        'uom_type' => Uom::TYPE_SMALLER,
                        'ratio' => 0.001,
                        'is_reference' => false,
                    ],
                ],
            ],
        ];

        foreach ($categories as $categoryData) {
            $units = $categoryData['units'];
            unset($categoryData['units']);

            // Check if category already exists
            $existingCategory = $this->connection->table('uom_categories')
                ->where('tenant_id', $this->tenantId)
                ->whereRaw("name->>'en' = ?", [$categoryData['name']['en']])
                ->first();

            if ($existingCategory) {
                $categoryId = $existingCategory->id;
            } else {
                $insertData = [
                    'tenant_id' => $this->tenantId,
                    'name' => json_encode($categoryData['name']),
                    'description' => json_encode($categoryData['description']),
                    'is_active' => true,
                    'sort_order' => $categoryData['sort_order'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if ($this->usesUuid) {
                    $categoryId = Str::uuid()->toString();
                    $insertData['id'] = $categoryId;
                    $this->connection->table('uom_categories')->insert($insertData);
                } else {
                    $categoryId = $this->connection->table('uom_categories')->insertGetId($insertData);
                }
            }

            foreach ($units as $unitData) {
                // Check if unit already exists
                $existingUnit = $this->connection->table('uoms')
                    ->where('tenant_id', $this->tenantId)
                    ->where('abbreviation', $unitData['abbreviation'])
                    ->first();

                if (!$existingUnit) {
                    $insertData = [
                        'tenant_id' => $this->tenantId,
                        'category_id' => $categoryId,
                        'name' => json_encode($unitData['name']),
                        'abbreviation' => $unitData['abbreviation'],
                        'uom_type' => $unitData['uom_type'],
                        'ratio' => $unitData['ratio'],
                        'is_reference' => $unitData['is_reference'],
                        'is_active' => true,
                        'rounding_precision' => 0.01,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    if ($this->usesUuid) {
                        $insertData['id'] = Str::uuid()->toString();
                    }

                    $this->connection->table('uoms')->insert($insertData);
                }
            }
        }
    }
}
