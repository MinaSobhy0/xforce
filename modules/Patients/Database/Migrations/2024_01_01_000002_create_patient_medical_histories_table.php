<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('patient_medical_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('patient_id')->unique();

            // Skin Type
            $table->enum('fitzpatrick_type', ['I', 'II', 'III', 'IV', 'V', 'VI'])->nullable();

            // General Health
            $table->string('blood_type', 5)->nullable();
            $table->integer('height_cm')->nullable();
            $table->decimal('weight_kg', 5, 1)->nullable();

            // Medical Arrays (JSON)
            $table->jsonb('allergies')->nullable();
            $table->jsonb('current_medications')->nullable();
            $table->jsonb('medical_conditions')->nullable();
            $table->jsonb('previous_cosmetic_treatments')->nullable();
            $table->jsonb('previous_surgeries')->nullable();
            $table->jsonb('contraindications')->nullable();
            $table->jsonb('skin_concerns')->nullable();

            // Women's Health
            $table->boolean('is_pregnant')->nullable();
            $table->boolean('is_breastfeeding')->nullable();
            $table->date('last_menstrual_date')->nullable();
            $table->boolean('hormone_therapy')->nullable();

            // Lifestyle
            $table->boolean('is_smoker')->nullable();
            $table->enum('sun_exposure_level', ['minimal', 'moderate', 'high'])->nullable();
            $table->enum('sunscreen_usage', ['never', 'sometimes', 'always'])->nullable();
            $table->text('skincare_routine')->nullable();

            // External Physician
            $table->string('physician_name', 200)->nullable();
            $table->string('physician_phone', 20)->nullable();

            // Notes
            $table->text('notes')->nullable();

            // Tracking
            $table->uuid('last_updated_by')->nullable();
            $table->timestamp('last_updated_at')->nullable();

            $table->timestamps();

            // Foreign keys
            $table->foreign('patient_id')
                ->references('id')
                ->on('patients')
                ->cascadeOnDelete();

            $table->foreign('last_updated_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_medical_histories');
    }
};
