<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('practitioner_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->index();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->integer('day_of_week'); // 0 = Sunday, 6 = Saturday
            $table->time('start_time');
            $table->time('end_time');
            $table->time('break_start')->nullable();
            $table->time('break_end')->nullable();
            $table->boolean('is_available')->default(true);
            $table->integer('max_appointments')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('user_id');
            $table->index('branch_id');
            $table->index('day_of_week');
            $table->index('is_available');
            $table->index(['user_id', 'branch_id', 'day_of_week']);

            // Unique constraint: one schedule per practitioner per branch per day
            $table->unique(['user_id', 'branch_id', 'day_of_week'], 'practitioner_branch_day_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('practitioner_schedules');
    }
};
