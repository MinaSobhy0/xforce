<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->nullable()->index();
            $table->string('code')->unique();
            $table->foreignUuid('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignUuid('service_id')->constrained('services')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignUuid('practitioner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->uuid('equipment_id')->nullable(); // FK added when Equipment module exists
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time')->nullable();
            $table->integer('duration_minutes');
            $table->string('status')->default('scheduled');
            $table->integer('price_minor')->default(0);
            $table->integer('discount_minor')->default(0);
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->uuid('rescheduled_from_id')->nullable();
            $table->string('source')->default('phone');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('patient_id');
            $table->index('service_id');
            $table->index('branch_id');
            $table->index('practitioner_id');
            $table->index('date');
            $table->index('status');
            $table->index('source');
            $table->index(['date', 'branch_id']);
            $table->index(['date', 'practitioner_id']);
            $table->index(['date', 'status']);
            $table->index(['patient_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
