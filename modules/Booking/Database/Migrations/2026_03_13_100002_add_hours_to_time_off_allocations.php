<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('time_off_allocations', function (Blueprint $table) {
            // Month for monthly allocations (null for yearly, 1-12 for monthly)
            $table->integer('month')->nullable()->after('year');

            // Update unique constraint to include month
            $table->dropUnique('time_off_allocation_unique');
        });

        // Re-create unique constraint with month included
        Schema::table('time_off_allocations', function (Blueprint $table) {
            $table->unique(
                ['tenant_id', 'user_id', 'time_off_type_id', 'year', 'month'],
                'time_off_allocation_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('time_off_allocations', function (Blueprint $table) {
            $table->dropUnique('time_off_allocation_unique');
        });

        Schema::table('time_off_allocations', function (Blueprint $table) {
            $table->dropColumn('month');
            $table->unique(
                ['tenant_id', 'user_id', 'time_off_type_id', 'year'],
                'time_off_allocation_unique'
            );
        });
    }
};
