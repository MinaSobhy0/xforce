<?php

namespace Modules\Attendance\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Attendance\Models\Attendance;
use Modules\Attendance\Models\AttendanceTypeSetting;

class AttendanceSettingsController extends Controller
{
    /**
     * Get enabled attendance types and their settings for mobile app.
     */
    public function index(Request $request): JsonResponse
    {
        $branchId = $request->input('branch_id');

        $enabledTypes = [];

        foreach (Attendance::CONFIGURABLE_TYPES as $type) {
            $setting = AttendanceTypeSetting::getForType($type, $branchId);

            if ($setting && $setting->is_enabled) {
                $enabledTypes[$type] = $this->formatSettingsForMobile($setting);
            }
        }

        // Always include manual type
        $enabledTypes[Attendance::TYPE_MANUAL] = [
            'type' => Attendance::TYPE_MANUAL,
            'enabled' => true,
            'settings' => [],
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'enabled_types' => array_keys($enabledTypes),
                'settings' => $enabledTypes,
            ],
        ]);
    }

    /**
     * Get settings for a specific type.
     */
    public function show(Request $request, string $type): JsonResponse
    {
        if (!in_array($type, Attendance::CONFIGURABLE_TYPES)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid attendance type',
            ], 400);
        }

        $branchId = $request->input('branch_id');
        $setting = AttendanceTypeSetting::getForType($type, $branchId);

        if (!$setting || !$setting->is_enabled) {
            return response()->json([
                'success' => false,
                'message' => 'This attendance type is not enabled',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatSettingsForMobile($setting),
        ]);
    }

    /**
     * Get current dynamic QR code.
     */
    public function getDynamicQrCode(Request $request): JsonResponse
    {
        $branchId = $request->input('branch_id');
        $setting = AttendanceTypeSetting::getForType(Attendance::TYPE_QR_DYNAMIC, $branchId);

        if (!$setting || !$setting->is_enabled) {
            return response()->json([
                'success' => false,
                'message' => 'Dynamic QR is not enabled',
            ], 404);
        }

        $code = $setting->getCurrentDynamicCode();
        $interval = $setting->getSetting('refresh_interval_seconds', 30);
        $currentTime = time();
        $nextRefresh = (floor($currentTime / $interval) + 1) * $interval;

        return response()->json([
            'success' => true,
            'data' => [
                'code' => $code,
                'expires_in' => $nextRefresh - $currentTime,
                'refresh_interval' => $interval,
                'display_countdown' => $setting->getSetting('display_countdown', true),
            ],
        ]);
    }

    /**
     * Validate a QR code (static or dynamic).
     */
    public function validateQrCode(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string',
            'type' => 'required|in:qr_static,qr_dynamic',
            'branch_id' => 'nullable|uuid',
        ]);

        $type = $request->input('type');
        $code = $request->input('code');
        $branchId = $request->input('branch_id');

        $setting = AttendanceTypeSetting::getForType($type, $branchId);

        if (!$setting || !$setting->is_enabled) {
            return response()->json([
                'success' => false,
                'message' => 'This QR type is not enabled',
            ], 404);
        }

        $isValid = $type === Attendance::TYPE_QR_STATIC
            ? $setting->validateStaticQrCode($code)
            : $setting->validateDynamicCode($code);

        return response()->json([
            'success' => $isValid,
            'message' => $isValid ? 'Code is valid' : 'Invalid or expired code',
        ]);
    }

    /**
     * Validate geofence location.
     */
    public function validateGeofence(Request $request): JsonResponse
    {
        $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'accuracy' => 'nullable|numeric',
            'branch_id' => 'nullable|uuid',
        ]);

        $branchId = $request->input('branch_id');
        $setting = AttendanceTypeSetting::getForType(Attendance::TYPE_GEOFENCE, $branchId);

        if (!$setting || !$setting->is_enabled) {
            return response()->json([
                'success' => false,
                'message' => 'Geofence is not enabled',
            ], 404);
        }

        $isWithin = $setting->isWithinGeofence(
            $request->input('latitude'),
            $request->input('longitude'),
            $request->input('accuracy')
        );

        return response()->json([
            'success' => $isWithin,
            'message' => $isWithin ? 'Location is within geofence' : 'Location is outside geofence',
            'data' => [
                'within_geofence' => $isWithin,
                'locations' => $setting->getSetting('locations', []),
            ],
        ]);
    }

    /**
     * Get geofence locations for map display.
     */
    public function getGeofenceLocations(Request $request): JsonResponse
    {
        $branchId = $request->input('branch_id');
        $setting = AttendanceTypeSetting::getForType(Attendance::TYPE_GEOFENCE, $branchId);

        if (!$setting || !$setting->is_enabled) {
            return response()->json([
                'success' => false,
                'message' => 'Geofence is not enabled',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'locations' => $setting->getSetting('locations', []),
                'default_radius' => $setting->getSetting('radius_meters', 100),
            ],
        ]);
    }

    /**
     * Format settings for mobile app consumption.
     * Excludes sensitive data like secret keys.
     */
    protected function formatSettingsForMobile(AttendanceTypeSetting $setting): array
    {
        $settings = $setting->getMergedSettings();
        $type = $setting->type;

        // Remove sensitive fields
        $sensitiveFields = ['qr_secret', 'secret_key', 'api_key'];
        foreach ($sensitiveFields as $field) {
            unset($settings[$field]);
        }

        // Type-specific formatting
        if ($type === Attendance::TYPE_GEOFENCE) {
            // Include only necessary geofence data
            return [
                'type' => $type,
                'enabled' => true,
                'settings' => [
                    'radius_meters' => $settings['radius_meters'] ?? 100,
                    'require_high_accuracy' => $settings['require_high_accuracy'] ?? true,
                    'min_accuracy_meters' => $settings['min_accuracy_meters'] ?? 50,
                    'allow_mock_location' => $settings['allow_mock_location'] ?? false,
                    'check_on_checkout' => $settings['check_on_checkout'] ?? true,
                    'locations' => $settings['locations'] ?? [],
                ],
            ];
        }

        if ($type === Attendance::TYPE_QR_STATIC) {
            return [
                'type' => $type,
                'enabled' => true,
                'settings' => [
                    'allow_camera_only' => $settings['allow_camera_only'] ?? true,
                    'show_qr_in_app' => $settings['show_qr_in_app'] ?? false,
                    'require_location' => $settings['require_location'] ?? false,
                    // Include QR content only if show_qr_in_app is true
                    'qr_content' => ($settings['show_qr_in_app'] ?? false) ? ($settings['qr_content'] ?? null) : null,
                ],
            ];
        }

        if ($type === Attendance::TYPE_QR_DYNAMIC) {
            return [
                'type' => $type,
                'enabled' => true,
                'settings' => [
                    'refresh_interval_seconds' => $settings['refresh_interval_seconds'] ?? 30,
                    'validity_seconds' => $settings['validity_seconds'] ?? 60,
                    'display_countdown' => $settings['display_countdown'] ?? true,
                    'require_location' => $settings['require_location'] ?? false,
                ],
            ];
        }

        if ($type === Attendance::TYPE_BIOMETRIC) {
            return [
                'type' => $type,
                'enabled' => true,
                'settings' => [
                    'device_type' => $settings['device_type'] ?? 'fingerprint',
                    'verification_level' => $settings['verification_level'] ?? 'medium',
                    'allow_fallback' => $settings['allow_fallback'] ?? true,
                    'fallback_method' => $settings['fallback_method'] ?? Attendance::TYPE_QR_STATIC,
                ],
            ];
        }

        return [
            'type' => $type,
            'enabled' => true,
            'settings' => $settings,
        ];
    }
}
