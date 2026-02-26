<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index();

            $table->jsonb('name');
            $table->jsonb('description')->nullable();

            // Rewards configuration
            $table->integer('referrer_points')->default(100); // Points for the referrer
            $table->integer('referred_points')->default(50); // Points for the referred
            $table->decimal('referrer_discount_percentage', 5, 2)->nullable(); // Optional discount for referrer
            $table->decimal('referred_discount_percentage', 5, 2)->nullable(); // Optional discount for referred

            // Requirements
            $table->integer('min_purchase_minor')->nullable(); // Minimum purchase to qualify
            $table->integer('max_referrals_per_patient')->nullable(); // Cap referrals per referrer
            $table->boolean('require_first_purchase')->default(true); // Require referred to make purchase

            // Conditions (JSON for flexible rules)
            $table->jsonb('conditions')->nullable();

            $table->boolean('is_active')->default(true);

            // Time-limited programs
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_programs');
    }
};
