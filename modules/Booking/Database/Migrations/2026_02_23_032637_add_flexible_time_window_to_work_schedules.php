<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_schedules', function (Blueprint $table) {
            $table->time('flexible_start_time')->nullable()->after('working_days');
            $table->time('flexible_end_time')->nullable()->after('flexible_start_time');
        });
    }

    public function down(): void
    {
        Schema::table('work_schedules', function (Blueprint $table) {
            $table->dropColumn(['flexible_start_time', 'flexible_end_time']);
        });
    }
};
