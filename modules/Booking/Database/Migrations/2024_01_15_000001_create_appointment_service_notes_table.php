<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_service_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->index();
            $table->foreignId('appointment_id')->constrained('appointments')->cascadeOnDelete();
            $table->jsonb('areas_treated')->nullable();
            $table->jsonb('machine_settings')->nullable();
            $table->string('skin_reaction')->nullable();
            $table->string('patient_comfort')->nullable();
            $table->integer('shots_fired')->nullable();
            $table->text('notes')->nullable();
            $table->jsonb('post_care_given')->nullable();
            $table->boolean('follow_up_recommended')->default(false);
            $table->integer('follow_up_days')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('appointment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_service_notes');
    }
};
