<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treatment_session_data', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('appointment_id');
            $table->uuid('service_id');
            $table->uuid('equipment_id')->nullable();
            $table->uuid('practitioner_id');

            // Parameter values recorded during session
            $table->json('parameter_values')->nullable();
            /*
             * parameter_values structure:
             * {
             *   "wavelength": {"value": 1064, "unit": "nm"},
             *   "fluence": {"value": 15.5, "unit": "J/cm²"},
             *   "pulse_duration": {"value": 20, "unit": "ms"},
             *   "spot_size": {"value": 8, "unit": "mm"},
             *   "cooling_level": {"value": "high", "unit": null},
             *   "total_pulses": {"value": 250, "unit": "pulses"}
             * }
             */

            // Equipment metrics recorded during session
            $table->json('equipment_metrics')->nullable();
            /*
             * equipment_metrics structure:
             * {
             *   "shots_used": 250,
             *   "starting_shot_count": 10500,
             *   "ending_shot_count": 10750,
             *   "energy_delivered": {"value": 3875, "unit": "J"},
             *   "duration_active": {"value": 25, "unit": "minutes"}
             * }
             */

            // Treatment area documentation
            $table->json('treatment_areas')->nullable();
            /*
             * treatment_areas structure:
             * [
             *   {"area": "upper_lip", "pulses": 50, "passes": 2},
             *   {"area": "chin", "pulses": 80, "passes": 2},
             *   {"area": "sideburns", "pulses": 120, "passes": 3}
             * ]
             */

            // Clinical observations
            $table->text('clinical_notes')->nullable();
            $table->string('skin_reaction', 50)->nullable(); // none, mild, moderate, severe
            $table->string('pain_level', 20)->nullable(); // 0-10 or descriptive
            $table->json('adverse_events')->nullable();

            // Pre-treatment checklist
            $table->json('pre_treatment_checklist')->nullable();
            /*
             * {
             *   "skin_test_done": true,
             *   "contraindications_checked": true,
             *   "consent_signed": true,
             *   "eye_protection_provided": true
             * }
             */

            // Post-treatment instructions
            $table->json('post_treatment_instructions')->nullable();
            $table->boolean('aftercare_provided')->default(false);

            // Session timing
            $table->timestamp('session_started_at')->nullable();
            $table->timestamp('session_ended_at')->nullable();
            $table->integer('actual_duration_minutes')->nullable();

            // Preset used (if any)
            $table->uuid('preset_id')->nullable();

            // Data validation status
            $table->boolean('is_complete')->default(false);
            $table->boolean('is_validated')->default(false);
            $table->uuid('validated_by')->nullable();
            $table->timestamp('validated_at')->nullable();

            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('cascade');

            $table->foreign('appointment_id')
                ->references('id')
                ->on('appointments')
                ->onDelete('cascade');

            $table->foreign('service_id')
                ->references('id')
                ->on('services')
                ->onDelete('cascade');

            $table->foreign('equipment_id')
                ->references('id')
                ->on('equipment')
                ->onDelete('set null');

            $table->foreign('practitioner_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->foreign('preset_id')
                ->references('id')
                ->on('parameter_presets')
                ->onDelete('set null');

            $table->index(['appointment_id']);
            $table->index(['service_id', 'equipment_id']);
            $table->index(['practitioner_id', 'created_at']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treatment_session_data');
    }
};
