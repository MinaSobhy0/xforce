<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_rule_actions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('attendance_rule_id')->index();
            $table->integer('occurrence_number')->nullable(); // 1st, 2nd, 3rd offense
            $table->string('action_type', 50); // deduction, warning, approval_required, notification, block_attendance
            $table->string('severity', 20)->default('moderate'); // minor, moderate, severe

            // Threshold
            $table->string('threshold_type', 30); // time_based, occurrence_based
            $table->integer('threshold_value'); // minutes or count
            $table->string('threshold_period', 20)->nullable(); // day, week, month, year

            // Penalty
            $table->string('penalty_type', 30)->nullable(); // fixed, percentage, hourly_rate, formula
            $table->integer('penalty_amount_minor')->default(0);
            $table->decimal('penalty_percentage', 5, 2)->nullable();
            $table->text('penalty_formula')->nullable();

            // Workflow
            $table->boolean('requires_approval')->default(false);
            $table->boolean('notification_enabled')->default(true);
            $table->boolean('notify_manager')->default(true);
            $table->boolean('notify_hr')->default(false);

            $table->text('message_template')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Foreign key
            $table->foreign('attendance_rule_id')
                ->references('id')
                ->on('attendance_rules')
                ->onDelete('cascade');

            // Indexes
            $table->index(['attendance_rule_id', 'threshold_value']);
            $table->index(['attendance_rule_id', 'occurrence_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_rule_actions');
    }
};
