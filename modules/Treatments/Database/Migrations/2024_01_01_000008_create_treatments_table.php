<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treatments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('category_id')->nullable()->index();
            $table->uuid('consent_template_id')->nullable()->index();

            $table->string('code', 20)->index();
            $table->jsonb('name');
            $table->jsonb('description')->nullable();
            $table->jsonb('short_description')->nullable();

            // Duration and scheduling
            $table->integer('duration_minutes')->default(30);
            $table->integer('buffer_minutes')->default(0);
            $table->integer('recommended_sessions')->nullable();
            $table->integer('session_interval_days')->nullable();

            // Pricing (in minor units - piasters for EGP)
            $table->integer('base_price_minor')->default(0);

            // Safety parameters
            $table->smallInteger('fitzpatrick_min')->nullable()->comment('Minimum safe Fitzpatrick skin type (1-6)');
            $table->smallInteger('fitzpatrick_max')->nullable()->comment('Maximum safe Fitzpatrick skin type (1-6)');
            $table->jsonb('contraindications')->nullable()->comment('List of contraindication keys');

            // Instructions (translatable)
            $table->jsonb('pre_care_instructions')->nullable();
            $table->jsonb('post_care_instructions')->nullable();

            // Resources
            $table->jsonb('equipment_required')->nullable();
            $table->jsonb('consumables_required')->nullable();

            // Flags
            $table->boolean('is_active')->default(true);
            $table->boolean('requires_consent')->default(false);
            $table->boolean('is_bookable_online')->default(true);

            // Display
            $table->integer('sort_order')->default(0);
            $table->string('image_url')->nullable();
            $table->jsonb('tags')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('category_id')
                ->references('id')
                ->on('treatment_categories')
                ->onDelete('set null');

            $table->foreign('consent_template_id')
                ->references('id')
                ->on('consent_templates')
                ->onDelete('set null');

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'is_active']);
            $table->index(['tenant_id', 'category_id', 'sort_order']);
            $table->index(['tenant_id', 'is_bookable_online', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treatments');
    }
};
