<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // First, drop the foreign key constraint if it exists
        $this->dropForeignKeyIfExists('package_items', 'package_items_treatment_id_foreign');

        // Drop the unique constraint if it exists
        $this->dropUniqueIfExists('package_items', ['package_id', 'treatment_id']);

        // Rename the column
        Schema::table('package_items', function (Blueprint $table) {
            $table->renameColumn('treatment_id', 'service_id');
        });

        // Add the new foreign key and unique constraint
        Schema::table('package_items', function (Blueprint $table) {
            $table->foreign('service_id')->references('id')->on('services')->cascadeOnDelete();
            $table->unique(['package_id', 'service_id']);
        });
    }

    public function down(): void
    {
        $this->dropForeignKeyIfExists('package_items', 'package_items_service_id_foreign');
        $this->dropUniqueIfExists('package_items', ['package_id', 'service_id']);

        Schema::table('package_items', function (Blueprint $table) {
            $table->renameColumn('service_id', 'treatment_id');
        });

        Schema::table('package_items', function (Blueprint $table) {
            $table->foreign('treatment_id')->references('id')->on('services')->cascadeOnDelete();
            $table->unique(['package_id', 'treatment_id']);
        });
    }

    private function dropForeignKeyIfExists(string $table, string $foreignKey): void
    {
        $schema = DB::connection()->getConfig('search_path') ?? 'public';

        $exists = DB::select("
            SELECT 1 FROM information_schema.table_constraints
            WHERE constraint_name = ?
            AND table_name = ?
            AND table_schema = ?
        ", [$foreignKey, $table, $schema]);

        if (!empty($exists)) {
            Schema::table($table, function (Blueprint $table) use ($foreignKey) {
                $table->dropForeign($foreignKey);
            });
        }
    }

    private function dropUniqueIfExists(string $table, array $columns): void
    {
        $indexName = $table . '_' . implode('_', $columns) . '_unique';
        $schema = DB::connection()->getConfig('search_path') ?? 'public';

        $exists = DB::select("
            SELECT 1 FROM information_schema.table_constraints
            WHERE constraint_name = ?
            AND table_name = ?
            AND table_schema = ?
        ", [$indexName, $table, $schema]);

        if (!empty($exists)) {
            Schema::table($table, function (Blueprint $table) use ($columns) {
                $table->dropUnique($columns);
            });
        }
    }
};
