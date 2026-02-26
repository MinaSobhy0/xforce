<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lifestyle_info', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreignId('medical_profile_id')->unique();

            // Smoking
            $table->enum('smoking_status', ['never', 'former', 'current'])->nullable();
            $table->string('smoking_frequency')->nullable();
            $table->integer('smoking_years')->nullable();
            $table->date('smoking_quit_date')->nullable();

            // Alcohol
            $table->enum('alcohol_status', ['never', 'occasional', 'regular', 'heavy'])->nullable();
            $table->string('alcohol_frequency')->nullable();

            // Exercise
            $table->enum('exercise_level', ['sedentary', 'light', 'moderate', 'active', 'very_active'])->nullable();
            $table->string('exercise_details')->nullable();

            // Sun Exposure
            $table->enum('sun_exposure_level', ['minimal', 'moderate', 'frequent', 'excessive'])->nullable();
            $table->boolean('uses_sunscreen')->nullable();
            $table->boolean('uses_tanning_beds')->nullable();

            // Sleep
            $table->integer('sleep_hours')->nullable();
            $table->boolean('sleep_issues')->nullable();

            // Diet
            $table->string('diet_type')->nullable();
            $table->text('dietary_restrictions')->nullable();

            // Occupational
            $table->text('occupational_exposures')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('medical_profile_id')->references('id')->on('medical_profiles')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lifestyle_info');
    }
};
