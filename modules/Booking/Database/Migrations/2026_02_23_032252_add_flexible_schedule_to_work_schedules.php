<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_schedules', function (Blueprint $table) {
            $table->string('schedule_type', 20)->default('fixed')->after('description');
            $table->decimal('required_hours_per_day', 4, 2)->nullable()->after('schedule_type');
            $table->decimal('required_hours_per_week', 5, 2)->nullable()->after('required_hours_per_day');
            $table->json('working_days')->nullable()->after('required_hours_per_week');
        });
    }

    public function down(): void
    {
        Schema::table('work_schedules', function (Blueprint $table) {
            $table->dropColumn(['schedule_type', 'required_hours_per_day', 'required_hours_per_week', 'working_days']);
        });
    }
};
