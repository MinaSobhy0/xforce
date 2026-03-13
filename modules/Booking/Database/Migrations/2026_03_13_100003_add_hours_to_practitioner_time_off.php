<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('practitioner_time_off', function (Blueprint $table) {
            // Hours requested (for hour-based time off types)
            $table->decimal('hours_requested', 8, 2)->nullable()->after('days_requested');
        });
    }

    public function down(): void
    {
        Schema::table('practitioner_time_off', function (Blueprint $table) {
            $table->dropColumn('hours_requested');
        });
    }
};
