<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_profiles', function (Blueprint $table) {
            // Allowed check-in methods: ['manual', 'geofence', 'qr_static', 'qr_dynamic', 'biometric']
            // NULL means all enabled methods are allowed
            $table->jsonb('allowed_check_in_methods')->nullable()->after('commission_plan_id');

            // Allowed geofence location IDs (branch IDs) for geofence check-in
            // NULL means all configured locations are allowed
            $table->jsonb('allowed_geofence_locations')->nullable()->after('allowed_check_in_methods');
        });
    }

    public function down(): void
    {
        Schema::table('staff_profiles', function (Blueprint $table) {
            $table->dropColumn(['allowed_check_in_methods', 'allowed_geofence_locations']);
        });
    }
};
