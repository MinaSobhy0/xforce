<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per (remote phone, our receiving phone_number_id) — the grouping
 * unit for the WhatsApp inbox. patient_id is nullable and backfilled later
 * by a phone-match job; an inbound message from an unknown number still
 * creates the conversation row so it surfaces in the inbox.
 *
 * last_inbound_at gates the 24-hour customer-service window for free-form
 * replies: WhatsAppService::canSendFreeForm() reads it; the inbox UI swaps
 * the reply textarea for a template picker when it's older than 24h.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index();
            $table->foreignId('patient_id')->nullable();
            $table->string('remote_phone_e164', 32);
            $table->string('remote_display_name', 255)->nullable();
            $table->string('phone_number_id', 64);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('last_inbound_at')->nullable();
            $table->unsignedInteger('unread_count')->default(0);
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->unique(['remote_phone_e164', 'phone_number_id'], 'whatsapp_conversations_remote_phone_unique');
            $table->index(['tenant_id', 'last_message_at']);
            $table->index(['tenant_id', 'patient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_conversations');
    }
};
