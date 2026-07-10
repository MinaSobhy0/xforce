<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Narrow send audit log. One row per SMTP send attempt from ANY
 * platform-level Mailable (marketing OR transactional). Used to:
 *   1. Prove what left the server (for compliance / audit).
 *   2. Enforce weekly per-address send limits at Phase 6 time.
 *   3. Support 24h duplicate detection.
 *
 * Kept intentionally narrow — no rendered body reproduction; just
 * enough metadata to answer "did we send to X at time T with subject S".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_email_sends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->nullable();
            $table->string('mailable_class')->nullable();
            $table->string('recipient_email');
            $table->string('subject')->nullable();
            $table->jsonb('headers')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->string('status', 20)->default('sent');
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index('recipient_email');
            $table->index(['campaign_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_email_sends');
    }
};
