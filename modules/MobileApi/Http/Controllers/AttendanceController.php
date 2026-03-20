<?php

namespace Modules\MobileApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Attendance\Models\Attendance;
use Modules\Attendance\Models\AttendanceTypeSetting;
use Modules\Attendance\Models\AttendanceViolation;

class AttendanceController extends BaseApiController
{
    /**
     * Get available attendance/check-in types.
     * GET /api/v2/attendance/types
     */
    public function types(): JsonResponse
    {
        $branch = $this->branch();

        if (!class_exists(AttendanceTypeSetting::class)) {
            // Return default manual type if module not available
            return $this->success([
                [
                    'type' => 'manual',
                    'label' => 'Manual',
                    'enabled' => true,
                    'settings' => [],
                ],
            ]);
        }

        // Get enabled types for this branch
        $settings = AttendanceTypeSetting::enabled()
            ->forBranch($branch?->id)
            ->get();

        // Always include manual type
        $types = [[
            'type' => Attendance::TYPE_MANUAL,
            'label' => Attendance::TYPES[Attendance::TYPE_MANUAL],
            'enabled' => true,
            'settings' => [],
        ]];

        foreach ($settings as $setting) {
            $types[] = [
                'type' => $setting->type,
                'label' => Attendance::TYPES[$setting->type] ?? $setting->type,
                'enabled' => $setting->is_enabled,
                'settings' => $this->getClientSettings($setting),
            ];
        }

        return $this->success($types);
    }

    /**
     * Get current attendance status.
     * GET /api/v2/attendance/status
     */
    public function status(): JsonResponse
    {
        $staffProfile = $this->staffProfile();

        if (!$staffProfile) {
            return $this->forbidden(__('mobile_api::mobile.auth.not_staff'));
        }

        if (!class_exists(Attendance::class)) {
            return $this->error('Attendance module not available', 503);
        }

        $attendance = Attendance::where('staff_profile_id', $staffProfile->id)
            ->whereDate('attendance_date', today())
            ->first();

        if (!$attendance) {
            return $this->success([
                'status' => 'not_checked_in',
                'can_check_in' => true,
                'can_check_out' => false,
                'can_start_break' => false,
                'can_end_break' => false,
            ]);
        }

        $currentBreak = $attendance->breaks()
            ->whereNull('end_time')
            ->first();

        $isOnBreak = $currentBreak !== null;
        $isCheckedOut = $attendance->check_out_time !== null;

        return $this->success([
            'status' => $isCheckedOut ? 'checked_out' : ($isOnBreak ? 'on_break' : 'checked_in'),
            'check_in_time' => $attendance->check_in_time?->format('H:i'),
            'check_out_time' => $attendance->check_out_time?->format('H:i'),
            'method' => $attendance->attendance_type,
            'worked_hours' => (float) $attendance->working_hours,
            'break_minutes' => $attendance->breaks()->sum('duration_minutes'),
            'current_break_started' => $currentBreak?->start_time?->format('H:i'),
            'can_check_in' => false,
            'can_check_out' => !$isCheckedOut && !$isOnBreak,
            'can_start_break' => !$isCheckedOut && !$isOnBreak,
            'can_end_break' => $isOnBreak,
        ]);
    }

    /**
     * Get attendance settings.
     * GET /api/v2/attendance/settings
     */
    public function settings(): JsonResponse
    {
        $tenant = $this->tenant();
        $mobileConfig = $tenant->getMobileAppConfig();
        $features = $mobileConfig['features'] ?? [];

        return $this->success([
            'check_in_methods' => $this->getEnabledCheckInMethods(),
            'geofence_enabled' => $features['geofence_check_in'] ?? true,
            'qr_enabled' => $features['qr_check_in'] ?? true,
            'break_tracking' => $features['break_tracking'] ?? true,
            'photo_required' => $features['attendance_photo_required'] ?? false,
        ]);
    }

