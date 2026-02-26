<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_medications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id'); // No FK - tenants table is in public schema
            $table->foreignId('medical_profile_id');
            $table->string('medication_name');
            $table->string('generic_name')->nullable();
            $table->string('dosage')->nullable();
            $table->string('frequency')->nullable();
            $table->string('route')->nullable();
            $table->text('reason')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_ongoing')->default(true);
            $table->boolean('affects_treatment')->default(false);
            $table->text('treatment_implications')->nullable();
            $table->string('prescribing_doctor')->nullable();
            $table->boolean('is_otc')->default(false)->comment('Over-the-counter medication');
            $table->timestamps();
            $table->softDeletes();

            // No FK for tenant_id - tenants table is in public schema
            $table->foreign('medical_profile_id')->references('id')->on('medical_profiles')->cascadeOnDelete();

            $table->index(['medical_profile_id', 'is_ongoing']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_medications');
    }
};
