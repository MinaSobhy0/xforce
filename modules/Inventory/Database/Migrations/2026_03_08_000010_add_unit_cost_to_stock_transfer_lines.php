<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('stock_transfer_lines', 'unit_cost_minor')) {
            return;
        }

        Schema::table('stock_transfer_lines', function (Blueprint $table) {
            // Track unit cost for this line (for valuation tracking)
            $table->integer('unit_cost_minor')->default(0)->after('quantity_done');
        });
    }

    public function down(): void
    {
        Schema::table('stock_transfer_lines', function (Blueprint $table) {
            $table->dropColumn('unit_cost_minor');
        });
    }
};
