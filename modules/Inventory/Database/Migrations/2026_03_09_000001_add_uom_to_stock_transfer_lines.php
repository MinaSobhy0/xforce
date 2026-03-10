<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('stock_transfer_lines', 'uom_id')) {
            return;
        }

        Schema::table('stock_transfer_lines', function (Blueprint $table) {
            $table->foreignId('uom_id')->nullable()->after('product_id')->constrained('uoms')->nullOnDelete();
            // Store quantity in the selected UOM (for display/audit)
            // quantity_done is always converted to stock UOM for actual stock updates
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_transfer_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('uom_id');
        });
    }
};
