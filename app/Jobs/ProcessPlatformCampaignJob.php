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

    /**
     * @param  ?int  $limit  Max new recipients to materialize this run.
     *                       NULL = whole list. Used for canary batches:
     *                       send 5 first, review results, then re-run
     *                       Send Now to email the remaining members.
     * @param  bool  $renderOnly  When true, materialize + AI-render each
     *                            recipient's body, then stop. No SendJobs
     *                            dispatched. Non-AI campaigns ignore this
     *                            flag (nothing to render, materialize alone).
     */
    public function __construct(public int $campaignId, public ?int $limit = null, public bool $renderOnly = false)
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

        $campaign->materialize($this->limit);

        $campaign->recipients()
            ->where('status', PlatformEmailCampaignRecipient::STATUS_PENDING)
            ->chunkById(200, function ($chunk) use ($campaign): void {
                foreach ($chunk as $recipient) {
                    if ($this->renderOnly) {
                        // Render but don't send. Only meaningful when
                        // ai_personalize is on; without AI there's
                        // nothing to render — leave the row pending
                        // for a later Send Now click.
                        if ($campaign->ai_personalize) {
                            RenderOnlyPlatformCampaignBodyJob::dispatch($recipient->id)->onConnection('central');
                        }

                        continue;
                    }
                    if ($campaign->ai_personalize) {
                        RenderPlatformCampaignBodyJob::dispatch($recipient->id)->onConnection('central');
                    } else {
                        SendPlatformCampaignEmailJob::dispatch($recipient->id)->onConnection('central');
                    }
                }
            });

        // In render-only mode there's no SendJob to flip the campaign
        // back to SENT. Do it here so the composer's Send Now button
        // re-appears for the next batch or the "actual send" run.
        if ($this->renderOnly && $campaign->status === PlatformEmailCampaign::STATUS_SENDING) {
            $campaign->forceFill([
                'status' => PlatformEmailCampaign::STATUS_SENT,
                'finished_at' => $campaign->finished_at ?? now(),
            ])->save();
        }
    }
}
