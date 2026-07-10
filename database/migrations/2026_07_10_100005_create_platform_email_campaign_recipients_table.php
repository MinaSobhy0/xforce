<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Frozen recipient snapshot for a campaign. Created at Send Now / when
 * the scheduler fires ({@see PlatformEmailCampaign::materialize()}), NOT
 * at compose time — so a member unsubscribing between "schedule" and
 * "actually send" is respected, and members added later don't get
 * retroactively targeted.
 *
 * context_jsonb holds the tenant/plan facts used by the AI personalizer
 * (name_hint, tenant_name, plan_code, branch_count, days_since_signup,
 * …). rendered_body_html is populated by RenderPlatformCampaignBodyJob
 * exactly once — SendPlatformCampaignEmailJob reads it verbatim.
 *
 * status flow:
 *   pending → rendering → sending → sent → delivered
 *   pending → skipped (suppressed / unsubscribed at send-time)
 *   any → failed (SMTP threw)
 *   sent → bounced (bounce landed later)
 *   sent → unsubscribed (recipient clicked List-Unsubscribe)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_email_campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('platform_email_campaigns')->cascadeOnDelete();
            $table->string('email');
            $table->string('name_hint')->nullable();

            $table->string('status', 20)->default('pending');
            $table->jsonb('context')->nullable();
            $table->longText('rendered_body_html')->nullable();
            $table->timestamp('rendered_at')->nullable();
            $table->integer('ai_tokens_input')->nullable();
            $table->integer('ai_tokens_output')->nullable();
            $table->integer('ai_cost_usd_cents')->nullable();

            $table->string('provider_message_id')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('first_clicked_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error_message')->nullable();

            $table->timestamps();

            $table->unique(['campaign_id', 'email']);
            $table->index(['campaign_id', 'status']);
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_email_campaign_recipients');
    }
};
