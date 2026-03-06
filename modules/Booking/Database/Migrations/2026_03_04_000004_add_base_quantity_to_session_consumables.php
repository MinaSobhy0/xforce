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
        Schema::table('session_consumables', function (Blueprint $table) {
            // Base quantity per 1 service unit - used for auto-scaling when service quantity changes
            $table->decimal('base_quantity', 10, 2)->nullable()->after('quantity');
        });

        // Set base_quantity = quantity for existing records (assuming they were added at service qty = 1)
        DB::table('session_consumables')
            ->whereNull('base_quantity')
            ->update(['base_quantity' => DB::raw('quantity')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('session_consumables', function (Blueprint $table) {
            $table->dropColumn('base_quantity');
        });
    }
};
