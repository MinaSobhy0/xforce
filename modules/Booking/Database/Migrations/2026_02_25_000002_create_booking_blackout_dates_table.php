<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_blackout_dates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('branch_id')->nullable(); // NULL = all branches

            $table->string('name', 100);

            // Date configuration
            $table->date('start_date');
            $table->date('end_date'); // Same as start_date for single day

            // Recurrence
            $table->boolean('is_recurring')->default(false);
            $table->enum('recurrence_type', ['yearly', 'monthly'])->nullable();

            // Scope
            $table->boolean('affects_online_booking')->default(true);
            $table->boolean('affects_staff_booking')->default(false); // Staff can still book

            $table->text('reason')->nullable();
            $table->boolean('is_active')->default(true);

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();

            // Indexes
            $table->index(['tenant_id', 'is_active']);
            $table->index(['tenant_id', 'start_date', 'end_date']);
            $table->index(['tenant_id', 'branch_id', 'start_date']);
            $table->index('is_recurring');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_blackout_dates');
    }
};
