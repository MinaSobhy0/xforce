<?php

namespace App\Jobs;

use App\Models\PlatformEmailCampaignRecipient;
use App\Services\Ai\AiEmailPersonalizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Per-recipient AI render. Runs BEFORE the send job when the campaign
 * has ai_personalize=on. Writes the rendered HTML into the recipient
 * row so retries (or later resends) don't re-charge the LLM provider.
 *
 * On AI failure, falls back to plain token replacement — the send
 * still goes out, just without personalization. Better a generic
 * message than a stuck queue.
 */
class RenderPlatformCampaignBodyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $backoff = [60, 300, 1200]; // 1min, 5min, 20min

    public function __construct(public int $recipientId)
    {
        $this->onConnection('central');
    }

    public function handle(AiEmailPersonalizer $personalizer): void
    {
        $recipient = PlatformEmailCampaignRecipient::with('campaign')->find($this->recipientId);
        if (! $recipient) {
            return;
        }
        if ($recipient->rendered_body_html) {
            SendPlatformCampaignEmailJob::dispatch($recipient->id)->onConnection('central');

            return;
        }
        $campaign = $recipient->campaign;
        if (! $campaign) {
            return;
        }

        $recipient->update(['status' => PlatformEmailCampaignRecipient::STATUS_RENDERING]);

        try {
            $response = $personalizer->personalize(
                brief: (string) $campaign->body_html,
                promptExtra: (string) $campaign->ai_prompt_template,
                recipient: ['email' => $recipient->email, 'name_hint' => $recipient->name_hint],
                context: (array) $recipient->context,
                modelKey: $campaign->ai_model,
                language: (string) ($campaign->language ?: 'en'),
            );

            $recipient->forceFill([
                'rendered_body_html' => $response->text,
                'rendered_at' => now(),
                'ai_tokens_input' => $response->tokensInput,
                'ai_tokens_output' => $response->tokensOutput,
                'ai_cost_usd_cents' => $response->costUsdCents,
            ])->save();

            $campaign->increment('ai_total_cost_usd_cents', (int) $response->costUsdCents);
        } catch (\Throwable $e) {
            Log::warning('AI render failed, falling back to plain body', [
                'campaign_id' => $campaign->id,
                'recipient_id' => $recipient->id,
                'error' => $e->getMessage(),
            ]);

            $recipient->forceFill([
                'rendered_body_html' => $personalizer->fallbackTokenReplace(
                    (string) $campaign->body_html,
                    ['name_hint' => $recipient->name_hint, 'email' => $recipient->email],
                    (array) $recipient->context,
                ),
                'rendered_at' => now(),
            ])->save();
        }

        SendPlatformCampaignEmailJob::dispatch($recipient->id)->onConnection('central');
    }
}
