<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_violations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index();
            $table->foreignId('attendance_id')->index();
            $table->foreignId('staff_profile_id')->index();
            $table->foreignId('attendance_rule_id')->nullable()->index();
            $table->foreignId('attendance_rule_action_id')->nullable();
            $table->string('violation_type', 50); // late_checkin, early_checkout, etc.
            $table->date('violation_date');
            $table->time('scheduled_time')->nullable();
            $table->time('actual_time')->nullable();
            $table->integer('grace_period_minutes')->default(0);
            $table->integer('violation_minutes')->default(0);
            $table->integer('penalty_amount_minor')->default(0);
            $table->string('penalty_type', 30)->nullable();
            $table->json('penalty_calculation_details')->nullable();
            $table->string('status', 30)->default('pending'); // pending, approved, waived, disputed, applied, cancelled
            $table->text('reason')->nullable();
            $table->text('employee_notes')->nullable();
            $table->text('manager_notes')->nullable();
            $table->foreignId('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('waived_by')->nullable();
            $table->text('waived_reason')->nullable();
            $table->timestamp('waived_at')->nullable();
            $table->text('dispute_reason')->nullable();
            $table->timestamp('disputed_at')->nullable();
            $table->foreignId('payroll_line_id')->nullable(); // linked when applied to payroll
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('attendance_id')
                ->references('id')
                ->on('attendances')
                ->onDelete('cascade');

            $table->foreign('staff_profile_id')
                ->references('id')
                ->on('staff_profiles')
                ->onDelete('cascade');

            $table->foreign('attendance_rule_id')
                ->references('id')
                ->on('attendance_rules')
                ->onDelete('set null');

            $table->foreign('attendance_rule_action_id')
                ->references('id')
                ->on('attendance_rule_actions')
                ->onDelete('set null');

            $table->foreign('approved_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->foreign('waived_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            // Indexes
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'violation_date']);
            $table->index(['tenant_id', 'staff_profile_id', 'status']);
            $table->index(['tenant_id', 'violation_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_violations');
    }
};
