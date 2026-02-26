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
        Schema::create('patient_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->index();
            $table->foreignId('appointment_id')->nullable()->index();

            // Note type
            $table->enum('type', [
                'clinical',
                'administrative',
                'follow_up',
                'complaint',
                'consultation',
                'treatment',
                'prescription',
                'referral'
            ])->default('clinical');

            // Content
            $table->string('subject', 255)->nullable();
            $table->text('content');

            // Flags
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_private')->default(false);
            $table->boolean('is_alert')->default(false);
            $table->timestamp('alert_until')->nullable();

            // Creator
            $table->foreignId('created_by')->nullable();

            // Attachments
            $table->jsonb('attachments')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('patient_id')
                ->references('id')
                ->on('patients')
                ->cascadeOnDelete();

            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            // Indexes
            $table->index(['patient_id', 'type']);
            $table->index(['patient_id', 'is_pinned']);
            $table->index(['patient_id', 'is_alert']);
            $table->index(['patient_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_notes');
    }
};
