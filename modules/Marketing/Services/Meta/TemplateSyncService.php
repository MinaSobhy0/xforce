<?php

namespace Modules\Marketing\Services\Meta;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Core\Models\Tenant;
use Modules\Marketing\Models\MessageTemplate;
use Modules\Marketing\Services\WhatsAppCredentialsResolver;

/**
 * Bridges our tenant-scoped MessageTemplate rows to Meta's
 * /{waba_id}/message_templates endpoint.
 *
 *   submit()  — push a local template up for approval. Persists the Meta
 *               template_id + sets status PENDING.
 *   pullAll() — fetch Meta's authoritative list for a tenant, upsert into
 *               local rows by (whatsapp_template_name, language). Used by
 *               the daily cron + manual "Re-sync from Meta" action so we
 *               catch templates created/edited outside our UI.
 *   delete()  — remove from Meta (Meta soft-deletes).
 *
 * All calls go through WhatsAppCredentialsResolver to use the tenant's
 * own access token and WABA id.
 */
class TemplateSyncService
{
    public function __construct(
        protected WhatsAppCredentialsResolver $resolver,
    ) {}

    /**
     * Submit a local template to Meta for review.
     *
     * @return array{success:bool,meta_template_id?:string,status?:string,error?:string,error_code?:int|string,response?:array}
     */
    public function submit(MessageTemplate $template): array
    {
        $tenant = $this->resolveTenantForTemplate($template);
        $creds = $tenant ? $this->resolver->resolveFor($tenant) : null;

        if (! $creds || $creds['source'] !== 'tenant') {
            return [
                'success' => false,
                'error' => 'No tenant WhatsApp credentials — connect a WABA via Embedded Signup first.',
            ];
        }

        $wabaId = (string) ($creds['business_id'] ?? '');
        if ($wabaId === '') {
            return [
                'success' => false,
                'error' => 'Tenant credentials missing waba_id; reconnect required.',
            ];
        }

        $payload = $template->toMetaPayload();
        $apiVersion = $creds['api_version'] ?? 'v18.0';

        $response = Http::withToken($creds['access_token'])
            ->post("https://graph.facebook.com/{$apiVersion}/{$wabaId}/message_templates", $payload);

        $body = $response->json();

        if ($response->successful() && ! isset($body['error'])) {
            $metaId = (string) ($body['id'] ?? '');
            $status = (string) ($body['status'] ?? MessageTemplate::META_STATUS_PENDING);
            $category = (string) ($body['category'] ?? $template->meta_template_category ?? '');

            $template->forceFill([
                'meta_template_id' => $metaId,
                'meta_template_status' => $status,
                'meta_template_category' => $category,
                'meta_template_language' => $payload['language'],
                'meta_synced_at' => now(),
                'meta_last_error' => null,
            ])->save();

            Log::info('whatsapp.template.submitted', [
                'tenant_id' => $tenant->id,
                'template_id' => $template->id,
                'meta_template_id' => $metaId,
                'status' => $status,
            ]);

            return [
                'success' => true,
                'meta_template_id' => $metaId,
                'status' => $status,
                'response' => $body,
            ];
        }

        $errorCode = $body['error']['code'] ?? $response->status();
        $errorMessage = $body['error']['message'] ?? 'Meta rejected the template';
        $errorUserTitle = $body['error']['error_user_title'] ?? null;
        $errorUserMsg = $body['error']['error_user_msg'] ?? null;
        $fullError = trim($errorMessage.' '.($errorUserTitle ?: '').' '.($errorUserMsg ?: ''));

        $template->forceFill([
            'meta_template_status' => MessageTemplate::META_STATUS_REJECTED,
            'meta_last_error' => $fullError,
            'meta_synced_at' => now(),
        ])->save();

        Log::warning('whatsapp.template.submit_failed', [
            'tenant_id' => $tenant->id,
            'template_id' => $template->id,
            'error_code' => $errorCode,
            'error' => $fullError,
        ]);

        return [
            'success' => false,
            'error' => $fullError,
            'error_code' => $errorCode,
            'response' => $body,
        ];
    }

