<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreignId('patient_id')->unique();
            $table->string('blood_type', 5)->nullable();
            $table->boolean('is_pregnant')->nullable();
            $table->boolean('is_breastfeeding')->nullable();
            $table->tinyInteger('fitzpatrick_type')->nullable()->comment('Fitzpatrick skin type I-VI');
            $table->jsonb('insurance_info')->nullable();
            $table->enum('status', ['active', 'archived', 'transferred'])->default('active');
            $table->timestamp('profile_created_at')->nullable();
            $table->timestamp('last_reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('patient_id')->references('id')->on('patients')->cascadeOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();

            $table->index('tenant_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_profiles');
    }
};
