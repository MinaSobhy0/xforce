<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_levels', function (Blueprint $table) {
            // Add location_id after branch_id
            $table->foreignId('location_id')->nullable()->after('branch_id');

            $table->foreign('location_id')
                ->references('id')
                ->on('stock_locations')
                ->nullOnDelete();
        });

        // Drop the old unique constraint
        Schema::table('stock_levels', function (Blueprint $table) {
            $table->dropUnique(['product_id', 'branch_id']);
        });

        // Add new unique constraint including location_id
        Schema::table('stock_levels', function (Blueprint $table) {
            $table->unique(['product_id', 'branch_id', 'location_id']);
        });
    }

    public function down(): void
    {
        // Restore original unique constraint
        Schema::table('stock_levels', function (Blueprint $table) {
            $table->dropUnique(['product_id', 'branch_id', 'location_id']);
        });

        Schema::table('stock_levels', function (Blueprint $table) {
            $table->unique(['product_id', 'branch_id']);
        });

        Schema::table('stock_levels', function (Blueprint $table) {
            $table->dropForeign(['location_id']);
            $table->dropColumn('location_id');
        });
    }
};
