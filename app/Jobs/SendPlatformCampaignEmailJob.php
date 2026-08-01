<?php

namespace App\Jobs;

use App\Mail\PlatformCampaignMail;
use App\Models\PlatformEmailCampaign;
use App\Models\PlatformEmailCampaignRecipient;
use App\Models\PlatformEmailSend;
use App\Models\PlatformEmailSuppression;
use App\Services\Ai\AiEmailPersonalizer;
use App\Services\PlatformEmail\BodyRewriter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\URL;

/**
 * Actually deliver one email. Runs under a per-second Redis throttle
 * so bulk sends drip out at N/second and don't overwhelm the local
 * Postfix. Config knob:
 *   platform_email.throttle_per_minute (default 60 = 1/sec)
 */
class SendPlatformCampaignEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $backoff = [30, 120, 600];

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
        if (in_array($recipient->status, [
            PlatformEmailCampaignRecipient::STATUS_SENT,
            PlatformEmailCampaignRecipient::STATUS_DELIVERED,
            PlatformEmailCampaignRecipient::STATUS_UNSUBSCRIBED,
            PlatformEmailCampaignRecipient::STATUS_SUPPRESSED,
        ], true)) {
            return;
        }

        $campaign = $recipient->campaign;
        if (! $campaign || $campaign->status === PlatformEmailCampaign::STATUS_CANCELLED) {
            return;
        }

        // Late suppression check (state may have changed between materialize and dispatch).
        if (PlatformEmailSuppression::suppresses($recipient->email)) {
            $recipient->update([
                'status' => PlatformEmailCampaignRecipient::STATUS_SUPPRESSED,
                'failed_at' => now(),
                'error_message' => 'Suppressed at send time',
            ]);
            $campaign->increment('failed_count');

            return;
        }

        // Weekly per-address send limit — prevents blasting the same
        // recipient across multiple back-to-back campaigns.
        $weeklyLimit = (int) config('platform_email.weekly_per_address_limit', 2);
        if ($weeklyLimit > 0) {
            $recentSendCount = PlatformEmailSend::query()
                ->where('recipient_email', $recipient->email)
                ->where('status', PlatformEmailSend::STATUS_SENT)
                ->where('created_at', '>=', now()->subDays(7))
                ->count();
            if ($recentSendCount >= $weeklyLimit) {
                $recipient->update([
                    'status' => PlatformEmailCampaignRecipient::STATUS_SUPPRESSED,
                    'failed_at' => now(),
                    'error_message' => "Weekly limit reached ({$recentSendCount}/{$weeklyLimit})",
                ]);
                $campaign->increment('failed_count');

                return;
            }
        }

        // 24h duplicate guard — same campaign to the same address within
        // 24 hours (job retry after a send already succeeded).
        $dupWithin24h = PlatformEmailSend::query()
            ->where('campaign_id', $campaign->id)
            ->where('recipient_email', $recipient->email)
            ->where('status', PlatformEmailSend::STATUS_SENT)
            ->where('created_at', '>=', now()->subDay())
            ->exists();
        if ($dupWithin24h) {
            $recipient->update([
                'status' => PlatformEmailCampaignRecipient::STATUS_SENT,
                'sent_at' => $recipient->sent_at ?? now(),
            ]);

            return;
        }

        $throttlePerMinute = (int) config('platform_email.throttle_per_minute', 60);
        Redis::throttle('platform-mail-send')
            ->allow($throttlePerMinute)
            ->every(60)
            ->then(function () use ($recipient, $campaign, $personalizer): void {
                $this->send($recipient, $campaign, $personalizer);
            }, function () {
                // Rate-limited — requeue with a small delay so we drain smoothly.
                $this->release(2);
            });
    }

    protected function send(PlatformEmailCampaignRecipient $recipient, PlatformEmailCampaign $campaign, AiEmailPersonalizer $personalizer): void
    {
        $recipient->update(['status' => PlatformEmailCampaignRecipient::STATUS_SENDING]);

        $body = $recipient->rendered_body_html
            ?: $personalizer->fallbackTokenReplace(
                (string) $campaign->body_html,
                ['name_hint' => $recipient->name_hint, 'email' => $recipient->email],
                (array) $recipient->context,
            );

        // Rewrite outbound links → signed click tracker + append open pixel.
        $body = app(BodyRewriter::class)->rewrite($body, $recipient);

        // Signed one-click unsubscribe URL — no auth needed to click.
        $unsubUrl = URL::signedRoute('platform.unsubscribe', [
            'campaign' => $campaign->id,
            'email' => $recipient->email,
        ]);

        try {
            $mailable = new PlatformCampaignMail(
                campaign: $campaign,
                recipient: $recipient,
                renderedBody: $body,
                unsubscribeUrl: $unsubUrl,
            );

            Mail::to($recipient->email)->send($mailable);

            $recipient->forceFill([
                'status' => PlatformEmailCampaignRecipient::STATUS_SENT,
                'sent_at' => now(),
            ])->save();

            $campaign->increment('sent_count');

            PlatformEmailSend::create([
                'campaign_id' => $campaign->id,
                'mailable_class' => PlatformCampaignMail::class,
                'recipient_email' => $recipient->email,
                'subject' => $campaign->subject,
                'status' => PlatformEmailSend::STATUS_SENT,
            ]);

            $this->maybeMarkCampaignSent($campaign);
        } catch (\Throwable $e) {
            Log::warning('Platform campaign send failed', [
                'campaign_id' => $campaign->id,
                'recipient_id' => $recipient->id,
                'error' => $e->getMessage(),
            ]);
            $recipient->forceFill([
                'status' => PlatformEmailCampaignRecipient::STATUS_FAILED,
                'failed_at' => now(),
                'error_message' => mb_substr($e->getMessage(), 0, 500),
            ])->save();
            $campaign->increment('failed_count');
            $this->maybeMarkCampaignSent($campaign);

            PlatformEmailSend::create([
                'campaign_id' => $campaign->id,
                'mailable_class' => PlatformCampaignMail::class,
                'recipient_email' => $recipient->email,
                'subject' => $campaign->subject,
                'status' => PlatformEmailSend::STATUS_FAILED,
                'error' => mb_substr($e->getMessage(), 0, 500),
            ]);

            throw $e;
        }
    }

    /**
     * When the last in-flight recipient of a batch reaches a terminal
     * state, flip the campaign status back to SENT so the composer's
     * Send Now button re-appears for a second batch.
     */
    protected function maybeMarkCampaignSent(PlatformEmailCampaign $campaign): void
    {
        if ($campaign->status !== PlatformEmailCampaign::STATUS_SENDING) {
            return;
        }
        $stillInFlight = $campaign->recipients()
            ->whereIn('status', [
                PlatformEmailCampaignRecipient::STATUS_PENDING,
                PlatformEmailCampaignRecipient::STATUS_RENDERING,
                PlatformEmailCampaignRecipient::STATUS_SENDING,
            ])
            ->exists();
        if ($stillInFlight) {
            return;
        }
        $campaign->forceFill([
            'status' => PlatformEmailCampaign::STATUS_SENT,
            'finished_at' => $campaign->finished_at ?? now(),
        ])->save();
    }
}
