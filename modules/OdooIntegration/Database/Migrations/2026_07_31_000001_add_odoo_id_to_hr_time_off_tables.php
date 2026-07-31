<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The HR time-off sync (hr.leave.type / hr.leave.allocation / hr.leave and
 * project time entries) relies on odoo_id + odoo_synced_at columns that were
 * added manually on existing tenants but never captured in a migration —
 * freshly provisioned tenants ended up without them and time-off sync broke.
 */
return new class extends Migration
{
    protected array $syncableTables = [
        'time_off_types',
        'time_off_allocations',
        'practitioner_time_off',
        'project_time_entries',
    ];

    public function up(): void
    {
        foreach ($this->syncableTables as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'odoo_id')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->integer('odoo_id')->nullable()->index()->after('id');
                    $table->timestamp('odoo_synced_at')->nullable();
                });
            }
        }
    }

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
