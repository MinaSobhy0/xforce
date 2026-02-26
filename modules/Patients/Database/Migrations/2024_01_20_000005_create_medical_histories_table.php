<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id'); // No FK - tenants table is in public schema
            $table->foreignId('medical_profile_id');
            $table->enum('history_type', ['medical_condition', 'surgery', 'hospitalization', 'family_history', 'social_history']);
            $table->string('name');
            $table->text('description')->nullable();
            $table->date('onset_date')->nullable();
            $table->date('resolved_date')->nullable();
            $table->boolean('is_ongoing')->default(false);
            $table->enum('severity', ['mild', 'moderate', 'severe'])->nullable();
            $table->string('family_relationship')->nullable()->comment('For family history type');
            $table->string('frequency')->nullable()->comment('For social history');
            $table->string('quantity')->nullable()->comment('For social history');
            $table->boolean('affects_treatment')->default(false);
            $table->text('treatment_implications')->nullable();
            $table->boolean('verified_by_doctor')->default(false);
            $table->foreignId('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // No FK for tenant_id - tenants table is in public schema
            $table->foreign('medical_profile_id')->references('id')->on('medical_profiles')->cascadeOnDelete();
            $table->foreign('verified_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['medical_profile_id', 'history_type']);
            $table->index('is_ongoing');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_histories');
    }
};
