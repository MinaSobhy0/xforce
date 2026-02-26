<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->string('plan_name');
            $table->string('plan_code');
            $table->enum('status', ['active', 'expired', 'canceled', 'suspended', 'pending'])->default('pending');
            $table->decimal('price', 10, 2);
            $table->string('currency', 3)->default('EGP');
            $table->enum('billing_cycle', ['monthly', 'quarterly', 'yearly'])->default('monthly');
            $table->timestamp('started_at');
            $table->timestamp('expires_at');
            $table->boolean('auto_renew')->default(true);
            $table->json('features')->nullable();
            $table->json('limits')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('payment_provider')->nullable();
            $table->string('payment_provider_id')->nullable();
            $table->timestamp('last_payment_at')->nullable();
            $table->timestamp('next_payment_at')->nullable();
            $table->integer('failed_payments_count')->default(0);
            $table->timestamp('grace_period_ends_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // No FK to tenants - schema isolation handles tenant context
            $table->index(['tenant_id']);
            $table->index(['status']);
            $table->index(['expires_at']);
            $table->index(['next_payment_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_subscriptions');
    }
};