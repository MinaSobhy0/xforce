<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treatment_session_data', function (Blueprint $table) {
            // Equipment parameter values - stores values for equipment tracking parameters
            $table->json('equipment_parameter_values')->nullable()->after('session_equipment');
            /*
             * equipment_parameter_values structure:
             * {
             *   "equipment_uuid_1": {
             *     "fluence": 15.5,
             *     "pulse_width": 20,
             *     "cooling_level": "high"
             *   },
             *   "equipment_uuid_2": {
             *     "energy": 100,
             *     "frequency": 10
             *   }
             * }
             */
        });
    }

    public function down(): void
    {
        Schema::table('treatment_session_data', function (Blueprint $table) {
            $table->dropColumn('equipment_parameter_values');
        });
    }
};
