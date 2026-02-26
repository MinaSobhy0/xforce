<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->jsonb('name'); // Translatable
            $table->jsonb('description')->nullable(); // Translatable
            $table->string('category', 50)->default('operations'); // core, operations, financial, sales, marketing, advanced
            $table->string('icon_emoji', 10)->nullable();
            $table->string('tier', 50)->default('free'); // free, starter, professional, enterprise, addon
            $table->integer('addon_price_monthly_minor')->nullable(); // Price if sold as add-on
            $table->boolean('is_core')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_beta')->default(false);
            $table->integer('sort_order')->default(0);
            $table->jsonb('dependencies')->nullable(); // Module codes this depends on
            $table->jsonb('settings_schema')->nullable(); // JSON schema for module settings

            $table->timestamps();
            $table->softDeletes();

            $table->index(['category', 'is_active']);
            $table->index(['tier', 'is_active']);
            $table->index(['sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};
