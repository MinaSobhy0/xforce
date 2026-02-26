<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create work_schedules table - weekly schedule templates
        if (!Schema::hasTable('work_schedules')) {
            Schema::create('work_schedules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->index();
                $table->foreignId('branch_id')->nullable()->index();
                $table->string('name'); // e.g., "Morning Shift", "Evening Shift", "Full Day"
                $table->string('code')->nullable(); // e.g., "morning", "evening", "full"
                $table->text('description')->nullable();

                // Weekly schedule as JSON: { "0": {"is_working": true, "start": "09:00", "end": "17:00", "break_start": "13:00", "break_end": "14:00"}, ... }
                $table->json('weekly_hours');

                $table->integer('slot_duration')->default(30); // minutes per slot
                $table->integer('buffer_time')->default(0); // minutes between appointments
                $table->integer('max_daily_appointments')->nullable();

                $table->string('color')->nullable(); // for calendar display
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);

                $table->timestamps();
                $table->softDeletes();

                $table->unique(['tenant_id', 'code']);
            });
        }

        // Create practitioner_schedule_assignments - links practitioners to work schedules
        if (Schema::hasTable('practitioner_schedule_assignments')) {
            return;
        }

        Schema::create('practitioner_schedule_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index();
            $table->foreignId('user_id')->index(); // practitioner
            $table->foreignId('work_schedule_id')->index();
            $table->foreignId('branch_id')->nullable()->index(); // override branch if different from schedule

            $table->date('effective_from')->nullable(); // null = immediately
            $table->date('effective_until')->nullable(); // null = indefinitely

            $table->json('day_overrides')->nullable(); // override specific days: {"1": {"start": "10:00"}}

            $table->boolean('is_primary')->default(false); // primary schedule for this practitioner
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('work_schedule_id')->references('id')->on('work_schedules')->onDelete('cascade');

            // Prevent duplicate active assignments for same practitioner/schedule/branch
            $table->unique(['tenant_id', 'user_id', 'work_schedule_id', 'branch_id', 'effective_from'], 'psa_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('practitioner_schedule_assignments');
        Schema::dropIfExists('work_schedules');
    }
};
