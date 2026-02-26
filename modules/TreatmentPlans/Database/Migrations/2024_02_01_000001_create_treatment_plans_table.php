<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treatment_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index();
            $table->string('code')->index();
            $table->foreignId('patient_id')->index();
            $table->foreignId('branch_id')->index();
            $table->foreignId('created_by_user_id')->nullable()->index();

            // Basic info
            $table->jsonb('name');
            $table->jsonb('description')->nullable();

            // Status workflow: draft -> active -> completed/cancelled
            // Can also be paused while active
            $table->string('status')->default('draft'); // draft, active, paused, completed, cancelled

            // Dates
            $table->date('start_date')->nullable();
            $table->date('target_end_date')->nullable();
            $table->date('actual_end_date')->nullable();

            // Source of treatment plan
            $table->string('source')->default('manual'); // manual, consultation

            // Package reference (optional)
            // recommended_package_id: The Package template suggested for this plan
            $table->foreignId('recommended_package_id')->nullable()->index();
            // package_subscription_id: Set when patient purchases the package
            $table->foreignId('package_subscription_id')->nullable()->index();

            // Notes
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();

            // Status timestamps
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('patient_id')->references('id')->on('patients')->cascadeOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->cascadeOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('recommended_package_id')->references('id')->on('packages')->nullOnDelete();
            $table->foreign('package_subscription_id')->references('id')->on('package_subscriptions')->nullOnDelete();

            // Unique code per tenant
            $table->unique(['tenant_id', 'code']);

            // Common query indexes
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'patient_id', 'status']);
            $table->index(['tenant_id', 'branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treatment_plans');
    }
};
