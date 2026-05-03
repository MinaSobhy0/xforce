<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add Odoo-aligned approver fields to staff_profiles.
 *
 * Mirrors hr.employee in Odoo:
 *   - leave_manager_id      → time_off_approver_user_id
 *   - attendance_manager_id → attendance_approver_user_id
 *
 * `hr_responsible_user_id` is local-only (no canonical Odoo field; some clinics
 * use coach_id but the semantics differ — kept manual for now).
 *
 * All three are nullOnDelete so removing an approver user doesn't cascade-delete
 * the staff profile.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('staff_profiles')) {
            return;
        }

        Schema::table('staff_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('staff_profiles', 'hr_responsible_user_id')) {
                $table->foreignId('hr_responsible_user_id')
                    ->nullable()
                    ->after('department_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('staff_profiles', 'time_off_approver_user_id')) {
                $table->foreignId('time_off_approver_user_id')
                    ->nullable()
                    ->after('hr_responsible_user_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('staff_profiles', 'attendance_approver_user_id')) {
                $table->foreignId('attendance_approver_user_id')
                    ->nullable()
                    ->after('time_off_approver_user_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('staff_profiles')) {
            return;
        }

        Schema::table('staff_profiles', function (Blueprint $table) {
            foreach (['attendance_approver_user_id', 'time_off_approver_user_id', 'hr_responsible_user_id'] as $col) {
                if (Schema::hasColumn('staff_profiles', $col)) {
                    try {
                        $table->dropForeign([$col]);
                    } catch (\Throwable $e) {
                        // ignore if FK doesn't exist
                    }
                    $table->dropColumn($col);
                }
            }
        });
    }
};
