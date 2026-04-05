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
        Schema::create('face_chart_markers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->index();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();

            // Links to source records (optional)
            $table->foreignId('treatment_plan_item_id')->nullable()
                ->constrained('treatment_plan_items')
                ->nullOnDelete();
            $table->foreignId('appointment_id')->nullable()
                ->constrained('appointments')
                ->nullOnDelete();
            $table->foreignId('service_id')->nullable()
                ->constrained('services')
                ->nullOnDelete();

            // 3D coordinates (normalized -1 to 1)
            $table->decimal('x', 10, 6);
            $table->decimal('y', 10, 6);
            $table->decimal('z', 10, 6);

            // Face region for filtering
            $table->string('face_region', 50)->nullable()->index();

            // Procedure details
            $table->string('marker_type', 30)->default('injection');
            $table->string('product_name', 255)->nullable();
            $table->decimal('units', 8, 2)->nullable();
            $table->string('unit_type', 20)->nullable();

            // Visual properties
            $table->string('color', 7)->default('#FF6B6B');
            $table->decimal('size', 4, 2)->default(1.0);

            // Metadata
            $table->text('notes')->nullable();
            $table->jsonb('metadata')->nullable();

            // Audit
            $table->foreignId('performed_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->date('performed_at')->nullable();
            $table->timestamps();

            // Indexes for common queries
            $table->index('patient_id');
            $table->index(['patient_id', 'performed_at']);
            $table->index(['patient_id', 'face_region']);
            $table->index(['patient_id', 'appointment_id']);
            $table->index('marker_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('face_chart_markers');
    }
};
