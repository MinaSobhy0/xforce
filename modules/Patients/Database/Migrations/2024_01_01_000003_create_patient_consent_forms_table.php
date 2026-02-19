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
        Schema::create('patient_consent_forms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('patient_id')->index();
            $table->uuid('consent_template_id')->index();

            // Template version at time of signing
            $table->string('template_version', 20)->nullable();

            // Signature details
            $table->timestamp('signed_at');
            $table->boolean('signed_by_patient')->default(true);
            $table->text('signature_data')->nullable(); // Base64 or file reference
            $table->enum('signature_type', ['drawn', 'typed', 'digital'])->default('drawn');

            // Witness
            $table->string('witness_name', 200)->nullable();
            $table->text('witness_signature_data')->nullable();

            // Audit trail
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            // Validity
            $table->timestamp('valid_until')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->text('revoked_reason')->nullable();

            // PDF storage
            $table->string('pdf_path')->nullable();

            // Notes
            $table->text('notes')->nullable();

            // Staff who collected consent
            $table->uuid('staff_id')->nullable();

            $table->timestamps();

            // Foreign keys
            $table->foreign('patient_id')
                ->references('id')
                ->on('patients')
                ->cascadeOnDelete();

            $table->foreign('staff_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            // Indexes
            $table->index(['patient_id', 'consent_template_id']);
            $table->index(['patient_id', 'signed_at']);
            $table->index('valid_until');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_consent_forms');
    }
};
