<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Denormalized preview text for the inbox list so the table renders
 * without a JOIN-per-row to whatsapp_messages — InboundMessageProcessor
 * and the outbound mirror in WhatsAppService keep it fresh.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            $table->string('last_message_preview', 280)->nullable()->after('last_inbound_at');
            $table->string('last_message_direction', 10)->nullable()->after('last_message_preview');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            $table->dropColumn(['last_message_preview', 'last_message_direction']);
        });
    }
};
