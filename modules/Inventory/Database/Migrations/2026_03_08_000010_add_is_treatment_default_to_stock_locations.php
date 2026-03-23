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
        if (!Schema::hasColumn('stock_locations', 'is_treatment_default')) {
            Schema::table('stock_locations', function (Blueprint $table) {
                $table->boolean('is_treatment_default')->default(false)->after('is_return_location');
            });

            // Add index for efficient lookup
            Schema::table('stock_locations', function (Blueprint $table) {
                $table->index(['branch_id', 'is_treatment_default']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('stock_locations', 'is_treatment_default')) {
            Schema::table('stock_locations', function (Blueprint $table) {
                $table->dropIndex(['branch_id', 'is_treatment_default']);
                $table->dropColumn('is_treatment_default');
            });
        }
    }
};
