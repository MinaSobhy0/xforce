<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform-managed WhatsApp template catalog (PUBLIC schema).
 *
 * The platform admin authors a master template here. Tenants browse
 * the catalog and "adopt" a template — the adoption flow clones it
 * into the tenant's message_templates and submits to their WABA via
 * TemplateSyncService::submit().
 *
 * Meta requires per-WABA approval even when bodies are identical, so
 * this table only stores the *blueprint* — not a synced state.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_whatsapp_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100)->unique(); // slug used as Meta's name (e.g. appointment_reminder)
            $table->jsonb('name'); // i18n display name for admins
            $table->jsonb('description')->nullable(); // i18n admin notes (when to use, etc.)
            $table->string('category', 32); // MARKETING|UTILITY|AUTHENTICATION
            $table->jsonb('body'); // i18n body text with {{var}} placeholders
            $table->string('header_type', 16)->default('none'); // none|text|image|document
            $table->jsonb('header_content')->nullable();
            $table->jsonb('footer')->nullable(); // i18n footer text
            $table->jsonb('buttons_json')->nullable(); // [{label, action, custom_payload}]
            $table->jsonb('variables_json')->nullable(); // {patient_name: "John Doe", date: "2026-06-01"} example values
            $table->string('default_language', 16)->default('en_US');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_whatsapp_templates');
    }
};
