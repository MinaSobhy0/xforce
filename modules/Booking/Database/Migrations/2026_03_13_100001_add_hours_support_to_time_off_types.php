<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('time_off_types', function (Blueprint $table) {
            // Request unit: day, half_day, or hour
            $table->string('request_unit', 20)->default('day')->after('allow_partial_day');

            // Allocation period: yearly or monthly
            $table->string('allocation_period', 20)->default('yearly')->after('request_unit');

            // Hours per day for conversion (default 8 hours workday)
            $table->decimal('hours_per_day', 4, 2)->default(8.00)->after('allocation_period');

            // Default allocation per period (hours or days based on request_unit)
            $table->decimal('default_allocation', 8, 2)->nullable()->after('hours_per_day');

            // Max hours/days per request
            $table->decimal('max_per_request', 8, 2)->nullable()->after('default_allocation');
        });
    }

    public function down(): void
    {
        Schema::table('time_off_types', function (Blueprint $table) {
            $table->dropColumn([
                'request_unit',
                'allocation_period',
                'hours_per_day',
                'default_allocation',
                'max_per_request',
            ]);
        });
    }
};
