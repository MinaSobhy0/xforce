<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Update foreign key constraints to use RESTRICT ON DELETE instead of CASCADE.
 *
 * This implements Odoo-style deletion protection: the database blocks deletions
 * when related records exist, and a global exception handler shows user-friendly
 * error messages. This approach:
 * - Requires zero configuration per model
 * - Ensures database-level data integrity
 * - Provides consistent UX across all resources
 *
 * @see \App\Exceptions\ForeignKeyViolationHandler
 */
return new class extends Migration
{
    /**
     * FK constraints to convert to restrictOnDelete.
     * Format: ['table' => ['column' => 'parent_table']]
     */
    protected array $constraints = [
        // Patient-related constraints (critical - many references)
        'appointments' => [
            'patient_id' => 'patients',
            'service_id' => 'services',
        ],
        'invoices' => [
            'patient_id' => 'patients',
        ],
        'treatment_plans' => [
            'patient_id' => 'patients',
        ],
        'package_subscriptions' => [
            'patient_id' => 'patients',
            'package_id' => 'packages',
        ],

        // Service-related constraints
        'invoice_lines' => [
            'service_id' => 'services',
            'product_id' => 'products',
        ],
        'treatment_plan_items' => [
            'service_id' => 'services',
        ],
        'package_items' => [
            'service_id' => 'services',
        ],

        // Product-related constraints
        'stock_movements' => [
            'product_id' => 'products',
        ],
        'purchase_order_lines' => [
            'product_id' => 'products',
        ],

        // Staff-related constraints
        'payslips' => [
            'staff_profile_id' => 'staff_profiles',
        ],
        'practitioner_schedules' => [
            'practitioner_id' => 'staff_profiles',
        ],
        'practitioner_time_off' => [
            'practitioner_id' => 'staff_profiles',
        ],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Step 1: Pre-check for orphaned records
        $issues = $this->checkDataIntegrity();

        if (! empty($issues)) {
            foreach ($issues as $issue) {
                echo "  - {$issue}\n";
            }
            throw new \Exception('Data integrity issues found. Please fix orphaned records before running this migration. See output above.');
        }

        echo "Data integrity check passed. Updating foreign key constraints...\n";

        // Step 2: Update constraints using raw SQL (more reliable with PostgreSQL)
        foreach ($this->constraints as $table => $columns) {
            if (! Schema::hasTable($table)) {
                echo "  Skipped: Table {$table} does not exist\n";

                continue;
            }

            foreach ($columns as $column => $parentTable) {
                if (! Schema::hasColumn($table, $column)) {
                    echo "  Skipped: Column {$table}.{$column} does not exist\n";

                    continue;
                }

                if (! Schema::hasTable($parentTable)) {
                    echo "  Skipped: Parent table {$parentTable} does not exist\n";

                    continue;
                }

                $constraintName = $table.'_'.$column.'_foreign';

                // Drop existing constraint if it exists
                DB::statement("ALTER TABLE \"{$table}\" DROP CONSTRAINT IF EXISTS \"{$constraintName}\"");

                // Create new constraint with RESTRICT
                DB::statement("
                    ALTER TABLE \"{$table}\"
                    ADD CONSTRAINT \"{$constraintName}\"
                    FOREIGN KEY (\"{$column}\")
                    REFERENCES \"{$parentTable}\" (\"id\")
                    ON DELETE RESTRICT
                ");

                echo "  Updated: {$table}.{$column}\n";
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to cascadeOnDelete using raw SQL
        foreach ($this->constraints as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column => $parentTable) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                if (! Schema::hasTable($parentTable)) {
                    continue;
                }

                $constraintName = $table.'_'.$column.'_foreign';

                // Drop existing constraint if it exists
                DB::statement("ALTER TABLE \"{$table}\" DROP CONSTRAINT IF EXISTS \"{$constraintName}\"");

                // Recreate with CASCADE
                DB::statement("
                    ALTER TABLE \"{$table}\"
                    ADD CONSTRAINT \"{$constraintName}\"
                    FOREIGN KEY (\"{$column}\")
                    REFERENCES \"{$parentTable}\" (\"id\")
                    ON DELETE CASCADE
                ");
            }
        }
    }

    /**
     * Check for orphaned records that would cause constraint creation to fail.
     */
    protected function checkDataIntegrity(): array
    {
        $issues = [];

        foreach ($this->constraints as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column => $parentTable) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                if (! Schema::hasTable($parentTable)) {
                    continue;
                }

                // Check for orphaned records (non-null FK pointing to non-existent parent)
                $orphanCount = DB::table($table)
                    ->whereNotNull($column)
                    ->whereNotExists(function ($query) use ($parentTable, $column, $table) {
                        $query->select(DB::raw(1))
                            ->from($parentTable)
                            ->whereColumn("{$parentTable}.id", "{$table}.{$column}");
                    })
                    ->count();

                if ($orphanCount > 0) {
                    $issues[] = "{$table}.{$column}: {$orphanCount} orphaned records (parent not in {$parentTable})";
                }
            }
        }

        return $issues;
    }

    /**
     * Check if a foreign key constraint exists.
     */
    protected function foreignKeyExists(string $table, string $constraintName): bool
    {
        $result = DB::selectOne("
            SELECT COUNT(*) as count
            FROM information_schema.table_constraints
            WHERE constraint_type = 'FOREIGN KEY'
              AND table_name = ?
              AND constraint_name = ?
        ", [$table, $constraintName]);

        return ($result->count ?? 0) > 0;
    }
};
