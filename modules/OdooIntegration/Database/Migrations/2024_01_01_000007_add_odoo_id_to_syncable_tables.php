<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables that need odoo_id and odoo_synced_at columns.
     * This migration adds these columns to tables from various modules
     * to enable Odoo synchronization.
     */
    protected array $syncableTables = [
        'users',
        'staff_profiles',
        'salary_rule_categories',
        'salary_structures',
        'salary_rules',
        'payroll_runs',
        'payroll_lines',
        'time_off_types',
        'time_off_allocations',
        'practitioner_time_off',
        'attendances',
        'projects',
        'project_tasks',
        'project_time_entries',
    ];

    public function up(): void
    {
        foreach ($this->syncableTables as $tableName) {
            if (Schema::hasTable($tableName) && !Schema::hasColumn($tableName, 'odoo_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->unsignedBigInteger('odoo_id')->nullable()->index();
                    $table->timestamp('odoo_synced_at')->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->syncableTables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'odoo_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn(['odoo_id', 'odoo_synced_at']);
                });
            }
        }
    }
};
