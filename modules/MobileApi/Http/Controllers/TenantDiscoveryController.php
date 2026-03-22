<?php

namespace Modules\MobileApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\MobileApi\Services\TenantDiscoveryService;

class TenantDiscoveryController extends BaseApiController
{
    public function __construct(
        protected TenantDiscoveryService $discoveryService
    ) {}

    /**
     * SECURITY: Generic error message to prevent enumeration.
     * Using a constant message prevents attackers from distinguishing
     * between "tenant doesn't exist" and "tenant is inactive".
     */
    protected const GENERIC_NOT_FOUND_MESSAGE = 'mobile_api::mobile.tenant.invalid_or_unavailable';

    /**
     * Resolve a tenant from an app code (QR, short code, deep link).
     * GET /api/v2/tenant/resolve/{code}
     *
     * SECURITY: Returns consistent error response to prevent enumeration.
     */
    public function resolve(string $code): JsonResponse
    {
        // SECURITY: Add small random delay to prevent timing attacks
        usleep(random_int(50000, 150000)); // 50-150ms

        $result = $this->discoveryService->resolveByCode($code);

        // SECURITY: Return same error for "not found" and "invalid" to prevent enumeration
        if (!$result || !$result['valid']) {
            return response()->json([
                'success' => false,
                'error_code' => 'INVALID_CODE',
                'message' => __('mobile_api::mobile.tenant.invalid_code'),
            ], 404);
        }

        return $this->success(
            $result['tenant'],
            __('mobile_api::mobile.tenant.resolved')
        );
    }

    /**
     * Validate a tenant by slug.
     * POST /api/v2/tenant/validate
     *
     * SECURITY: Returns consistent error response to prevent enumeration.
     */
    public function validateTenant(Request $request): JsonResponse
    {
        $request->validate([
            'slug' => 'required|string|max:100|alpha_dash',
        ]);

        // SECURITY: Add small random delay to prevent timing attacks
        usleep(random_int(50000, 150000)); // 50-150ms

        $result = $this->discoveryService->validateBySlug($request->slug);

        // SECURITY: Return same error for "not found" and "inactive" to prevent enumeration
        if (!$result || !$result['valid']) {
            return $this->error(
                __('mobile_api::mobile.tenant.invalid_code'),
                404
            );
        }

        return $this->success(
            $result['tenant'],
            __('mobile_api::mobile.tenant.validated')
        );
    }

    /**
     * Lookup a tenant by domain.
     * GET /api/v2/tenant/lookup?domain=
     *
     * SECURITY: Returns consistent error response to prevent enumeration.
     */
    public function lookup(Request $request): JsonResponse
    {
        $request->validate([
            'domain' => 'required|string|max:255',
        ]);

        // SECURITY: Add small random delay to prevent timing attacks
        usleep(random_int(50000, 150000)); // 50-150ms

        $result = $this->discoveryService->lookupByDomain($request->domain);

        // SECURITY: Return same error for "not found" and "inactive" to prevent enumeration
        if (!$result || !$result['valid']) {
            return $this->error(
                __('mobile_api::mobile.tenant.invalid_code'),
                404
            );
        }

        return $this->success(
            $result['tenant'],
            __('mobile_api::mobile.tenant.resolved')
        );
    }
}
