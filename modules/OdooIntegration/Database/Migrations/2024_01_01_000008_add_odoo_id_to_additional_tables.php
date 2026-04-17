<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Additional tables that need odoo_id columns for synchronization.
     */
    protected array $syncableTables = [
        'branches',
        'project_stages',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->syncableTables as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'odoo_id')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->integer('odoo_id')->nullable()->index()->after('id');
                    $table->timestamp('odoo_synced_at')->nullable();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->syncableTables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'odoo_id')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropColumn(['odoo_id', 'odoo_synced_at']);
                });
            }
        }
    }
};
