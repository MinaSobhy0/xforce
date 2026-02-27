<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Determine the id type of the products table to match foreign key
        $productIdType = $this->getColumnType('products', 'id');
        $sessionProductIdType = $this->getColumnType('session_products', 'id');

        Schema::table('invoice_lines', function (Blueprint $table) use ($productIdType, $sessionProductIdType) {
            // Add product_id with matching type (if not exists)
            if (!Schema::hasColumn('invoice_lines', 'product_id')) {
                if ($productIdType === 'uuid') {
                    $table->uuid('product_id')->nullable()->index();
                } else {
                    $table->foreignId('product_id')->nullable()->index();
                }
            }

            // Add session_product_id with matching type (if not exists)
            if (!Schema::hasColumn('invoice_lines', 'session_product_id')) {
                if ($sessionProductIdType === 'uuid') {
                    $table->uuid('session_product_id')->nullable();
                } else {
                    $table->foreignId('session_product_id')->nullable();
                }
            }

            // Add line_type (if not exists)
            if (!Schema::hasColumn('invoice_lines', 'line_type')) {
                $table->string('line_type', 20)->default('service')->index();
            }
        });

        // Add foreign keys if they don't exist
        if (Schema::hasColumn('invoice_lines', 'product_id') && !$this->foreignKeyExists('invoice_lines', 'invoice_lines_product_id_foreign')) {
            Schema::table('invoice_lines', function (Blueprint $table) {
                $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            });
        }

        if (Schema::hasColumn('invoice_lines', 'session_product_id') && !$this->foreignKeyExists('invoice_lines', 'invoice_lines_session_product_id_foreign')) {
            Schema::table('invoice_lines', function (Blueprint $table) {
                $table->foreign('session_product_id')->references('id')->on('session_products')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropForeign(['session_product_id']);
            $table->dropColumn(['product_id', 'session_product_id', 'line_type']);
        });
    }

    private function getColumnType(string $table, string $column): string
    {
        $result = DB::select(
            "SELECT data_type FROM information_schema.columns WHERE table_name = ? AND column_name = ?",
            [$table, $column]
        );

        return $result[0]->data_type ?? 'bigint';
    }

    private function foreignKeyExists(string $table, string $foreignKey): bool
    {
        $result = DB::select(
            "SELECT constraint_name FROM information_schema.table_constraints
             WHERE table_name = ? AND constraint_name = ? AND constraint_type = 'FOREIGN KEY'",
            [$table, $foreignKey]
        );

        return !empty($result);
    }
};
