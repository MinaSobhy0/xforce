<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();

            $table->string('code')->unique(); // REF-2024-000001

            $table->uuid('referral_program_id')->nullable();
            $table->uuid('referrer_patient_id')->index();
            $table->uuid('referred_patient_id')->nullable()->index();

            $table->string('status')->default('pending'); // pending, completed, rewarded, expired, cancelled

            // Rewards tracking
            $table->integer('referrer_points_awarded')->nullable();
            $table->integer('referred_points_awarded')->nullable();
            $table->boolean('referrer_discount_used')->default(false);
            $table->boolean('referred_discount_used')->default(false);

            // First purchase reference
            $table->uuid('first_purchase_invoice_id')->nullable();

            // Timestamps for status transitions
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('rewarded_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            // Foreign keys
            $table->foreign('referral_program_id')
                ->references('id')->on('referral_programs')
                ->nullOnDelete();
            $table->foreign('referrer_patient_id')
                ->references('id')->on('patients')
                ->cascadeOnDelete();
            $table->foreign('referred_patient_id')
                ->references('id')->on('patients')
                ->nullOnDelete();
            $table->foreign('first_purchase_invoice_id')
                ->references('id')->on('invoices')
                ->nullOnDelete();

            // Indexes
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'referrer_patient_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};
