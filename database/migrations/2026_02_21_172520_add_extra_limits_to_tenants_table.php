<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds columns for additional purchased resources beyond plan limits.
     * These are additive - total limit = plan limit + extra purchased.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Additional purchased resources (on top of plan limits)
            if (!Schema::hasColumn('tenants', 'extra_users')) {
                $table->integer('extra_users')->default(0)->after('max_storage_mb');
            }
            if (!Schema::hasColumn('tenants', 'extra_branches')) {
                $table->integer('extra_branches')->default(0)->after('extra_users');
            }
            if (!Schema::hasColumn('tenants', 'extra_patients')) {
                $table->integer('extra_patients')->default(0)->after('extra_branches');
            }
            if (!Schema::hasColumn('tenants', 'extra_storage_mb')) {
                $table->integer('extra_storage_mb')->default(0)->after('extra_patients');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $columns = ['extra_users', 'extra_branches', 'extra_patients', 'extra_storage_mb'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('tenants', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
