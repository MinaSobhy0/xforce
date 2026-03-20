<?php

namespace Modules\MobileApi\Http\Controllers;

use Illuminate\Http\JsonResponse;

class AppConfigController extends BaseApiController
{
    /**
     * Get app configuration for the tenant.
     * GET /api/v2/config
     */
    public function index(): JsonResponse
    {
        $tenant = $this->tenant();

        $config = [
            'tenant' => [
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'timezone' => $tenant->timezone,
                'currency' => $tenant->currency,
                'locale' => $tenant->locale ?? 'en',
            ],
            'features' => [
                'attendance' => in_array('attendance', $tenant->features ?? []),
                'payroll' => in_array('payroll', $tenant->features ?? []),
                'booking' => in_array('appointments', $tenant->features ?? []),
                'staff' => in_array('staff', $tenant->features ?? []),
            ],
            'settings' => [
                'check_in_methods' => $tenant->getMobileConfig('check_in_methods', ['manual', 'qr', 'gps']),
                'geofence_enabled' => $tenant->getMobileConfig('geofence_enabled', true),
                'geofence_radius' => $tenant->getMobileConfig('geofence_radius', 100),
                'break_tracking' => $tenant->getMobileConfig('break_tracking', true),
                'photo_check_in' => $tenant->getMobileConfig('photo_check_in', false),
            ],
            'sdui' => [
                'version' => config('mobile_api.sdui.version', '1.0.0'),
            ],
            'api' => [
                'version' => config('mobile_api.version', 'v2'),
            ],
        ];

        return $this->success($config);
    }

    /**
     * Get branding configuration.
     * GET /api/v2/branding
     */
    public function branding(): JsonResponse
    {
        $tenant = $this->tenant();

        $branding = [
            'name' => $tenant->name,
            'logo_url' => $tenant->getLogoUrl(),
            'favicon_url' => $tenant->favicon_url ?? $tenant->favicon_path,
            'primary_color' => $tenant->primary_color ?? '#3B82F6',
            'secondary_color' => $tenant->secondary_color ?? '#10B981',
            'theme' => $tenant->getMobileConfig('theme', 'light'),
        ];

        return $this->success($branding);
    }
}
