<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index();
            $table->foreignId('staff_profile_id')->index();
            $table->foreignId('branch_id')->nullable()->index();
            $table->foreignId('working_schedule_id')->nullable()->index();
            $table->date('attendance_date');
            $table->time('check_in_time')->nullable();
            $table->time('check_out_time')->nullable();
            $table->string('attendance_type', 30)->default('manual');
            $table->string('status', 20)->default('present');
            $table->decimal('working_hours', 5, 2)->default(0);
            $table->decimal('late_hours', 5, 2)->default(0);
            $table->decimal('early_hours', 5, 2)->default(0);
            $table->decimal('overtime_hours', 5, 2)->default(0);
            $table->text('late_reason')->nullable();
            $table->text('early_checkout_reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('staff_profile_id')
                ->references('id')
                ->on('staff_profiles')
                ->onDelete('cascade');

            $table->foreign('branch_id')
                ->references('id')
                ->on('branches')
                ->onDelete('set null');

            $table->foreign('approved_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->foreign('updated_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            // Unique constraint: one attendance per staff per day
            $table->unique(['tenant_id', 'staff_profile_id', 'attendance_date'], 'attendances_unique_daily');

            // Indexes
            $table->index(['tenant_id', 'attendance_date']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
