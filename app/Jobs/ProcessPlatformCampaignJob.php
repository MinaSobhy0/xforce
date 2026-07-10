<?php

namespace App\Jobs;

use App\Models\PlatformEmailCampaign;
use App\Models\PlatformEmailCampaignRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Orchestrator. Materializes recipients if not already, then fans out
 * one job per recipient — RenderPlatformCampaignBodyJob first if AI is
 * enabled (chains SendPlatformCampaignEmailJob after), otherwise
 * SendPlatformCampaignEmailJob directly.
 */
class ProcessPlatformCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;

    public function __construct(public int $campaignId)
    {
        // Central queue — job row lives in public.jobs, not tenant_x.jobs.
        $this->onConnection('central');
    }

    public function handle(): void
    {
        $campaign = PlatformEmailCampaign::find($this->campaignId);
        if (! $campaign) {
            return;
        }

        if ($campaign->status === PlatformEmailCampaign::STATUS_CANCELLED) {
            return;
        }

        // Advance status. `started_at` records first fan-out.
        $campaign->forceFill([
            'status' => PlatformEmailCampaign::STATUS_SENDING,
            'started_at' => $campaign->started_at ?? now(),
        ])->save();

        $campaign->materialize();

        $campaign->recipients()
            ->where('status', PlatformEmailCampaignRecipient::STATUS_PENDING)
            ->chunkById(200, function ($chunk) use ($campaign): void {
                foreach ($chunk as $recipient) {
                    if ($campaign->ai_personalize) {
                        RenderPlatformCampaignBodyJob::dispatch($recipient->id)->onConnection('central');
                    } else {
                        SendPlatformCampaignEmailJob::dispatch($recipient->id)->onConnection('central');
                    }
                }
            });
    }
}
