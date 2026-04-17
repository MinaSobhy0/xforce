<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add records_skipped column to track records skipped due to missing dependencies.
     */
    public function up(): void
    {
        Schema::table('odoo_sync_logs', function (Blueprint $table) {
            $table->integer('records_skipped')->default(0)->after('records_failed');
        });
    }

    public function down(): void
    {
        Schema::table('odoo_sync_logs', function (Blueprint $table) {
            $table->dropColumn('records_skipped');
        });
    }
};
