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
        Schema::create('patient_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->index();
            $table->foreignId('appointment_id')->nullable()->index();
            $table->foreignId('treatment_id')->nullable()->index();

            // Photo classification
            $table->enum('type', ['before', 'after', 'during', 'consultation', 'progress', 'reaction'])->default('consultation');
            $table->string('body_area', 50)->nullable()->index();

            // Description
            $table->text('description')->nullable();

            // Capture details
            $table->timestamp('taken_at')->nullable();
            $table->foreignId('taken_by')->nullable();

            // Privacy
            $table->boolean('is_private')->default(false);

            // Tags and metadata
            $table->jsonb('tags')->nullable();
            $table->jsonb('metadata')->nullable();

            $table->timestamps();

            // Foreign keys
            $table->foreign('patient_id')
                ->references('id')
                ->on('patients')
                ->cascadeOnDelete();

            $table->foreign('taken_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            // Indexes
            $table->index(['patient_id', 'type']);
            $table->index(['patient_id', 'body_area']);
            $table->index(['patient_id', 'treatment_id']);
            $table->index(['patient_id', 'taken_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_photos');
    }
};
