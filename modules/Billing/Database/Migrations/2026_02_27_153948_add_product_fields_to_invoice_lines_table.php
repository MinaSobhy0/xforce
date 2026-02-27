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
            // Add product_id with matching type
            if ($productIdType === 'uuid') {
                $table->uuid('product_id')->nullable()->index();
            } else {
                $table->foreignId('product_id')->nullable()->index();
            }

            // Add session_product_id with matching type
            if ($sessionProductIdType === 'uuid') {
                $table->uuid('session_product_id')->nullable();
            } else {
                $table->foreignId('session_product_id')->nullable();
            }

            // Add line_type
            $table->string('line_type', 20)->default('service')->index();

            // Add foreign keys
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            $table->foreign('session_product_id')->references('id')->on('session_products')->nullOnDelete();
        });
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
};
