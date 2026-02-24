<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skin_assessments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('medical_profile_id');
            $table->uuid('appointment_id')->nullable();
            $table->uuid('assessed_by');

            // Fitzpatrick Assessment
            $table->tinyInteger('fitzpatrick_type')->nullable()->comment('I-VI');

            // Skin Characteristics
            $table->enum('skin_type_oily', ['dry', 'normal', 'oily', 'combination'])->nullable();
            $table->enum('skin_sensitivity', ['low', 'normal', 'high', 'very_high'])->nullable();
            $table->enum('skin_texture', ['smooth', 'slightly_rough', 'rough', 'very_rough'])->nullable();
            $table->enum('pore_size', ['small', 'medium', 'large', 'very_large'])->nullable();
            $table->enum('skin_tone', ['even', 'slightly_uneven', 'uneven', 'very_uneven'])->nullable();

            // Measurements (percentages or scores)
            $table->tinyInteger('hydration_level')->nullable();
            $table->tinyInteger('elasticity_score')->nullable();
            $table->tinyInteger('pigmentation_level')->nullable();
            $table->tinyInteger('acne_severity')->nullable();

            // Conditions
            $table->jsonb('current_conditions')->nullable();
            $table->jsonb('previous_conditions')->nullable();

            // Aging
            $table->jsonb('aging_signs')->nullable();
            $table->enum('aging_level', ['none', 'early', 'moderate', 'advanced'])->nullable();

            // Sun Damage
            $table->enum('sun_damage_level', ['none', 'mild', 'moderate', 'severe'])->nullable();
            $table->jsonb('sun_damage_signs')->nullable();

            // Assessment
            $table->jsonb('areas_of_concern')->nullable();
            $table->text('patient_goals')->nullable();
            $table->text('clinical_observations')->nullable();
            $table->text('recommendations')->nullable();
            $table->text('treatment_recommendations')->nullable();
            $table->text('notes')->nullable();

            // Photos
            $table->jsonb('assessment_photos')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('medical_profile_id')->references('id')->on('medical_profiles')->cascadeOnDelete();
            $table->foreign('appointment_id')->references('id')->on('appointments')->nullOnDelete();
            $table->foreign('assessed_by')->references('id')->on('users')->restrictOnDelete();

            $table->index(['medical_profile_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skin_assessments');
    }
};
