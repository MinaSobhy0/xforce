<?php

namespace Modules\Marketing\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\Marketing\Models\Campaign;
use Modules\Marketing\Services\CampaignService;

class ProcessCampaignRecipientsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;

    /**
     * SECURITY: Maximum re-dispatch count to prevent infinite loops.
     * With batch size 50 and max 1000 iterations = 50,000 recipients max per campaign run.
     */
    protected const MAX_DISPATCHES = 1000;

    public function __construct(
        public Campaign $campaign,
        public int $batchSize = 50,
        public int $dispatchCount = 0
    ) {}

    public function handle(CampaignService $campaignService): void
    {
        // SECURITY: Use atomic lock to prevent concurrent processing of same campaign
        $lockKey = "campaign_processing:{$this->campaign->id}";
        $lock = Cache::lock($lockKey, 60);

        if (!$lock->get()) {
            Log::info('Campaign already being processed by another job', [
                'campaign_id' => $this->campaign->id,
            ]);
            return;
        }

        try {
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
                'dispatch_count' => $this->dispatchCount,
                'stats' => $stats,
            ]);

            // If there are more pending recipients and campaign is still sending, dispatch another job
            if ($this->campaign->fresh()->isSending()) {
                $pendingCount = $this->campaign->recipients()
                    ->where('status', 'pending')
                    ->count();

                // SECURITY: Check dispatch count to prevent infinite loops
                if ($pendingCount > 0 && $this->dispatchCount < self::MAX_DISPATCHES) {
                    self::dispatch($this->campaign, $this->batchSize, $this->dispatchCount + 1)
                        ->delay(now()->addSeconds(5));
                } elseif ($this->dispatchCount >= self::MAX_DISPATCHES) {
                    Log::warning('Campaign reached maximum dispatch limit', [
                        'campaign_id' => $this->campaign->id,
                        'dispatch_count' => $this->dispatchCount,
                        'pending_remaining' => $pendingCount,
                    ]);
                }
            }
        } finally {
            $lock->release();
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
