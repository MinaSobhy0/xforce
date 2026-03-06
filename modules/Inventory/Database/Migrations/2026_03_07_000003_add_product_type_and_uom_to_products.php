<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Add product_type column
            $table->string('product_type', 20)->default('storable')->after('is_consumable');

            // Add UoM foreign keys
            $table->foreignId('sales_uom_id')->nullable()->after('unit');
            $table->foreignId('purchase_uom_id')->nullable()->after('sales_uom_id');

            $table->foreign('sales_uom_id')->references('id')->on('uoms')->nullOnDelete();
            $table->foreign('purchase_uom_id')->references('id')->on('uoms')->nullOnDelete();
            $table->index(['tenant_id', 'product_type']);
        });

        // Migrate existing data: is_consumable = false means storable, true means consumable
        DB::statement("UPDATE products SET product_type = CASE WHEN is_consumable = true THEN 'consumable' ELSE 'storable' END");
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['sales_uom_id']);
            $table->dropForeign(['purchase_uom_id']);
            $table->dropIndex(['tenant_id', 'product_type']);
            $table->dropColumn(['product_type', 'sales_uom_id', 'purchase_uom_id']);
        });
    }
};
