<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memberships', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->jsonb('name');
            $table->jsonb('description')->nullable();
            $table->string('tier')->default('silver'); // silver, gold, platinum, diamond
            $table->integer('price_monthly_minor');
            $table->integer('price_yearly_minor');
            $table->integer('discount_percentage')->default(0);
            $table->jsonb('included_sessions_monthly')->nullable(); // {treatment_id: quantity}
            $table->float('loyalty_multiplier')->default(1.0);
            $table->boolean('priority_booking')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'is_active']);
            $table->index(['tenant_id', 'tier']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};
