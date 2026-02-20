<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_treatment_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->nullable()->index();
            $table->foreignUuid('appointment_id')->constrained('appointments')->cascadeOnDelete();
            $table->jsonb('areas_treated')->nullable();
            $table->jsonb('machine_settings')->nullable();
            $table->string('skin_reaction')->nullable();
            $table->string('patient_comfort')->nullable();
            $table->integer('shots_fired')->nullable();
            $table->text('notes')->nullable();
            $table->jsonb('post_care_given')->nullable();
            $table->boolean('follow_up_recommended')->default(false);
            $table->integer('follow_up_days')->nullable();
            $table->foreignUuid('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Indexes
            $table->index('appointment_id');
            $table->index('skin_reaction');
            $table->index('follow_up_recommended');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_treatment_notes');
    }
};