    /**
     * Check in.
     * POST /api/v2/attendance/check-in
     */
    public function checkIn(Request $request): JsonResponse
    {
        $staffProfile = $this->staffProfile();

        if (!$staffProfile) {
            return $this->forbidden(__('mobile_api::mobile.auth.not_staff'));
        }

        $request->validate([
            'method' => 'required|in:manual,geofence,qr_static,qr_dynamic,biometric',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'qr_code' => 'required_if:method,qr_static,qr_dynamic|string',
            'photo' => 'sometimes|string',
        ]);

        if (!class_exists(Attendance::class)) {
            return $this->error('Attendance module not available', 503);
        }

        // Check if already has attendance record today (any status)
        $existing = Attendance::where('staff_profile_id', $staffProfile->id)
            ->whereDate('attendance_date', today())
            ->first();

        if ($existing) {
            // Already checked in today
            if ($existing->check_out_time) {
                // Already completed attendance for today
                return $this->error(__('mobile_api::mobile.attendance.already_completed_today'), 400);
            }
            // Still checked in (no check out yet)
            return $this->error(__('mobile_api::mobile.attendance.already_checked_in'), 400);
        }

        // Validate check-in method
        if ($request->method === 'geofence') {
            if (!$request->latitude || !$request->longitude) {
                return $this->error(__('mobile_api::mobile.attendance.location_required'), 400);
            }
            $validation = $this->validateGeofenceLocation($request->latitude, $request->longitude);
            if (!$validation['valid']) {
                return $this->error(__('mobile_api::mobile.attendance.invalid_location'), 400);
            }
        }

        if (in_array($request->method, ['qr_static', 'qr_dynamic'])) {
            $validation = $this->validateQrCode($request->qr_code, $request->method);
            if (!$validation['valid']) {
                return $this->error(__('mobile_api::mobile.attendance.invalid_qr'), 400);
            }
        }

        // Create attendance record with try-catch for race conditions
        try {
            $attendance = Attendance::create([
                'tenant_id' => current_tenant_id(),
                'staff_profile_id' => $staffProfile->id,
                'branch_id' => $this->branch()?->id,
                'attendance_date' => today(),
                'check_in_time' => now(),
                'attendance_type' => $request->method,
                'status' => Attendance::STATUS_PRESENT,
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            return $this->error(__('mobile_api::mobile.attendance.already_checked_in'), 400);
        }

        return $this->success([
            'id' => $attendance->id,
            'check_in_time' => $attendance->check_in_time->format('H:i'),
        ], __('mobile_api::mobile.attendance.checked_in'));
    }

    /**
     * Check out.
     * POST /api/v2/attendance/check-out
     */
    public function checkOut(Request $request): JsonResponse
    {
        $staffProfile = $this->staffProfile();

        if (!$staffProfile) {
            return $this->forbidden(__('mobile_api::mobile.auth.not_staff'));
        }

        if (!class_exists(Attendance::class)) {
            return $this->error('Attendance module not available', 503);
        }

        $attendance = Attendance::where('staff_profile_id', $staffProfile->id)
            ->whereDate('attendance_date', today())
            ->whereNull('check_out_time')
            ->first();

        if (!$attendance) {
            return $this->error(__('mobile_api::mobile.attendance.not_checked_in'), 400);
        }

        // End any active breaks
        $attendance->breaks()
            ->whereNull('end_time')
            ->each(function ($break) {
                $break->endBreak();
            });

        // Calculate working hours
        $totalMinutes = $attendance->check_in_time->diffInMinutes(now());
        $breakMinutes = $attendance->breaks()->sum('duration_minutes');
        $workedHours = round(($totalMinutes - $breakMinutes) / 60, 2);

        // Update check out
        $attendance->update([
            'check_out_time' => now(),
            'working_hours' => $workedHours,
        ]);

        return $this->success([
            'check_out_time' => $attendance->check_out_time->format('H:i'),
            'worked_hours' => $workedHours,
        ], __('mobile_api::mobile.attendance.checked_out'));
    }

    /**
     * Start break.
     * POST /api/v2/attendance/break/start
     */
    public function startBreak(Request $request): JsonResponse
    {
        $staffProfile = $this->staffProfile();

        if (!$staffProfile) {
            return $this->forbidden(__('mobile_api::mobile.auth.not_staff'));
        }

        if (!class_exists(Attendance::class)) {
            return $this->error('Attendance module not available', 503);
        }

        $attendance = Attendance::where('staff_profile_id', $staffProfile->id)
            ->whereDate('attendance_date', today())
            ->whereNull('check_out_time')
            ->first();

        if (!$attendance) {
            return $this->error(__('mobile_api::mobile.attendance.not_checked_in'), 400);
        }

        // Check if already on break
        $existingBreak = $attendance->breaks()
            ->whereNull('end_time')
            ->first();

        if ($existingBreak) {
            return $this->error(__('mobile_api::mobile.attendance.already_on_break'), 400);
        }

        // Create break record
        $break = $attendance->breaks()->create([
            'tenant_id' => current_tenant_id(),
            'start_time' => now(),
            'reason' => $request->reason,
        ]);

        return $this->success([
            'break_id' => $break->id,
            'started_at' => $break->start_time->format('H:i'),
        ], __('mobile_api::mobile.attendance.break_started'));
    }

    /**
     * End break.
     * POST /api/v2/attendance/break/end
     */
    public function endBreak(): JsonResponse
    {
        $staffProfile = $this->staffProfile();

        if (!$staffProfile) {
            return $this->forbidden(__('mobile_api::mobile.auth.not_staff'));
        }

        if (!class_exists(Attendance::class)) {
            return $this->error('Attendance module not available', 503);
        }

        $attendance = Attendance::where('staff_profile_id', $staffProfile->id)
            ->whereDate('attendance_date', today())
            ->whereNull('check_out_time')
            ->first();

        if (!$attendance) {
            return $this->error(__('mobile_api::mobile.attendance.not_checked_in'), 400);
        }

        $break = $attendance->breaks()
            ->whereNull('end_time')
            ->first();

        if (!$break) {
            return $this->error(__('mobile_api::mobile.attendance.not_on_break'), 400);
        }

        $break->endBreak();

        return $this->success([
            'ended_at' => $break->end_time->format('H:i'),
            'duration_minutes' => $break->duration_minutes,
        ], __('mobile_api::mobile.attendance.break_ended'));
    }

    /**
     * Get attendance history.
     * GET /api/v2/attendance/history
     */
    public function history(Request $request): JsonResponse
    {
        $staffProfile = $this->staffProfile();

        if (!$staffProfile) {
            return $this->forbidden(__('mobile_api::mobile.auth.not_staff'));
        }

        if (!class_exists(Attendance::class)) {
            return $this->success([]);
        }

        $query = Attendance::where('staff_profile_id', $staffProfile->id)
            ->orderByDesc('attendance_date');

        if ($request->filled('month')) {
            $query->whereMonth('attendance_date', $request->month);
        }

        if ($request->filled('year')) {
            $query->whereYear('attendance_date', $request->year);
        }

        $records = $query->paginate($this->getPerPage());

        $formatted = collect($records->items())->map(fn($r) => [
            'id' => $r->id,
            'date' => $r->attendance_date->toDateString(),
            'check_in' => $r->check_in_time?->format('H:i'),
            'check_out' => $r->check_out_time?->format('H:i'),
            'worked_hours' => (float) $r->working_hours,
            'status' => $r->status,
            'status_label' => Attendance::STATUSES[$r->status] ?? $r->status,
            'method' => $r->attendance_type,
        ]);

        return response()->json([
            'success' => true,
            'data' => $formatted,
            'meta' => [
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
            ],
        ]);
    }

    /**
     * Get attendance summary.
     * GET /api/v2/attendance/summary
     */
    public function summary(Request $request): JsonResponse
    {
        $staffProfile = $this->staffProfile();

        if (!$staffProfile) {
            return $this->forbidden(__('mobile_api::mobile.auth.not_staff'));
        }

        if (!class_exists(Attendance::class)) {
            return $this->success([
                'total_days' => 0,
                'total_hours' => 0,
                'average_hours' => 0,
            ]);
        }

        $month = $request->integer('month', now()->month);
        $year = $request->integer('year', now()->year);

        $records = Attendance::where('staff_profile_id', $staffProfile->id)
            ->whereMonth('attendance_date', $month)
            ->whereYear('attendance_date', $year)
            ->whereNotNull('check_out_time')
            ->get();

        $totalHours = $records->sum('working_hours');
        $totalBreakMinutes = 0;

        foreach ($records as $record) {
            $totalBreakMinutes += $record->breaks()->sum('duration_minutes');
        }

        return $this->success([
            'month' => $month,
            'year' => $year,
            'total_days' => $records->count(),
            'total_hours' => round($totalHours, 1),
            'average_hours' => $records->count() > 0 ? round($totalHours / $records->count(), 1) : 0,
            'total_break_minutes' => $totalBreakMinutes,
            'present_days' => $records->where('status', Attendance::STATUS_PRESENT)->count(),
            'half_days' => $records->where('status', Attendance::STATUS_HALF_DAY)->count(),
        ]);
    }

    /**
     * Get violations.
     * GET /api/v2/attendance/violations
     */
    public function violations(): JsonResponse
    {
        $staffProfile = $this->staffProfile();

        if (!$staffProfile) {
            return $this->forbidden(__('mobile_api::mobile.auth.not_staff'));
        }

        if (!class_exists(AttendanceViolation::class)) {
            return $this->success([]);
        }

        $violations = AttendanceViolation::where('staff_profile_id', $staffProfile->id)
            ->orderByDesc('violation_date')
            ->paginate($this->getPerPage());

        $formatted = collect($violations->items())->map(fn($v) => [
            'id' => $v->id,
            'date' => $v->violation_date->toDateString(),
            'type' => $v->violation_type,
            'type_label' => AttendanceViolation::TYPES[$v->violation_type] ?? $v->violation_type,
            'duration' => $v->formatted_violation_duration,
            'penalty' => $v->formatted_penalty,
            'status' => $v->status,
            'status_label' => AttendanceViolation::STATUSES[$v->status] ?? $v->status,
            'can_dispute' => $v->status === AttendanceViolation::STATUS_PENDING,
        ]);

        return response()->json([
            'success' => true,
            'data' => $formatted,
            'meta' => [
                'current_page' => $violations->currentPage(),
                'last_page' => $violations->lastPage(),
                'per_page' => $violations->perPage(),
                'total' => $violations->total(),
            ],
        ]);
    }

    /**
     * Dispute a violation.
     * POST /api/v2/attendance/violations/{id}/dispute
     */
    public function dispute(Request $request, int $id): JsonResponse
    {
        $staffProfile = $this->staffProfile();

        if (!$staffProfile) {
            return $this->forbidden(__('mobile_api::mobile.auth.not_staff'));
        }

        $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        if (!class_exists(AttendanceViolation::class)) {
            return $this->error('Attendance module not available', 503);
        }

        $violation = AttendanceViolation::where('staff_profile_id', $staffProfile->id)
            ->find($id);

        if (!$violation) {
            return $this->notFound();
        }

        if ($violation->status !== AttendanceViolation::STATUS_PENDING) {
            return $this->error(__('mobile_api::mobile.attendance.cannot_dispute'), 400);
        }

        $violation->update([
            'dispute_reason' => $request->reason,
            'status' => AttendanceViolation::STATUS_DISPUTED,
        ]);

        return $this->success(null, __('mobile_api::mobile.attendance.dispute_submitted'));
    }

    /**
     * Validate QR code.
     * POST /api/v2/attendance/validate/qr
     */
    public function validateQr(Request $request): JsonResponse
    {
        $request->validate([
            'qr_code' => 'required|string',
            'type' => 'sometimes|in:qr_static,qr_dynamic',
        ]);

        $type = $request->type ?? 'qr_static';
        $validation = $this->validateQrCode($request->qr_code, $type);

        return $this->success($validation);
    }

    /**
     * Validate geofence.
     * POST /api/v2/attendance/validate/geofence
     */
    public function validateGeofence(Request $request): JsonResponse
    {
        $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        $validation = $this->validateGeofenceLocation($request->latitude, $request->longitude);

        return $this->success($validation);
    }

    /**
     * Get geofence locations.
     * GET /api/v2/attendance/geofence/locations
     */
    public function getGeofenceLocations(): JsonResponse
    {
        $tenant = $this->tenant();

        if (!class_exists(\Modules\Core\Models\Branch::class)) {
            return $this->success([]);
        }

        $branches = \Modules\Core\Models\Branch::whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get();

        $defaultRadius = 100;

        // Get geofence settings if available
        if (class_exists(AttendanceTypeSetting::class)) {
            $geofenceSetting = AttendanceTypeSetting::getForType(Attendance::TYPE_GEOFENCE, $this->branch()?->id);
            if ($geofenceSetting) {
                $defaultRadius = $geofenceSetting->getSetting('radius_meters', 100);
            }
        }

        return $this->success($branches->map(fn($b) => [
            'id' => $b->id,
            'name' => $b->name,
            'latitude' => (float) $b->latitude,
            'longitude' => (float) $b->longitude,
            'radius' => $b->geofence_radius ?? $defaultRadius,
        ])->all());
    }

    // Helper methods

    protected function getEnabledCheckInMethods(): array
    {
        $methods = ['manual'];

        if (!class_exists(AttendanceTypeSetting::class)) {
            return $methods;
        }

        $enabledTypes = AttendanceTypeSetting::getEnabledTypes($this->branch()?->id);

        return array_merge($methods, $enabledTypes);
    }

    protected function getClientSettings(AttendanceTypeSetting $setting): array
    {
        // Return only settings that the mobile client needs
        $type = $setting->type;
        $allSettings = $setting->getMergedSettings();

        return match ($type) {
            Attendance::TYPE_GEOFENCE => [
                'radius_meters' => $allSettings['radius_meters'] ?? 100,
                'require_high_accuracy' => $allSettings['require_high_accuracy'] ?? true,
            ],
            Attendance::TYPE_QR_STATIC => [
                'allow_camera_only' => $allSettings['allow_camera_only'] ?? true,
            ],
            Attendance::TYPE_QR_DYNAMIC => [
                'refresh_interval_seconds' => $allSettings['refresh_interval_seconds'] ?? 30,
                'display_countdown' => $allSettings['display_countdown'] ?? true,
            ],
            default => [],
        };
    }

    protected function validateQrCode(string $qrCode, string $type): array
    {
        if (!class_exists(AttendanceTypeSetting::class)) {
            return ['valid' => false, 'reason' => 'not_configured'];
        }

        $setting = AttendanceTypeSetting::getForType($type, $this->branch()?->id);

        if (!$setting) {
            return ['valid' => false, 'reason' => 'not_enabled'];
        }

        if ($type === Attendance::TYPE_QR_STATIC) {
            $valid = $setting->validateStaticQrCode($qrCode);
            return ['valid' => $valid, 'reason' => $valid ? null : 'invalid_code'];
        }

        if ($type === Attendance::TYPE_QR_DYNAMIC) {
            $valid = $setting->validateDynamicCode($qrCode);
            return ['valid' => $valid, 'reason' => $valid ? null : 'expired_or_invalid'];
        }

        return ['valid' => false, 'reason' => 'unknown_type'];
    }

    protected function validateGeofenceLocation(float $lat, float $lng): array
    {
        $branch = $this->branch();

        if (!$branch || !$branch->latitude || !$branch->longitude) {
            return ['valid' => true, 'distance' => null, 'message' => 'No geofence configured'];
        }

        $radius = 100;

        // Get geofence settings
        if (class_exists(AttendanceTypeSetting::class)) {
            $setting = AttendanceTypeSetting::getForType(Attendance::TYPE_GEOFENCE, $branch->id);
            if ($setting) {
                $radius = $setting->getSetting('radius_meters', 100);
            }
        }

        $distance = $this->calculateDistance($lat, $lng, $branch->latitude, $branch->longitude);

        return [
            'valid' => $distance <= $radius,
            'distance' => round($distance),
            'allowed_radius' => $radius,
            'branch_name' => $branch->name,
        ];
    }

    protected function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000; // meters

        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLng = deg2rad($lng2 - $lng1);

        $a = sin($deltaLat / 2) * sin($deltaLat / 2) +
            cos($lat1Rad) * cos($lat2Rad) *
            sin($deltaLng / 2) * sin($deltaLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
