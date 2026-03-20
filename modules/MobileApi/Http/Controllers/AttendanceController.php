<?php

namespace Modules\MobileApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends BaseApiController
{
    /**
     * Get current attendance status.
     * GET /api/v2/attendance/status
     */
    public function status(): JsonResponse
    {
        $staffProfile = $this->staffProfile();

        if (!class_exists(\Modules\Attendance\Models\AttendanceRecord::class)) {
            return $this->error('Attendance module not available', 503);
        }

        $attendance = \Modules\Attendance\Models\AttendanceRecord::where('staff_profile_id', $staffProfile->id)
            ->whereDate('check_in', today())
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
            ->whereNull('ended_at')
            ->first();

        $isOnBreak = $currentBreak !== null;
        $isCheckedOut = $attendance->check_out !== null;

        return $this->success([
            'status' => $isCheckedOut ? 'checked_out' : ($isOnBreak ? 'on_break' : 'checked_in'),
            'check_in_time' => $attendance->check_in->format('H:i'),
            'check_out_time' => $attendance->check_out?->format('H:i'),
            'method' => $attendance->check_in_method,
            'location' => $attendance->check_in_location,
            'worked_hours' => $this->calculateWorkedHours($attendance),
            'break_minutes' => $attendance->breaks()->sum('duration_minutes'),
            'current_break_started' => $currentBreak?->started_at?->format('H:i'),
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

        return $this->success([
            'check_in_methods' => $tenant->getMobileConfig('check_in_methods', ['manual', 'qr', 'gps']),
            'geofence_enabled' => $tenant->getMobileConfig('geofence_enabled', true),
            'geofence_radius' => $tenant->getMobileConfig('geofence_radius', 100),
            'break_tracking' => $tenant->getMobileConfig('break_tracking', true),
            'photo_required' => $tenant->getMobileConfig('photo_check_in', false),
            'dynamic_qr_enabled' => $tenant->getMobileConfig('dynamic_qr_enabled', false),
        ]);
    }

    /**
     * Check in.
     * POST /api/v2/attendance/check-in
     */
    public function checkIn(Request $request): JsonResponse
    {
        $staffProfile = $this->staffProfile();

        $request->validate([
            'method' => 'required|in:manual,qr,gps,biometric',
            'latitude' => 'required_if:method,gps|numeric',
            'longitude' => 'required_if:method,gps|numeric',
            'qr_code' => 'required_if:method,qr|string',
            'photo' => 'sometimes|string', // base64 encoded
        ]);

        if (!class_exists(\Modules\Attendance\Models\AttendanceRecord::class)) {
            return $this->error('Attendance module not available', 503);
        }

        // Check if already checked in
        $existing = \Modules\Attendance\Models\AttendanceRecord::where('staff_profile_id', $staffProfile->id)
            ->whereDate('check_in', today())
            ->whereNull('check_out')
            ->first();

        if ($existing) {
            return $this->error(__('mobile_api::mobile.attendance.already_checked_in'), 400);
        }

        // Validate check-in method
        if ($request->method === 'gps') {
            $validation = $this->validateGeofenceLocation($request->latitude, $request->longitude);
            if (!$validation['valid']) {
                return $this->error(__('mobile_api::mobile.attendance.invalid_location'), 400);
            }
        }

        if ($request->method === 'qr') {
            $validation = $this->validateQrCode($request->qr_code);
            if (!$validation['valid']) {
                return $this->error(__('mobile_api::mobile.attendance.invalid_qr'), 400);
            }
        }

        // Create attendance record
        $attendance = \Modules\Attendance\Models\AttendanceRecord::create([
            'staff_profile_id' => $staffProfile->id,
            'branch_id' => $this->branch()?->id,
            'check_in' => now(),
            'check_in_method' => $request->method,
            'check_in_location' => $request->method === 'gps' ? [
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
            ] : null,
            'check_in_photo' => $request->photo,
        ]);

        return $this->success([
            'id' => $attendance->id,
            'check_in_time' => $attendance->check_in->format('H:i'),
        ], __('mobile_api::mobile.attendance.checked_in'));
    }

    /**
     * Check out.
     * POST /api/v2/attendance/check-out
     */
    public function checkOut(Request $request): JsonResponse
    {
        $staffProfile = $this->staffProfile();

        if (!class_exists(\Modules\Attendance\Models\AttendanceRecord::class)) {
            return $this->error('Attendance module not available', 503);
        }

        $attendance = \Modules\Attendance\Models\AttendanceRecord::where('staff_profile_id', $staffProfile->id)
            ->whereDate('check_in', today())
            ->whereNull('check_out')
            ->first();

        if (!$attendance) {
            return $this->error(__('mobile_api::mobile.attendance.not_checked_in'), 400);
        }

        // End any active breaks
        $attendance->breaks()
            ->whereNull('ended_at')
            ->update([
                'ended_at' => now(),
                'duration_minutes' => \DB::raw('EXTRACT(EPOCH FROM (NOW() - started_at)) / 60'),
            ]);

        // Update check out
        $attendance->update([
            'check_out' => now(),
            'check_out_method' => $request->method ?? 'manual',
        ]);

        return $this->success([
            'check_out_time' => $attendance->check_out->format('H:i'),
            'worked_hours' => $this->calculateWorkedHours($attendance),
        ], __('mobile_api::mobile.attendance.checked_out'));
    }

    /**
     * Start break.
     * POST /api/v2/attendance/break/start
     */
    public function startBreak(Request $request): JsonResponse
    {
        $staffProfile = $this->staffProfile();

        if (!class_exists(\Modules\Attendance\Models\AttendanceRecord::class)) {
            return $this->error('Attendance module not available', 503);
        }

        $attendance = \Modules\Attendance\Models\AttendanceRecord::where('staff_profile_id', $staffProfile->id)
            ->whereDate('check_in', today())
            ->whereNull('check_out')
            ->first();

        if (!$attendance) {
            return $this->error(__('mobile_api::mobile.attendance.not_checked_in'), 400);
        }

        // Check if already on break
        $existingBreak = $attendance->breaks()
            ->whereNull('ended_at')
            ->first();

        if ($existingBreak) {
            return $this->error(__('mobile_api::mobile.attendance.already_on_break'), 400);
        }

        // Create break record
        $break = $attendance->breaks()->create([
            'started_at' => now(),
            'type' => $request->type ?? 'regular',
        ]);

        return $this->success([
            'break_id' => $break->id,
            'started_at' => $break->started_at->format('H:i'),
        ], __('mobile_api::mobile.attendance.break_started'));
    }

    /**
     * End break.
     * POST /api/v2/attendance/break/end
     */
    public function endBreak(): JsonResponse
    {
        $staffProfile = $this->staffProfile();

        if (!class_exists(\Modules\Attendance\Models\AttendanceRecord::class)) {
            return $this->error('Attendance module not available', 503);
        }

        $attendance = \Modules\Attendance\Models\AttendanceRecord::where('staff_profile_id', $staffProfile->id)
            ->whereDate('check_in', today())
            ->whereNull('check_out')
            ->first();

        if (!$attendance) {
            return $this->error(__('mobile_api::mobile.attendance.not_checked_in'), 400);
        }

        $break = $attendance->breaks()
            ->whereNull('ended_at')
            ->first();

        if (!$break) {
            return $this->error(__('mobile_api::mobile.attendance.not_on_break'), 400);
        }

        $break->update([
            'ended_at' => now(),
            'duration_minutes' => $break->started_at->diffInMinutes(now()),
        ]);

        return $this->success([
            'ended_at' => $break->ended_at->format('H:i'),
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

        if (!class_exists(\Modules\Attendance\Models\AttendanceRecord::class)) {
            return $this->success([]);
        }

        $query = \Modules\Attendance\Models\AttendanceRecord::where('staff_profile_id', $staffProfile->id)
            ->orderByDesc('check_in');

        if ($request->filled('month')) {
            $query->whereMonth('check_in', $request->month);
        }

        if ($request->filled('year')) {
            $query->whereYear('check_in', $request->year);
        }

        $records = $query->paginate($this->getPerPage());

        return $this->paginated($records);
    }

    /**
     * Get attendance summary.
     * GET /api/v2/attendance/summary
     */
    public function summary(Request $request): JsonResponse
    {
        $staffProfile = $this->staffProfile();

        if (!class_exists(\Modules\Attendance\Models\AttendanceRecord::class)) {
            return $this->success([
                'total_days' => 0,
                'total_hours' => 0,
                'average_hours' => 0,
            ]);
        }

        $month = $request->month ?? now()->month;
        $year = $request->year ?? now()->year;

        $records = \Modules\Attendance\Models\AttendanceRecord::where('staff_profile_id', $staffProfile->id)
            ->whereMonth('check_in', $month)
            ->whereYear('check_in', $year)
            ->whereNotNull('check_out')
            ->get();

        $totalMinutes = $records->sum(fn($r) => $r->check_in->diffInMinutes($r->check_out));
        $totalBreakMinutes = 0;

        foreach ($records as $record) {
            $totalBreakMinutes += $record->breaks()->sum('duration_minutes');
        }

        $workedMinutes = $totalMinutes - $totalBreakMinutes;
        $workedHours = round($workedMinutes / 60, 1);

        return $this->success([
            'month' => $month,
            'year' => $year,
            'total_days' => $records->count(),
            'total_hours' => $workedHours,
            'average_hours' => $records->count() > 0 ? round($workedHours / $records->count(), 1) : 0,
            'total_break_minutes' => $totalBreakMinutes,
        ]);
    }

    /**
     * Get violations.
     * GET /api/v2/attendance/violations
     */
    public function violations(): JsonResponse
    {
        $staffProfile = $this->staffProfile();

        if (!class_exists(\Modules\Attendance\Models\AttendanceViolation::class)) {
            return $this->success([]);
        }

        $violations = \Modules\Attendance\Models\AttendanceViolation::where('staff_profile_id', $staffProfile->id)
            ->orderByDesc('created_at')
            ->paginate($this->getPerPage());

        return $this->paginated($violations);
    }

    /**
     * Dispute a violation.
     * POST /api/v2/attendance/violations/{id}/dispute
     */
    public function dispute(Request $request, int $id): JsonResponse
    {
        $staffProfile = $this->staffProfile();

        $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        if (!class_exists(\Modules\Attendance\Models\AttendanceViolation::class)) {
            return $this->error('Attendance module not available', 503);
        }

        $violation = \Modules\Attendance\Models\AttendanceViolation::where('staff_profile_id', $staffProfile->id)
            ->find($id);

        if (!$violation) {
            return $this->notFound();
        }

        $violation->update([
            'dispute_reason' => $request->reason,
            'dispute_submitted_at' => now(),
            'status' => 'disputed',
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
        ]);

        $validation = $this->validateQrCode($request->qr_code);

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
     * Get dynamic QR code.
     * GET /api/v2/attendance/qr-dynamic/current
     */
    public function getDynamicQr(): JsonResponse
    {
        $branch = $this->branch();

        if (!$branch) {
            return $this->error('No branch context', 400);
        }

        // Generate a time-based QR code that expires
        $qrData = encrypt([
            'branch_id' => $branch->id,
            'timestamp' => now()->timestamp,
            'expires_at' => now()->addMinutes(5)->timestamp,
        ]);

        return $this->success([
            'qr_code' => $qrData,
            'expires_in' => 300, // 5 minutes
        ]);
    }

    /**
     * Get geofence locations.
     * GET /api/v2/attendance/geofence/locations
     */
    public function getGeofenceLocations(): JsonResponse
    {
        $tenant = $this->tenant();

        // Get branch locations with geofence config
        if (!class_exists(\Modules\Core\Models\Branch::class)) {
            return $this->success([]);
        }

        $branches = \Modules\Core\Models\Branch::whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get();

        $defaultRadius = $tenant->getMobileConfig('geofence_radius', 100);

        return $this->success($branches->map(fn($b) => [
            'id' => $b->id,
            'name' => $b->name,
            'latitude' => $b->latitude,
            'longitude' => $b->longitude,
            'radius' => $b->geofence_radius ?? $defaultRadius,
        ])->all());
    }

    // Helper methods

    protected function calculateWorkedHours($attendance): float
    {
        $checkOut = $attendance->check_out ?? now();
        $totalMinutes = $attendance->check_in->diffInMinutes($checkOut);
        $breakMinutes = $attendance->breaks()->sum('duration_minutes');

        return round(($totalMinutes - $breakMinutes) / 60, 1);
    }

    protected function validateQrCode(string $qrCode): array
    {
        try {
            $data = decrypt($qrCode);

            if (!isset($data['expires_at']) || $data['expires_at'] < now()->timestamp) {
                return ['valid' => false, 'reason' => 'expired'];
            }

            return ['valid' => true, 'branch_id' => $data['branch_id'] ?? null];
        } catch (\Exception $e) {
            return ['valid' => false, 'reason' => 'invalid'];
        }
    }

    protected function validateGeofenceLocation(float $lat, float $lng): array
    {
        $branch = $this->branch();

        if (!$branch || !$branch->latitude || !$branch->longitude) {
            // No geofence configured, allow
            return ['valid' => true, 'distance' => null];
        }

        $radius = $branch->geofence_radius ?? $this->tenant()->getMobileConfig('geofence_radius', 100);
        $distance = $this->calculateDistance($lat, $lng, $branch->latitude, $branch->longitude);

        return [
            'valid' => $distance <= $radius,
            'distance' => round($distance),
            'allowed_radius' => $radius,
        ];
    }

    protected function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        // Haversine formula
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
