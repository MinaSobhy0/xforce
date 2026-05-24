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
use Modules\Marketing\Models\WhatsAppMessage;
use Modules\Marketing\Services\MediaDownloader;

/**
 * Fetches an inbound Meta media payload onto the tenant disk.
 *
 * Runs out-of-band so the webhook response stays under Meta's 20-second
 * deadline. The job re-resolves tenant + switches schema itself because
 * queue workers don't carry HTTP request tenant context.
 *
 * Idempotent: skips the download if media_path is already populated.
 */
class DownloadInboundMediaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30; // seconds between retries

    public function __construct(
        public int $tenantId,
        public int $messageId,
    ) {
        // Pin to the 'central' queue connection so the job row lands in
        // public.jobs even when dispatched while a tenant schema is active —
        // otherwise a system-wide worker won't see it.
        $this->onConnection('central');
    }

    public function handle(TenantSchemaSwitcher $switcher, MediaDownloader $downloader): void
    {
        $tenant = Tenant::find($this->tenantId);
        if (! $tenant || ! $tenant->database_name) {
            Log::warning('whatsapp.media_download.tenant_missing', ['tenant_id' => $this->tenantId]);

            return;
        }

        $switcher->switchTo($tenant);

        $message = WhatsAppMessage::find($this->messageId);
        if (! $message) {
            return; // row deleted; nothing to do
        }
        if ($message->media_path) {
            return; // already downloaded (idempotent retry)
        }
        if (! $message->media_id) {
            return; // not a media message after all
        }

        try {
            $stored = $downloader->downloadAndStore($tenant, $message->media_id, $message->wamid ?: "msg-{$message->id}");

            $message->update([
                'media_path' => $stored['path'],
                'media_mime' => $message->media_mime ?: $stored['mime'],
                'media_sha256' => $stored['sha256'],
            ]);
        } catch (\Throwable $e) {
            Log::error('whatsapp.media_download.failed', [
                'tenant_id' => $this->tenantId,
                'message_id' => $this->messageId,
                'media_id' => $message->media_id,
                'error' => $e->getMessage(),
            ]);

            throw $e; // let the queue retry
        }
    }
}
