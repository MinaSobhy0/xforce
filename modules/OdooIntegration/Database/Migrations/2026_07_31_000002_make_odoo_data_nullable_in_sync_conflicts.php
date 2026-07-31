<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ExportService::handleDeletedInOdoo files a deleted-remotely conflict with
 * odoo_data = null (the record no longer exists in Odoo), but the column was
 * NOT NULL — the insert failed and the conflict was silently lost (counted
 * as a failed record instead). Mirror of 2024_01_01_000011 which fixed
 * local_data for the deleted-locally case.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('odoo_sync_conflicts')) {
            DB::statement('ALTER TABLE odoo_sync_conflicts ALTER COLUMN odoo_data DROP NOT NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('odoo_sync_conflicts')) {
            DB::statement("UPDATE odoo_sync_conflicts SET odoo_data = '{}' WHERE odoo_data IS NULL");
            DB::statement('ALTER TABLE odoo_sync_conflicts ALTER COLUMN odoo_data SET NOT NULL');
        }
    }
};
