<?php

namespace Modules\Marketing\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Core\Models\Tenant;
use Modules\Core\Services\TenantSchemaSwitcher;
use Modules\Marketing\Models\MessageTemplate;
use Modules\Marketing\Services\Meta\TemplateSyncService;

/**
 * Submits a tenant's MessageTemplate to Meta for review.
 *
 * Filament's "Submit to Meta" action dispatches this so the request
 * returns instantly — the actual Meta round-trip happens out of band.
 * Pinned to the `central` queue connection so the queued row lands in
 * public.jobs and a system-wide worker picks it up (same pattern as
 * DownloadInboundMediaJob).
 */
class SubmitTemplateToMetaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $backoff = 30;

    public function __construct(
        public int $tenantId,
        public int $templateId,
    ) {
        $this->onConnection('central');
    }

    public function handle(TenantSchemaSwitcher $switcher, TemplateSyncService $sync): void
    {
        $tenant = Tenant::find($this->tenantId);
        if (! $tenant || ! $tenant->database_name) {
            Log::warning('whatsapp.template.submit_job.tenant_missing', ['tenant_id' => $this->tenantId]);

            return;
        }

        $switcher->switchTo($tenant);

        $template = MessageTemplate::find($this->templateId);
        if (! $template) {
            return;
        }

        $sync->submit($template);
    }
}
