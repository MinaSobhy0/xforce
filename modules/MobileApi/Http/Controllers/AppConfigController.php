<?php

namespace Modules\MobileApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\MobileApi\Services\SDUIService;

class AppConfigController extends BaseApiController
{
    public function __construct(
        protected SDUIService $sduiService
    ) {}

    /**
     * Get app configuration for the tenant.
     * GET /api/v2/config
     */
    public function index(): JsonResponse
    {
        $tenant = $this->tenant();
        $mobileConfig = $tenant->getMobileAppConfig();

        // Get branding from SDUI service (handles fallbacks)
        $branding = $this->sduiService->getBranding();

        // Get navigation from SDUI service (applies tenant customizations)
        $navigation = $this->sduiService->getNavigation();

        // Get features from SDUI service + module availability
        $sduiFeatures = $this->sduiService->getFeatures();

        $config = [
            'tenant' => [
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'timezone' => $tenant->timezone,
                'currency' => $tenant->currency,
                'locale' => $tenant->locale ?? 'en',
            ],
            'branding' => $branding,
            'navigation' => $navigation,
            'features' => array_merge(
                // Module availability
                [
                    'attendance' => in_array('attendance', $tenant->features ?? []),
                    'payroll' => in_array('payroll', $tenant->features ?? []),
                    'booking' => in_array('appointments', $tenant->features ?? []),
                    'staff' => in_array('staff', $tenant->features ?? []),
                ],
                // Mobile app specific features from tenant config
                $sduiFeatures
            ),
            'settings' => [
                'check_in_methods' => $this->getCheckInMethods($mobileConfig),
                'geofence_enabled' => $mobileConfig['features']['geofence_check_in'] ?? true,
                'geofence_radius' => $tenant->getMobileConfig('geofence_radius', 100),
                'break_tracking' => $mobileConfig['features']['break_tracking'] ?? true,
                'photo_check_in' => $mobileConfig['features']['attendance_photo_required'] ?? false,
            ],
            // TC-20: tenant-configurable payslip visibility. Mobile app hides
            // rows the tenant has flipped off. Default hides gross_salary
            // (business policy in most tenants) while keeping the earnings /
            // deductions breakdowns on. Server + client stay in sync via
            // PayrollController::payslipDisplayConfig().
            'payslip_display' => \Modules\MobileApi\Http\Controllers\PayrollController::payslipDisplayConfig(),
            'quick_actions' => $this->sduiService->getQuickActions(),
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
        $branding = $this->sduiService->getBranding();

        return $this->success($branding);
    }

    /**
     * Get navigation configuration.
     * GET /api/v2/navigation
     */
    public function navigation(): JsonResponse
    {
        return $this->success($this->sduiService->getNavigation());
    }

    /**
     * Get quick actions configuration.
     * GET /api/v2/quick-actions
     */
    public function quickActions(): JsonResponse
    {
        return $this->success([
            'quick_actions' => $this->sduiService->getQuickActions(),
        ]);
    }

    /**
     * Get available check-in methods based on tenant config.
     */
    protected function getCheckInMethods(array $mobileConfig): array
    {
        $methods = ['manual'];

        if ($mobileConfig['features']['qr_check_in'] ?? true) {
            $methods[] = 'qr';
        }

        if ($mobileConfig['features']['geofence_check_in'] ?? true) {
            $methods[] = 'gps';
        }

        return $methods;
    }
}
