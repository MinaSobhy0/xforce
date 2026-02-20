<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();

            $table->jsonb('name');
            $table->jsonb('description')->nullable();
            $table->string('type'); // per_spend, per_visit, referral, birthday, signup, first_purchase

            // Points configuration
            $table->integer('points_amount')->nullable(); // Fixed points for non-spend rules
            $table->decimal('points_per_currency_unit', 10, 2)->nullable(); // Points per currency for spend rules
            $table->integer('min_spend_minor')->nullable(); // Minimum spend to qualify
            $table->integer('max_points_per_transaction')->nullable(); // Cap points per transaction

            // Treatment/Category specific rules
            $table->uuid('treatment_id')->nullable();
            $table->uuid('treatment_category_id')->nullable();

            // Multiplier (for tiers or promotions)
            $table->decimal('multiplier', 5, 2)->default(1.0);

            // Conditions (JSON for flexible rules)
            $table->jsonb('conditions')->nullable();

            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(0);

            // Time-limited rules
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('treatment_id')
                ->references('id')->on('treatments')
                ->nullOnDelete();
            $table->foreign('treatment_category_id')
                ->references('id')->on('treatment_categories')
                ->nullOnDelete();

            // Indexes
            $table->index(['tenant_id', 'type']);
            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_rules');
    }
};
