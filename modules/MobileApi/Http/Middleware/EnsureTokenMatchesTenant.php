<?php

namespace Modules\MobileApi\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SECURITY: reject a bearer token that was issued for a different tenant.
 *
 * personal_access_tokens is a shared public-schema table while `users` is
 * per-tenant. ResolveTenantFromHeader runs first and points search_path at
 * whatever tenant X-Tenant-Slug names, so Sanctum resolves tokenable_id
 * against THAT tenant's users table. A token for user 42 of clinic A,
 * replayed with clinic B's slug, would otherwise authenticate as clinic B's
 * user 42 — a different person, at a different company.
 *
 * Must run after auth:sanctum (the token has to be resolved before it can
 * be inspected) and after ResolveTenantFromHeader.
 */
class EnsureTokenMatchesTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();

        // Session-guard requests (no personal access token) are out of scope;
        // they are tenant-resolved by host, not by header.
        if (! $token) {
            return $next($request);
        }

        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;

        if (! $tenant) {
            return $this->reject();
        }

        // NULL tenant_id means the token predates this binding. It cannot be
        // attributed to a tenant, so it is not trusted on the mobile surface;
        // the client re-authenticates and gets a stamped token.
        if ($token->tenant_id === null || (int) $token->tenant_id !== (int) $tenant->id) {
            return $this->reject();
        }

        return $next($request);
    }

    protected function reject(): Response
    {
        return response()->json([
            'success' => false,
            'message' => __('mobile_api::mobile.auth.invalid_token'),
            'error_code' => 'TOKEN_TENANT_MISMATCH',
        ], 401);
    }
}
