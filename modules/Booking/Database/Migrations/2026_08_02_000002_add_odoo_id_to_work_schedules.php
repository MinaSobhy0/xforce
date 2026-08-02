<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Work schedules sync from Odoo resource.calendar — they need the
     * standard odoo linkage columns.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('work_schedules', 'odoo_id')) {
            Schema::table('work_schedules', function (Blueprint $table) {
                $table->integer('odoo_id')->nullable()->index();
                $table->timestamp('odoo_synced_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('work_schedules', 'odoo_id')) {
            Schema::table('work_schedules', function (Blueprint $table) {
                $table->dropColumn(['odoo_id', 'odoo_synced_at']);
            });
        }
    }
};
