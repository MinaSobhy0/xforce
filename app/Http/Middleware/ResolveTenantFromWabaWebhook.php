<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Core\Services\TenantSchemaSwitcher;
use Modules\Marketing\Services\Meta\WabaTenantResolver;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active tenant for a Meta WhatsApp webhook request from the
 * WABA id in the payload, then switches the DB connection to that tenant's
 * schema so the controller can query tenant-scoped tables (notification_logs,
 * whatsapp_messages) without manual schema gymnastics.
 *
 * Why a middleware (not a controller-side resolver): it enforces ordering at
 * the framework level — future controllers and handlers can't accidentally
 * query tenant tables before the switch. Subdomain-based IdentifyTenant is
 * useless here because Meta requests carry no XForce subdomain.
 *
 * Unknown WABA → log + pass through with no tenant bound, returning 200 OK
 * (Meta retries 4xx/5xx aggressively; we want to accept-and-discard).
 *
 * Signature verification stays in the controller and runs AFTER this. That's
 * safe because this middleware only reads + schema-switches; if the signature
 * is forged, the controller aborts before any writes.
 */
class ResolveTenantFromWabaWebhook
{
    public function __construct(
        protected WabaTenantResolver $resolver,
        protected TenantSchemaSwitcher $switcher,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $wabaId = $this->extractWabaId($request);

        if (! $wabaId) {
            Log::info('whatsapp.webhook.no_waba_in_payload', [
                'ip' => $request->ip(),
            ]);

            return $next($request);
        }

        $tenant = $this->resolver->resolveByWabaId($wabaId);

        if (! $tenant) {
            Log::info('whatsapp.webhook.unknown_waba', [
                'waba_id' => $wabaId,
            ]);

            return $next($request);
        }

        if (! $tenant->database_name || ! $this->switcher->schemaExists($tenant->database_name)) {
            Log::warning('whatsapp.webhook.tenant_schema_missing', [
                'tenant_id' => $tenant->id,
                'schema' => $tenant->database_name,
            ]);

            return $next($request);
        }

        $this->switcher->switchTo($tenant);
        $request->attributes->set('tenant', $tenant);

        return $next($request);
    }

    /**
     * Pull entry[0].id without consuming the request body — getContent() is
     * idempotent so the controller's signature verifier still gets raw bytes.
     */
    protected function extractWabaId(Request $request): ?string
    {
        $body = $request->getContent();
        if ($body === '' || $body === false) {
            return null;
        }

        $decoded = json_decode($body, true);
        if (! is_array($decoded)) {
            return null;
        }

        $entries = $decoded['entry'] ?? null;
        if (! is_array($entries) || $entries === []) {
            return null;
        }

        foreach ($entries as $entry) {
            $id = $entry['id'] ?? null;
            if (is_string($id) && $id !== '') {
                return $id;
            }
        }

        return null;
    }
}