    /**
     * Fetch Meta's authoritative template list for the tenant's WABA and
     * upsert into local rows. Meta is source of truth for status/category;
     * local stays source of truth for body/buttons (which are immutable
     * after Meta approval anyway).
     */
    public function pullAll(Tenant $tenant): int
    {
        $creds = $this->resolver->resolveFor($tenant);
        if (! $creds || ($creds['source'] ?? null) !== 'tenant') {
            return 0;
        }

        $wabaId = (string) ($creds['business_id'] ?? '');
        if ($wabaId === '') {
            return 0;
        }

        $apiVersion = $creds['api_version'] ?? 'v18.0';
        $url = "https://graph.facebook.com/{$apiVersion}/{$wabaId}/message_templates?limit=200";
        $count = 0;

        while ($url) {
            $response = Http::withToken($creds['access_token'])->get($url);
            if (! $response->successful()) {
                Log::warning('whatsapp.template.pull_failed', [
                    'tenant_id' => $tenant->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                break;
            }

            $body = $response->json();
            foreach ($body['data'] ?? [] as $remote) {
                $this->upsertFromMeta($remote);
                $count++;
            }

            $url = $body['paging']['next'] ?? null;
        }

        Log::info('whatsapp.template.pull_complete', [
            'tenant_id' => $tenant->id,
            'count' => $count,
        ]);

        return $count;
    }

    /**
     * Delete a template from Meta. Local row is preserved (so we keep a
     * record of what was once there); local meta_* state is cleared.
     */
    public function delete(MessageTemplate $template): bool
    {
        $tenant = $this->resolveTenantForTemplate($template);
        $creds = $tenant ? $this->resolver->resolveFor($tenant) : null;

        if (! $creds || ($creds['source'] ?? null) !== 'tenant') {
            return false;
        }

        $wabaId = (string) ($creds['business_id'] ?? '');
        if ($wabaId === '' || ! $template->whatsapp_template_name) {
            return false;
        }

        $apiVersion = $creds['api_version'] ?? 'v18.0';
        $response = Http::withToken($creds['access_token'])
            ->delete("https://graph.facebook.com/{$apiVersion}/{$wabaId}/message_templates", [
                'name' => $template->whatsapp_template_name,
            ]);

        $ok = $response->successful();

        if ($ok) {
            $template->forceFill([
                'meta_template_id' => null,
                'meta_template_status' => null,
                'meta_synced_at' => now(),
                'meta_last_error' => null,
            ])->save();
        }

        return $ok;
    }

    /**
     * Tenant for an in-context template — we're running inside the tenant
     * schema when this is called, so current_tenant() should resolve. Fall
     * back to looking up by tenant_id column if the request lacks context
     * (e.g. queued job manually invoked).
     */
    protected function resolveTenantForTemplate(MessageTemplate $template): ?Tenant
    {
        if ($tenant = current_tenant()) {
            return $tenant;
        }

        if ($template->tenant_id) {
            return Tenant::find($template->tenant_id);
        }

        return null;
    }

    /**
     * Upsert one Meta template payload into the local table. Keyed by
     * (whatsapp_template_name + meta_template_language) which Meta also
     * treats as unique.
     */
    protected function upsertFromMeta(array $remote): void
    {
        $name = (string) ($remote['name'] ?? '');
        $language = (string) ($remote['language'] ?? '');
        if ($name === '' || $language === '') {
            return;
        }

        /** @var MessageTemplate|null $row */
        $row = MessageTemplate::query()
            ->where('whatsapp_template_name', $name)
            ->where(function ($q) use ($language) {
                $q->where('meta_template_language', $language)
                    ->orWhereNull('meta_template_language');
            })
            ->first();

        // Don't create rows for templates that exist only on Meta — we'd
        // have no body translation, no buttons authoring, no campaign
        // wiring. Just refresh status on rows we already know about.
        if (! $row) {
            return;
        }

        $row->forceFill([
            'meta_template_id' => (string) ($remote['id'] ?? $row->meta_template_id),
            'meta_template_status' => (string) ($remote['status'] ?? $row->meta_template_status),
            'meta_template_category' => (string) ($remote['category'] ?? $row->meta_template_category),
            'meta_template_language' => $language,
            'meta_synced_at' => now(),
            'meta_last_error' => $remote['rejected_reason'] ?? null,
        ])->save();
    }
}
