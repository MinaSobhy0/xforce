<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_amr_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreignId('patient_id')->unique(); // One summary per patient

            // Aggregated data from all AMR tests
            $table->jsonb('known_organisms')->nullable(); // ['MRSA', 'E. coli', 'P. aeruginosa']
            $table->jsonb('known_resistances')->nullable(); // ['Penicillin', 'Amoxicillin', 'Ciprofloxacin']
            $table->jsonb('known_sensitivities')->nullable(); // ['Vancomycin', 'Linezolid', 'Meropenem']
            $table->jsonb('mdro_flags')->nullable(); // ['MRSA', 'ESBL', 'VRE']

            // Alert flags
            $table->boolean('has_critical_resistance')->default(false);
            $table->text('alert_notes')->nullable();

            // Last test reference
            $table->foreignId('last_test_id')->nullable();
            $table->date('last_test_date')->nullable();

            // Audit
            $table->foreignId('last_updated_by')->nullable();
            $table->timestamp('last_updated_at')->nullable();

            $table->timestamps();

            // Foreign keys
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('patient_id')->references('id')->on('patients')->cascadeOnDelete();
            $table->foreign('last_test_id')->references('id')->on('patient_amr_tests')->nullOnDelete();
            $table->foreign('last_updated_by')->references('id')->on('users')->nullOnDelete();

            // Indexes
            $table->index(['tenant_id', 'has_critical_resistance']);
            $table->index('last_test_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_amr_summaries');
    }
};
