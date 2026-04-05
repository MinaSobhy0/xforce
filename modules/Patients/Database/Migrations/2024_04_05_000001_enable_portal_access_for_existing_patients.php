<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Enable portal access for all active patients by default
        DB::table('patients')
            ->where('status', 'active')
            ->update(['portal_access_enabled' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Optionally revert back (not recommended as this was a data migration)
        // DB::table('patients')->update(['portal_access_enabled' => false]);
    }
};
