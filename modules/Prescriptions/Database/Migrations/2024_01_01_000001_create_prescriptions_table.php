<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index();
            $table->string('prescription_number')->index();
            $table->foreignId('patient_id')->index();
            $table->foreignId('prescriber_id')->index();
            $table->foreignId('appointment_id')->nullable()->index();
            $table->foreignId('branch_id')->index();

            // Clinical info
            $table->text('diagnosis')->nullable();
            $table->text('notes')->nullable();

            // Status
            $table->string('status')->default('draft')->index();

            // Dates
            $table->datetime('issued_at')->nullable();
            $table->date('valid_until')->nullable();

            // Print tracking
            $table->boolean('is_printed')->default(false);
            $table->integer('print_count')->default(0);
            $table->datetime('last_printed_at')->nullable();

            // Finalization
            $table->foreignId('finalized_by')->nullable();
            $table->datetime('finalized_at')->nullable();

            // Cancellation
            $table->foreignId('cancelled_by')->nullable();
            $table->datetime('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Composite indexes
            $table->index(['tenant_id', 'prescription_number']);
            $table->index(['tenant_id', 'patient_id']);
            $table->index(['tenant_id', 'prescriber_id']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'issued_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescriptions');
    }
};
