<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create service categories table
        Schema::create('service_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index();
            $table->foreignId('parent_id')->nullable()->index();
            $table->jsonb('name');
            $table->jsonb('description')->nullable();
            $table->string('icon', 100)->nullable();
            $table->string('color', 50)->nullable();
            $table->string('image_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
            $table->index(['tenant_id', 'parent_id', 'sort_order']);
        });

        // Add self-referencing FK after table creation
        Schema::table('service_categories', function (Blueprint $table) {
            $table->foreign('parent_id')
                ->references('id')
                ->on('service_categories')
                ->nullOnDelete();
        });

        // Create services table
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index();
            $table->foreignId('category_id')->nullable()->index();
            $table->foreignId('consent_template_id')->nullable()->index();
            $table->foreignId('parameter_template_id')->nullable();
            $table->string('parameter_mode', 20)->default('none'); // none, template, custom
            $table->boolean('has_dynamic_parameters')->default(false);
            $table->string('code', 30)->index();
            $table->jsonb('name');
            $table->jsonb('description')->nullable();
            $table->jsonb('short_description')->nullable();
            $table->integer('duration_minutes')->default(30);
            $table->integer('buffer_minutes')->default(0);
            $table->integer('base_price_minor')->default(0);
            $table->integer('recommended_sessions')->nullable();
            $table->integer('session_interval_days')->nullable();
            $table->integer('fitzpatrick_min')->nullable();
            $table->integer('fitzpatrick_max')->nullable();
            $table->jsonb('contraindications')->nullable();
            $table->jsonb('pre_care_instructions')->nullable();
            $table->jsonb('post_care_instructions')->nullable();
            $table->jsonb('equipment_required')->nullable();
            $table->jsonb('consumables_required')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('requires_consent')->default(false);
            $table->boolean('is_bookable_online')->default(true);
            $table->jsonb('time_slot_restrictions')->nullable();
            $table->integer('sort_order')->default(0);
            $table->string('image_url')->nullable();
            $table->jsonb('tags')->nullable();
            $table->timestamps();

            $table->foreign('category_id')
                ->references('id')
                ->on('service_categories')
                ->nullOnDelete();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'is_active']);
            $table->index(['tenant_id', 'category_id', 'sort_order']);
            $table->index(['tenant_id', 'is_bookable_online', 'is_active']);
        });

        // Create service branch pricing table
        Schema::create('service_branch_pricing', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index();
            $table->foreignId('service_id')->index();
            $table->foreignId('branch_id')->index();
            $table->integer('price_minor');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('service_id')
                ->references('id')
                ->on('services')
                ->cascadeOnDelete();

            $table->foreign('branch_id')
                ->references('id')
                ->on('branches')
                ->cascadeOnDelete();

            $table->unique(['service_id', 'branch_id']);
        });

        // Create consent templates table
        Schema::create('consent_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index();
            $table->jsonb('name');
            $table->jsonb('content');
            $table->boolean('is_active')->default(true);
            $table->boolean('requires_signature')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });

        // Add consent template FK to services
        Schema::table('services', function (Blueprint $table) {
            $table->foreign('consent_template_id')
                ->references('id')
                ->on('consent_templates')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropForeign(['consent_template_id']);
        });
        Schema::dropIfExists('consent_templates');
        Schema::dropIfExists('service_branch_pricing');
        Schema::dropIfExists('services');
        Schema::dropIfExists('service_categories');
    }
};
