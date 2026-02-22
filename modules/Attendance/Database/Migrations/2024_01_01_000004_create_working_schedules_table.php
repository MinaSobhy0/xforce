<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('working_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('branch_id')->nullable()->index();
            $table->string('name');
            $table->string('code', 50)->index();
            $table->text('description')->nullable();
            $table->string('type', 30)->default('fixed'); // fixed, flexible, shift, compressed, remote

            // Fixed Schedule
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->decimal('hours_per_day', 5, 2)->default(8.00);
            $table->decimal('hours_per_week', 5, 2)->default(40.00);

            // Grace Periods
            $table->integer('grace_period_late_minutes')->default(0);
            $table->integer('grace_period_early_minutes')->default(0);

            // Flexible Schedule
            $table->boolean('is_flexible')->default(false);
            $table->time('flexible_start_from')->nullable();
            $table->time('flexible_start_to')->nullable();
            $table->time('flexible_end_from')->nullable();
            $table->time('flexible_end_to')->nullable();
            $table->decimal('flexible_min_hours_per_day', 5, 2)->nullable();
            $table->decimal('flexible_max_hours_per_day', 5, 2)->nullable();

            // Core Hours
            $table->boolean('core_hours_required')->default(false);
            $table->time('core_hours_start')->nullable();
            $table->time('core_hours_end')->nullable();

            // Working Days
            $table->json('working_days')->nullable(); // [0,1,2,3,4,5,6] - Sunday=0
            $table->decimal('days_per_week', 3, 1)->default(5.0);

            // Break Settings
            $table->boolean('has_break')->default(true);
            $table->integer('break_duration_minutes')->default(60);
            $table->time('break_start')->nullable();
            $table->time('break_end')->nullable();
            $table->boolean('flexible_break')->default(false);

            // Overtime
            $table->boolean('allow_overtime')->default(true);
            $table->decimal('max_overtime_per_day', 5, 2)->nullable();
            $table->decimal('max_overtime_per_week', 5, 2)->nullable();

            // Shift Rotation
            $table->boolean('is_rotating_shift')->default(false);
            $table->integer('rotation_cycle_days')->nullable();
            $table->json('shift_pattern')->nullable();

            // Remote/Hybrid
            $table->boolean('is_remote')->default(false);
            $table->boolean('is_hybrid')->default(false);
            $table->integer('remote_days_per_week')->nullable();
            $table->json('remote_days')->nullable();

            // Status
            $table->string('status', 20)->default('active'); // active, inactive
            $table->boolean('is_default')->default(false);
            $table->text('notes')->nullable();

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('branch_id')
                ->references('id')
                ->on('branches')
                ->onDelete('set null');

            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->foreign('updated_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            // Indexes
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('working_schedules');
    }
};
