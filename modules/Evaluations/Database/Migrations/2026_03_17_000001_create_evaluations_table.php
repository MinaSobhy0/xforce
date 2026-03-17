<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index();
            $table->foreignId('branch_id')->index();
            $table->foreignId('visit_id')->unique();
            $table->foreignId('patient_id')->index();
            $table->foreignId('evaluated_by')->nullable();

            // Rating fields (1-5 scale)
            $table->tinyInteger('overall_rating');
            $table->tinyInteger('service_quality_rating')->nullable();
            $table->tinyInteger('staff_friendliness_rating')->nullable();
            $table->tinyInteger('cleanliness_rating')->nullable();
            $table->tinyInteger('wait_time_rating')->nullable();
            $table->tinyInteger('value_for_money_rating')->nullable();

            // NPS (0-10 scale)
            $table->tinyInteger('satisfaction_score')->nullable();
            $table->boolean('would_recommend')->nullable();

            // Feedback
            $table->text('feedback_text')->nullable();
            $table->text('improvement_suggestions')->nullable();

            // Source tracking
            $table->string('source', 20)->default('staff');

            // Extensibility
            $table->jsonb('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Foreign key constraints
            $table->foreign('visit_id')
                ->references('id')
                ->on('visits')
                ->restrictOnDelete();

            $table->foreign('patient_id')
                ->references('id')
                ->on('patients')
                ->restrictOnDelete();

            $table->foreign('evaluated_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            // Performance indexes
            $table->index(['tenant_id', 'branch_id', 'created_at']);
            $table->index(['tenant_id', 'overall_rating']);
            $table->index(['patient_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluations');
    }
};
