<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A single platform-marketing campaign — one composed message that
 * fans out to every non-suppressed member of the target list.
 *
 * body_html is the AUTHOR's copy. When ai_personalize=true, it's
 * treated as a brief and the LLM rewrites it per-recipient at send
 * time; the rewritten HTML lives on the recipient row so retries
 * don't re-charge the AI provider.
 *
 * The counter columns (sent_count, delivered_count, …) are updated by
 * SendPlatformCampaignEmailJob and the tracking routes. They exist on
 * the campaign row for fast list-view display; the source of truth
 * for individual events is platform_email_campaign_recipients.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_email_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('subject');
            $table->string('preheader')->nullable();
            $table->string('from_name')->nullable();
            $table->string('reply_to')->nullable();
            $table->longText('body_html')->nullable();
            $table->longText('body_text')->nullable();
            $table->foreignId('list_id')->constrained('platform_email_lists');

            $table->string('status', 20)->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->integer('sent_count')->default(0);
            $table->integer('delivered_count')->default(0);
            $table->integer('opened_count')->default(0);
            $table->integer('clicked_count')->default(0);
            $table->integer('bounced_count')->default(0);
            $table->integer('unsubscribed_count')->default(0);
            $table->integer('complained_count')->default(0);
            $table->integer('failed_count')->default(0);

            // AI personalization
            $table->boolean('ai_personalize')->default(false);
            $table->string('ai_model', 100)->nullable();
            $table->text('ai_prompt_template')->nullable();
            $table->boolean('ai_use_batch_api')->default(false);
            $table->integer('ai_total_cost_usd_cents')->default(0);

            $table->foreignId('created_by_user_id')->nullable();
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
            $table->index('list_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_email_campaigns');
    }
};
