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
     * Resolve a tenant from an app code (QR, short code, deep link).
     * GET /api/v2/tenant/resolve/{code}
     */
    public function resolve(string $code): JsonResponse
    {
        $result = $this->discoveryService->resolveByCode($code);

        if (!$result) {
            return response()->json([
                'success' => false,
                'error_code' => 'INVALID_CODE',
                'message' => __('mobile_api::mobile.tenant.invalid_code'),
            ], 404);
        }

        if (!$result['valid']) {
            return response()->json([
                'success' => false,
                'error_code' => $result['error_code'] ?? 'VALIDATION_ERROR',
                'message' => $result['error'],
            ], 400);
        }

        return $this->success(
            $result['tenant'],
            __('mobile_api::mobile.tenant.resolved')
        );
    }

    /**
     * Validate a tenant by slug.
     * POST /api/v2/tenant/validate
     */
    public function validateTenant(Request $request): JsonResponse
    {
        $request->validate([
            'slug' => 'required|string|max:100',
        ]);

        $result = $this->discoveryService->validateBySlug($request->slug);

        if (!$result) {
            return $this->error(
                __('mobile_api::mobile.tenant.tenant_not_found'),
                404
            );
        }

        if (!$result['valid']) {
            return $this->error($result['error'], 400);
        }

        return $this->success(
            $result['tenant'],
            __('mobile_api::mobile.tenant.validated')
        );
    }

    /**
     * Lookup a tenant by domain.
     * GET /api/v2/tenant/lookup?domain=
     */
    public function lookup(Request $request): JsonResponse
    {
        $request->validate([
            'domain' => 'required|string|max:255',
        ]);

        $result = $this->discoveryService->lookupByDomain($request->domain);

        if (!$result) {
            return $this->error(
                __('mobile_api::mobile.tenant.tenant_not_found'),
                404
            );
        }

        if (!$result['valid']) {
            return $this->error($result['error'], 400);
        }

        return $this->success(
            $result['tenant'],
            __('mobile_api::mobile.tenant.resolved')
        );
    }
}
