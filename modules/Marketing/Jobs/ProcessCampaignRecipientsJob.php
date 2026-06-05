<?php

namespace Modules\Marketing\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\Core\Models\Tenant;
use Modules\Core\Services\TenantSchemaSwitcher;
use Modules\Marketing\Models\Campaign;
use Modules\Marketing\Services\CampaignService;

/**
 * Processes a campaign's pending recipients in batches.
 *
 * SECURITY (tenant isolation): queue workers do not carry HTTP request tenant
 * context, and under PgBouncer session pooling a worker can inherit a leftover
 * tenant schema from a previous job. This job therefore pins to the 'central'
 * connection (so the job row lands in public.jobs) and re-resolves the tenant +
 * switches schema itself before touching any tenant table. It carries scalar
 * IDs instead of a serialized Eloquent model so restoration never queries the
 * wrong schema.
 */
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
        public int $tenantId,
        public int $campaignId,
        public int $batchSize = 50,
        public int $dispatchCount = 0
    ) {
        // Pin to the 'central' queue connection so the job row lands in
        // public.jobs even when dispatched while a tenant schema is active —
        // otherwise a system-wide worker won't see it.
        $this->onConnection('central');
    }

    public function handle(TenantSchemaSwitcher $switcher, CampaignService $campaignService): void
    {
        $tenant = Tenant::find($this->tenantId);
        if (! $tenant || ! $tenant->database_name) {
            Log::warning('campaign.process.tenant_missing', ['tenant_id' => $this->tenantId]);

            return;
        }

        // SECURITY: establish the tenant schema before any tenant query runs.
        $switcher->switchTo($tenant);

        // SECURITY: tenant-scoped lock key so two tenants' campaign id=N cannot
        // collide on the same lock in the shared (non-tenant-prefixed) cache.
        $lockKey = "campaign_processing:{$this->tenantId}:{$this->campaignId}";
        $lock = Cache::lock($lockKey, 60);

        if (! $lock->get()) {
            Log::info('Campaign already being processed by another job', [
                'tenant_id' => $this->tenantId,
                'campaign_id' => $this->campaignId,
            ]);

            return;
        }

        try {
            $campaign = Campaign::find($this->campaignId);
            if (! $campaign) {
                Log::warning('campaign.process.campaign_missing', [
                    'tenant_id' => $this->tenantId,
                    'campaign_id' => $this->campaignId,
                ]);

                return;
            }

            if (! $campaign->isSending()) {
                Log::info('Campaign not in sending state, skipping', [
                    'campaign_id' => $campaign->id,
                    'status' => $campaign->status,
                ]);

                return;
            }

            $stats = $campaignService->processRecipients($campaign, $this->batchSize);

            Log::info('Processed campaign batch', [
                'tenant_id' => $this->tenantId,
                'campaign_id' => $campaign->id,
                'dispatch_count' => $this->dispatchCount,
                'stats' => $stats,
            ]);

            // If there are more pending recipients and campaign is still sending, dispatch another job
            if ($campaign->fresh()->isSending()) {
                $pendingCount = $campaign->recipients()
                    ->where('status', 'pending')
                    ->count();

                // SECURITY: Check dispatch count to prevent infinite loops
                if ($pendingCount > 0 && $this->dispatchCount < self::MAX_DISPATCHES) {
                    self::dispatch($this->tenantId, $this->campaignId, $this->batchSize, $this->dispatchCount + 1)
                        ->delay(now()->addSeconds(5));
                } elseif ($this->dispatchCount >= self::MAX_DISPATCHES) {
                    Log::warning('Campaign reached maximum dispatch limit', [
                        'campaign_id' => $campaign->id,
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
            'tenant_id' => $this->tenantId,
            'campaign_id' => $this->campaignId,
            'error' => $exception->getMessage(),
        ]);
    }
}
