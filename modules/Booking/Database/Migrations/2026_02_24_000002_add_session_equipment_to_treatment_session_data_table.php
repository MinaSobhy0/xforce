<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treatment_session_data', function (Blueprint $table) {
            // Session equipment array - stores multiple equipment used during session
            $table->json('session_equipment')->nullable()->after('equipment_metrics');
            /*
             * session_equipment structure:
             * [
             *   {
             *     "equipment_id": "uuid",
             *     "name": "Candela GentleMax Pro",
             *     "code": "EQ-001",
             *     "is_preset": true,
             *     "shots_used": 150,
             *     "energy_delivered": 2500
             *   },
             *   {
             *     "equipment_id": "uuid",
             *     "name": "Zimmer Cryo 6",
             *     "code": "EQ-002",
             *     "is_preset": false,
             *     "shots_used": null,
             *     "energy_delivered": null
             *   }
             * ]
             */
        });
    }

    public function down(): void
    {
        Schema::table('treatment_session_data', function (Blueprint $table) {
            $table->dropColumn('session_equipment');
        });
    }
};
