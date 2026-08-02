<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Odoo's xs.attendance.config can allow multiple check-in/out pairs per
     * day — the hard unique index on (tenant, staff, date) forbids that.
     * Application-level rules (open-record checks + the synced config) now
     * gate duplicates instead.
     */
    public function up(): void
    {
        if (Schema::hasTable('attendances')) {
            // Provisioned tenants carry it as a table CONSTRAINT, older ones
            // as a bare unique index — drop whichever exists.
            DB::statement('ALTER TABLE attendances DROP CONSTRAINT IF EXISTS attendances_unique_daily');
            DB::statement('DROP INDEX IF EXISTS attendances_unique_daily');
            DB::statement('CREATE INDEX IF NOT EXISTS attendances_daily_idx ON attendances (tenant_id, staff_profile_id, attendance_date)');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('attendances')) {
            DB::statement('DROP INDEX IF EXISTS attendances_daily_idx');
            DB::statement('CREATE UNIQUE INDEX attendances_unique_daily ON attendances (tenant_id, staff_profile_id, attendance_date)');
        }
    }
};
