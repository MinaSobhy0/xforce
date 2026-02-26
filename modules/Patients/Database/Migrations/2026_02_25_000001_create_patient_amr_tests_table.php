<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_amr_tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreignId('patient_id');

            // Lab reference
            $table->string('lab_accession_number')->nullable();

            // Test dates
            $table->date('collection_date');
            $table->date('result_date')->nullable();

            // Specimen information
            $table->enum('specimen_source', [
                'blood', 'urine', 'wound', 'sputum', 'stool',
                'throat', 'nasal', 'skin', 'csf', 'abscess',
                'tissue', 'drainage', 'catheter', 'respiratory',
                'genital', 'other'
            ]);
            $table->string('specimen_site')->nullable(); // Specific body location

            // Organism identification
            $table->string('organism_name'); // e.g., "Staphylococcus aureus"
            $table->string('organism_code')->nullable(); // e.g., "STAAU"

            // MDRO (Multi-Drug Resistant Organism) classification
            $table->boolean('is_mdro')->default(false);
            $table->jsonb('mdro_types')->nullable(); // ['MRSA', 'VRE', 'ESBL', 'CRE', etc.]

            // Antibiotic sensitivity results - JSONB array
            $table->jsonb('antibiotic_results');
            /*
             * [
             *   {"antibiotic": "penicillin", "sensitivity": "R", "mic": 32, "mic_unit": "mcg/ml"},
             *   {"antibiotic": "vancomycin", "sensitivity": "S", "mic": 1, "mic_unit": "mcg/ml"},
             *   {"antibiotic": "ciprofloxacin", "sensitivity": "I", "mic": 2, "mic_unit": "mcg/ml"}
             * ]
             * Sensitivity values: S = Susceptible, I = Intermediate, R = Resistant, SDD = Susceptible Dose-Dependent, NS = Non-susceptible
             */

            // Laboratory information
            $table->string('laboratory_name')->nullable();
            $table->foreignId('ordering_physician_id')->nullable();

            // Clinical notes
            $table->text('clinical_notes')->nullable();
            $table->text('recommendations')->nullable();

            // Verification
            $table->foreignId('created_by')->nullable();
            $table->foreignId('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('patient_id')->references('id')->on('patients')->cascadeOnDelete();
            $table->foreign('ordering_physician_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('verified_by')->references('id')->on('users')->nullOnDelete();

            // Indexes
            $table->index(['tenant_id', 'patient_id']);
            $table->index(['patient_id', 'collection_date']);
            $table->index(['tenant_id', 'is_mdro']);
            $table->index('organism_name');
            $table->index('specimen_source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_amr_tests');
    }
};
