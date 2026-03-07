<?php

namespace Modules\Marketing\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Marketing\Models\Campaign;
use Modules\Marketing\Services\CampaignService;

class ProcessCampaignRecipientsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;

    public function __construct(
        public Campaign $campaign,
        public int $batchSize = 50
    ) {}

    public function handle(CampaignService $campaignService): void
    {
        if (!$this->campaign->isSending()) {
            Log::info('Campaign not in sending state, skipping', [
                'campaign_id' => $this->campaign->id,
                'status' => $this->campaign->status,
            ]);
            return;
        }

        $stats = $campaignService->processRecipients($this->campaign, $this->batchSize);

        Log::info('Processed campaign batch', [
            'campaign_id' => $this->campaign->id,
            'stats' => $stats,
        ]);

        // If there are more pending recipients and campaign is still sending, dispatch another job
        if ($this->campaign->fresh()->isSending()) {
            $pendingCount = $this->campaign->recipients()
                ->where('status', 'pending')
                ->count();

            if ($pendingCount > 0) {
                self::dispatch($this->campaign, $this->batchSize)
                    ->delay(now()->addSeconds(5));
            }
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Campaign processing job failed', [
            'campaign_id' => $this->campaign->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
