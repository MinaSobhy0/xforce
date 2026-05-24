<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Both inbound (captured from Meta webhooks) and outbound (written by
 * WhatsAppService after a successful send) messages live in this single
 * table so the inbox thread can render the full conversation chronology.
 *
 * notification_log_id bridges outbound rows back to the existing
 * notification_logs entry — campaign/reminder dashboards keep working
 * unchanged; we only add the inbox layer on top.
 *
 * wamid (Meta's message id) is unique on inbound rows to enforce webhook
 * idempotency. Outbound rows may have NULL wamid if the send failed
 * before Meta accepted it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index();
            $table->foreignId('conversation_id')->constrained('whatsapp_conversations')->cascadeOnDelete();
            $table->string('wamid', 128)->nullable();
            $table->string('direction', 10); // inbound | outbound
            $table->string('type', 20); // text|image|document|video|audio|interactive|button_reply|template|reaction|location|unknown
            $table->text('body')->nullable();
            $table->string('media_id', 128)->nullable();
            $table->string('media_path', 512)->nullable();
            $table->string('media_mime', 100)->nullable();
            $table->string('media_filename', 255)->nullable();
            $table->string('media_sha256', 64)->nullable();
            $table->jsonb('interactive_payload')->nullable();
            $table->string('context_wamid', 128)->nullable();
            $table->string('status', 20)->nullable(); // outbound only: queued|sent|delivered|read|failed
            $table->foreignId('template_id')->nullable();
            $table->foreignId('notification_log_id')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->unique('wamid', 'whatsapp_messages_wamid_unique');
            $table->index(['conversation_id', 'created_at'], 'whatsapp_messages_thread_idx');
            $table->index(['tenant_id', 'direction', 'status']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
