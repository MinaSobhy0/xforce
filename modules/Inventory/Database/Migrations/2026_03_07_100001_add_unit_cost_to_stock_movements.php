<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            // Track cost per unit for this movement (needed for FIFO/AVCO)
            $table->integer('unit_cost_minor')->default(0)->after('quantity_after');
            // Track remaining quantity for FIFO layers
            $table->integer('remaining_quantity')->nullable()->after('unit_cost_minor');
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropColumn(['unit_cost_minor', 'remaining_quantity']);
        });
    }
};
