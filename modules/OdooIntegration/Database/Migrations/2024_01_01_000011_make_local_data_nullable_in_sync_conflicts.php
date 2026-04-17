<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Make local_data nullable in sync_conflicts table.
     * This is needed for deleted_locally conflicts where there's no local data.
     */
    public function up(): void
    {
        // Use raw SQL to alter nullable constraint
        DB::statement('ALTER TABLE odoo_sync_conflicts ALTER COLUMN local_data DROP NOT NULL');
    }

    public function down(): void
    {
        // Update any null values before making it NOT NULL again
        DB::table('odoo_sync_conflicts')
            ->whereNull('local_data')
            ->update(['local_data' => json_encode(['_deleted' => true])]);

        DB::statement('ALTER TABLE odoo_sync_conflicts ALTER COLUMN local_data SET NOT NULL');
    }
};
