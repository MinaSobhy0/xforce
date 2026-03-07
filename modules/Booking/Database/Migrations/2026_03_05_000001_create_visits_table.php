<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index(); // No FK - tenants table is in public schema
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->string('code')->unique();

            // Check-in details
            $table->timestamp('check_in_at');
            $table->foreignId('checked_in_by')->nullable()->constrained('users')->nullOnDelete();

            // Check-out details
            $table->timestamp('check_out_at')->nullable();
            $table->foreignId('checked_out_by')->nullable()->constrained('users')->nullOnDelete();

            // Status and financials
            $table->string('status')->default('open'); // open, completed, invoiced, cancelled
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->bigInteger('total_minor')->default(0);

            // Additional info
            $table->string('source')->nullable(); // walk_in, appointment, online
            $table->text('chief_complaint')->nullable(); // Reason for visit
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'patient_id']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'check_in_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visits');
    }
};
