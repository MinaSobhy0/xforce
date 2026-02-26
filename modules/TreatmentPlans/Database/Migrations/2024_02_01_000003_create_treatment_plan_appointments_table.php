<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treatment_plan_appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index();
            $table->foreignId('treatment_plan_item_id')->index();
            $table->foreignId('appointment_id')->index();

            // Session tracking (e.g., "Session 3 of 6")
            $table->integer('session_number')->default(1);

            // Status mirrors appointment status for quick lookups
            $table->string('status')->default('scheduled'); // scheduled, confirmed, completed, cancelled, no_show

            $table->text('notes')->nullable();

            $table->timestamps();

            // Foreign keys
            $table->foreign('treatment_plan_item_id')->references('id')->on('treatment_plan_items')->cascadeOnDelete();
            $table->foreign('appointment_id')->references('id')->on('appointments')->cascadeOnDelete();

            // Unique constraint: one appointment can only belong to one treatment plan item
            $table->unique('appointment_id');

            // Indexes
            $table->index(['tenant_id', 'treatment_plan_item_id']);
            $table->index(['tenant_id', 'status']);
            $table->index(['treatment_plan_item_id', 'session_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treatment_plan_appointments');
    }
};
