<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment_tracking_parameters', function (Blueprint $table) {
            $table->string('source', 20)->default('manual')->after('is_active');
            // 'manual' = custom parameter added directly
            // 'template' = imported from parameter template
        });

        // Rename parameter_name to name if it exists
        if (Schema::hasColumn('equipment_tracking_parameters', 'parameter_name')) {
            Schema::table('equipment_tracking_parameters', function (Blueprint $table) {
                $table->renameColumn('parameter_name', 'name');
            });
        }
    }

    public function down(): void
    {
        Schema::table('equipment_tracking_parameters', function (Blueprint $table) {
            $table->dropColumn('source');
        });

        if (Schema::hasColumn('equipment_tracking_parameters', 'name')) {
            Schema::table('equipment_tracking_parameters', function (Blueprint $table) {
                $table->renameColumn('name', 'parameter_name');
            });
        }
    }
};
