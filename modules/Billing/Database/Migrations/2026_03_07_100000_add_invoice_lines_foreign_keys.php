<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add foreign keys to invoice_lines table.
 * These are added in a separate migration because the referenced tables
 * (services, products, session_products, chart_of_accounts) are created
 * in modules that run later in the migration order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table) {
            // Only add foreign keys if the referenced tables exist
            if (Schema::hasTable('services') && !$this->foreignKeyExists('invoice_lines', 'invoice_lines_service_id_foreign')) {
                $table->foreign('service_id')->references('id')->on('services')->nullOnDelete();
            }

            if (Schema::hasTable('products') && !$this->foreignKeyExists('invoice_lines', 'invoice_lines_product_id_foreign')) {
                $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            }

            if (Schema::hasTable('session_products') && !$this->foreignKeyExists('invoice_lines', 'invoice_lines_session_product_id_foreign')) {
                $table->foreign('session_product_id')->references('id')->on('session_products')->nullOnDelete();
            }

            if (Schema::hasTable('chart_of_accounts') && !$this->foreignKeyExists('invoice_lines', 'invoice_lines_account_id_foreign')) {
                $table->foreign('account_id')->references('id')->on('chart_of_accounts')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->dropForeignIfExists(['service_id']);
            $table->dropForeignIfExists(['product_id']);
            $table->dropForeignIfExists(['session_product_id']);
            $table->dropForeignIfExists(['account_id']);
        });
    }

    private function foreignKeyExists(string $table, string $foreignKey): bool
    {
        $connection = Schema::getConnection();
        $schemaName = $connection->selectOne("SHOW search_path")->search_path ?? 'public';

        $result = $connection->selectOne("
            SELECT COUNT(*) as count
            FROM information_schema.table_constraints
            WHERE constraint_type = 'FOREIGN KEY'
              AND table_schema = ?
              AND table_name = ?
              AND constraint_name = ?
        ", [$schemaName, $table, $foreignKey]);

        return ($result->count ?? 0) > 0;
    }
};
