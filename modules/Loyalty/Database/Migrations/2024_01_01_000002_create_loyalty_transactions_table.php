<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();

            $table->uuid('patient_id')->index();
            $table->uuid('loyalty_rule_id')->nullable();

            $table->string('type'); // earn, redeem, expire, adjust, refund, bonus, referral
            $table->integer('points'); // Positive for credits, negative for debits
            $table->integer('running_balance'); // Balance after this transaction

            $table->text('description')->nullable();

            // Polymorphic reference (invoice, payment, appointment, etc.)
            $table->string('reference_type')->nullable();
            $table->uuid('reference_id')->nullable();

            // Additional metadata
            $table->jsonb('metadata')->nullable();

            // Points expiry (for earned points)
            $table->timestamp('expires_at')->nullable();

            $table->uuid('created_by_user_id')->nullable();

            $table->timestamps();

            // Foreign keys
            $table->foreign('patient_id')
                ->references('id')->on('patients')
                ->cascadeOnDelete();
            $table->foreign('loyalty_rule_id')
                ->references('id')->on('loyalty_rules')
                ->nullOnDelete();
            $table->foreign('created_by_user_id')
                ->references('id')->on('users')
                ->nullOnDelete();

            // Indexes
            $table->index(['tenant_id', 'patient_id']);
            $table->index(['tenant_id', 'type']);
            $table->index(['reference_type', 'reference_id']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_transactions');
    }
};
