<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_contraindications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('medical_profile_id');
            $table->enum('contraindication_type', ['absolute', 'relative', 'temporary']);
            $table->string('name');
            $table->text('description')->nullable();
            $table->jsonb('affected_services')->nullable()->comment('Array of service IDs');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->enum('source', ['patient_reported', 'doctor_identified', 'system_derived'])->default('doctor_identified');
            $table->uuid('identified_by')->nullable();
            $table->timestamp('identified_at')->nullable();
            $table->boolean('show_booking_alert')->default(true);
            $table->boolean('block_booking')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('medical_profile_id')->references('id')->on('medical_profiles')->cascadeOnDelete();
            $table->foreign('identified_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['medical_profile_id', 'is_active']);
            $table->index('contraindication_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_contraindications');
    }
};
