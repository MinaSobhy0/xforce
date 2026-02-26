<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index();
            $table->foreignId('patient_id')->index();
            $table->foreignId('membership_id')->index();
            $table->string('billing_cycle')->default('monthly'); // monthly, yearly
            $table->string('status')->default('active'); // active, expired, cancelled, frozen
            $table->timestamp('started_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->boolean('auto_renew')->default(true);
            $table->foreignId('renewal_invoice_id')->nullable();
            $table->timestamp('frozen_at')->nullable();
            $table->timestamp('frozen_until')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('patient_id')->references('id')->on('patients')->cascadeOnDelete();
            $table->foreign('membership_id')->references('id')->on('memberships')->cascadeOnDelete();
            $table->foreign('renewal_invoice_id')->references('id')->on('invoices')->nullOnDelete();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'patient_id', 'status']);
            $table->unique(['patient_id', 'membership_id', 'deleted_at'], 'unique_patient_membership');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_subscriptions');
    }
};
