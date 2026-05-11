<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_lines', function (Blueprint $table) {
            $table->string('status', 32)->default('draft')->after('staff_profile_id');
            $table->index('status');
        });

        DB::statement('ALTER TABLE payroll_lines ALTER COLUMN payroll_run_id DROP NOT NULL');

        DB::statement('ALTER TABLE payroll_lines DROP CONSTRAINT IF EXISTS payroll_lines_payroll_run_id_staff_profile_id_unique');

        // Partial unique: only enforce when the slip belongs to a run.
        // Standalone slips (payroll_run_id IS NULL) are allowed to repeat per staff.
        DB::statement('CREATE UNIQUE INDEX payroll_lines_run_staff_unique
            ON payroll_lines (payroll_run_id, staff_profile_id)
            WHERE payroll_run_id IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS payroll_lines_run_staff_unique');

        // Restore original unique constraint (will fail if NULLs remain).
        DB::statement('ALTER TABLE payroll_lines
            ADD CONSTRAINT payroll_lines_payroll_run_id_staff_profile_id_unique
            UNIQUE (payroll_run_id, staff_profile_id)');

        DB::statement('ALTER TABLE payroll_lines ALTER COLUMN payroll_run_id SET NOT NULL');

        Schema::table('payroll_lines', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
    }
};
