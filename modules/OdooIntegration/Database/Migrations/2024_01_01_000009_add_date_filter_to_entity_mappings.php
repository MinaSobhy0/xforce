<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add date filter fields to entity mappings.
     * This allows filtering Odoo records by date when syncing.
     */
    public function up(): void
    {
        Schema::table('odoo_entity_mappings', function (Blueprint $table) {
            // The Odoo field name to use for date filtering (e.g., 'write_date', 'create_date', 'date_from')
            $table->string('sync_date_field')->nullable()->after('filter_conditions');

            // The date from which to sync records (records with date >= this value will be synced)
            $table->date('sync_from_date')->nullable()->after('sync_date_field');

            // The date until which to sync records (records with date <= this value will be synced)
            $table->date('sync_to_date')->nullable()->after('sync_from_date');
        });
    }

    public function down(): void
    {
        Schema::table('odoo_entity_mappings', function (Blueprint $table) {
            $table->dropColumn(['sync_date_field', 'sync_from_date', 'sync_to_date']);
        });
    }
};
