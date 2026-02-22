<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treatment_plan_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('treatment_plan_id')->index();
            $table->uuid('service_id')->index();

            // Session tracking
            $table->integer('recommended_sessions')->default(1);
            $table->integer('completed_sessions')->default(0);

            // Scheduling preferences
            $table->integer('session_interval_days')->nullable();
            $table->uuid('preferred_practitioner_id')->nullable()->index();
            $table->jsonb('preferred_day_of_week')->nullable(); // Array of days [0-6]
            $table->string('preferred_time_slot')->nullable(); // morning, afternoon, evening

            // Status: pending -> in_progress -> completed/cancelled
            $table->string('status')->default('pending'); // pending, in_progress, completed, cancelled

            $table->integer('sort_order')->default(0);
            $table->text('notes')->nullable();

            $table->timestamps();

            // Foreign keys
            $table->foreign('treatment_plan_id')->references('id')->on('treatment_plans')->cascadeOnDelete();
            $table->foreign('service_id')->references('id')->on('services')->cascadeOnDelete();
            $table->foreign('preferred_practitioner_id')->references('id')->on('users')->nullOnDelete();

            // Indexes
            $table->index(['tenant_id', 'treatment_plan_id']);
            $table->index(['tenant_id', 'status']);
            $table->index(['treatment_plan_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treatment_plan_items');
    }
};
