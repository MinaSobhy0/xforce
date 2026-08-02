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
        $staffProfile = $this->staffProfile();

        if (! class_exists(AttendanceTypeSetting::class)) {
            // Return default manual type if module not available
            $types = [[
                'type' => 'manual',
                'label' => 'Manual',
                'enabled' => true,
                'allowed' => true,
                'settings' => [],
            ]];

            return $this->success([
                'types' => $types,
                'staff_restrictions' => null,
            ]);
        }

        // Get enabled types for this branch
        $settings = AttendanceTypeSetting::enabled()
            ->forBranch($branch?->id)
            ->get();

        // Get staff's allowed methods (null = all allowed)
        $staffAllowedMethods = $staffProfile?->getAllowedCheckInMethods() ?? array_keys(\Modules\Staff\Models\StaffProfile::CHECK_IN_METHODS);

        // Manual is always listed; whether it is usable comes from the
        // Attendance Settings toggle (enabled by default when unset).
        $types = [[
            'type' => Attendance::TYPE_MANUAL,
            'label' => Attendance::TYPES[Attendance::TYPE_MANUAL],
            'enabled' => AttendanceTypeSetting::isManualEnabled($branch?->id),
            'allowed' => in_array('manual', $staffAllowedMethods),
            'settings' => [],
        ]];

        foreach ($settings as $setting) {
            // Already rendered above from its dedicated entry.
            if ($setting->type === Attendance::TYPE_MANUAL) {
                continue;
            }

            $types[] = [
                'type' => $setting->type,
                'label' => Attendance::TYPES[$setting->type] ?? $setting->type,
                'enabled' => $setting->is_enabled,
                'allowed' => in_array($setting->type, $staffAllowedMethods),
                'settings' => $this->getClientSettings($setting),
            ];
        }

        // Get allowed geofence locations for this staff
        $allowedGeofenceLocations = $staffProfile?->getAllowedGeofenceLocations();

        return $this->success([
            'types' => $types,
            'staff_restrictions' => [
                'allowed_methods' => $staffAllowedMethods,
                'allowed_geofence_locations' => $allowedGeofenceLocations,
                'has_method_restrictions' => $staffProfile?->allowed_check_in_methods !== null,
                'has_location_restrictions' => $allowedGeofenceLocations !== null,
            ],
        ]);
    }

    /**
     * Get current attendance status.
     * GET /api/v2/attendance/status
     */
    public function status(): JsonResponse
    {
        $staffProfile = $this->staffProfile();

        if (! $staffProfile) {
            return $this->forbidden(__('mobile_api::mobile.auth.not_staff'));
        }

        if (! class_exists(Attendance::class)) {
            return $this->error('Attendance module not available', 503);
        }

        // Check for attendance record (exclude soft-deleted - those can be restored on check-in)
        $attendance = Attendance::where('staff_profile_id', $staffProfile->id)
            ->whereDate('attendance_date', today())
            ->first();

        if (! $attendance) {
            // Check if there's a soft-deleted record (can be restored)
            $hasTrashedRecord = Attendance::onlyTrashed()
                ->where('staff_profile_id', $staffProfile->id)
                ->whereDate('attendance_date', today())
                ->exists();

            return $this->success([
                'status' => 'not_checked_in',
                'can_check_in' => true,
                'can_check_out' => false,
                'can_start_break' => false,
                'can_end_break' => false,
                'has_deleted_record' => $hasTrashedRecord,
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
            'can_check_out' => ! $isCheckedOut && ! $isOnBreak,
            'can_start_break' => ! $isCheckedOut && ! $isOnBreak,
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
            'location_required' => $this->geofenceEnforced(),
            'qr_enabled' => $features['qr_check_in'] ?? true,
            'break_tracking' => $features['break_tracking'] ?? true,
            'photo_required' => $features['attendance_photo_required'] ?? false,
            // Offline punch queue: the app may record punches (with location)
            // while offline, must show `offline_message` to the user, and
            // syncs them via POST /attendance/sync once back online. The
            // backend re-validates every punch's location and refuses
            // out-of-geofence ones.
            'offline_sync' => [
                'enabled' => true,
                'max_age_days' => 7,
                'max_batch_size' => 50,
                'offline_message' => __('mobile_api::mobile.attendance.offline_queued'),
            ],
        ]);
    }

    /**
     * Check in.
     * POST /api/v2/attendance/check-in
     */
    public function checkIn(Request $request): JsonResponse
    {
        $staffProfile = $this->staffProfile();

        if (! $staffProfile) {
            return $this->forbidden(__('mobile_api::mobile.auth.not_staff'));
        }

        $request->validate([
            'method' => 'required|in:manual,geofence,qr_static,qr_dynamic,biometric',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'qr_code' => 'required_if:method,qr_static,qr_dynamic|string',
            'photo' => 'sometimes|string',
        ]);

        if (! class_exists(Attendance::class)) {
            return $this->error('Attendance module not available', 503);
        }

        // Two-level method gate, general first: (1) the tenant/branch-wide
        // availability of the method itself (Attendance Settings; manual
        // defaults to enabled, every other method needs an enabled settings
        // row), then (2) the per-employee restriction. Distinct error_codes
        // let the app tell "disabled by the clinic" apart from "not allowed
        // for you".
        if (class_exists(AttendanceTypeSetting::class)
            && ! AttendanceTypeSetting::isMethodEnabled($request->method, $this->branch()?->id)) {
            return $this->error(__('mobile_api::mobile.attendance.method_disabled'), 403, null, 'METHOD_DISABLED');
        }

        if (! $staffProfile->isCheckInMethodAllowed($request->method)) {
            return $this->error(__('mobile_api::mobile.attendance.method_not_allowed'), 403, null, 'METHOD_NOT_ALLOWED');
        }

        // Location/method validation runs BEFORE any record is touched —
        // including the soft-deleted-restore path below, which previously
        // skipped it.
        $locationVerified = null;

        if ($request->method === 'geofence') {
            if (! $request->latitude || ! $request->longitude) {
                return $this->error(__('mobile_api::mobile.attendance.location_required'), 400);
            }
            $validation = $this->validateGeofenceLocation($request->latitude, $request->longitude, $staffProfile);
            if (! $validation['valid']) {
                return $this->businessRuleError(
                    __('mobile_api::mobile.attendance.invalid_location'),
                    'OUT_OF_GEOFENCE'
                );
            }
            $locationVerified = true;
        }

        // TC-8/9: SECURITY — if coordinates were provided (offline-synced
        // check-in, QR scan that also captured location, etc.), validate
        // them against the geofence even when method != geofence. The
        // client can't be trusted to have run its own check.
        if ($request->method !== 'geofence' && $request->filled('latitude') && $request->filled('longitude')) {
            $validation = $this->validateGeofenceLocation($request->latitude, $request->longitude, $staffProfile);
            if (! $validation['valid']) {
                return $this->businessRuleError(
                    __('mobile_api::mobile.attendance.invalid_location'),
                    'OUT_OF_GEOFENCE'
                );
            }
            $locationVerified = true;
        }

        // A manual punch without coordinates is refused when the tenant has
        // a configured geofence — omitting the location is not a bypass.
        if ($request->method === 'manual'
            && (! $request->filled('latitude') || ! $request->filled('longitude'))
            && $this->geofenceEnforced()) {
            return $this->businessRuleError(
                __('mobile_api::mobile.attendance.location_required'),
                'LOCATION_REQUIRED'
            );
        }

        if (in_array($request->method, ['qr_static', 'qr_dynamic'])) {
            $validation = $this->validateQrCode($request->qr_code, $request->method);
            if (! $validation['valid']) {
                return $this->error(__('mobile_api::mobile.attendance.invalid_qr'), 400);
            }
        }

        // Check if already has attendance record today (including soft-deleted)
        $existing = Attendance::withTrashed()
            ->where('staff_profile_id', $staffProfile->id)
            ->whereDate('attendance_date', today())
            ->first();

        if ($existing) {
            // If soft-deleted, restore it instead of creating new
            if ($existing->trashed()) {
                $existing->restore();
                $existing->update([
                    'check_in_time' => now(),
                    'check_out_time' => null,
                    'attendance_type' => $request->method,
                    'status' => Attendance::STATUS_PRESENT,
                    'working_hours' => 0,
                    'check_in_latitude' => $request->latitude,
                    'check_in_longitude' => $request->longitude,
                    'location_verified' => $locationVerified,
                ]);

                return $this->success([
                    'id' => $existing->id,
                    'check_in_time' => $existing->check_in_time->format('H:i'),
                ], __('mobile_api::mobile.attendance.checked_in'));
            }

            // Already checked in today
            if ($existing->check_out_time) {
                // Already completed attendance for today
                return $this->error(__('mobile_api::mobile.attendance.already_completed_today'), 400);
            }

            // Still checked in (no check out yet)
            return $this->error(__('mobile_api::mobile.attendance.already_checked_in'), 400);
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
                'check_in_latitude' => $request->latitude,
                'check_in_longitude' => $request->longitude,
                'location_verified' => $locationVerified,
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

        if (! $staffProfile) {
            return $this->forbidden(__('mobile_api::mobile.auth.not_staff'));
        }

        $request->validate([
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        if (! class_exists(Attendance::class)) {
            return $this->error('Attendance module not available', 503);
        }

        // Same rules as check-in (TC-8/9): coordinates, when provided, must
        // pass the backend geofence check — the client is not trusted — and
        // when the tenant has a configured geofence they are required.
        if ($request->filled('latitude') && $request->filled('longitude')) {
            $validation = $this->validateGeofenceLocation($request->latitude, $request->longitude, $staffProfile);
            if (! $validation['valid']) {
                return $this->businessRuleError(
                    __('mobile_api::mobile.attendance.invalid_location'),
                    'OUT_OF_GEOFENCE'
                );
            }
        } elseif ($this->geofenceEnforced()) {
            return $this->businessRuleError(
                __('mobile_api::mobile.attendance.location_required'),
                'LOCATION_REQUIRED'
            );
        }

        $attendance = Attendance::where('staff_profile_id', $staffProfile->id)
            ->whereDate('attendance_date', today())
            ->whereNull('check_out_time')
            ->first();

        if (! $attendance) {
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
            'check_out_latitude' => $request->latitude,
            'check_out_longitude' => $request->longitude,
        ]);

        return $this->success([
            'check_out_time' => $attendance->check_out_time->format('H:i'),
            'worked_hours' => $workedHours,
        ], __('mobile_api::mobile.attendance.checked_out'));
    }

    /**
     * Whether the tenant enforces location-verified punches: the geofence
     * feature is on AND a fence is actually configured (custom locations or
     * branch coordinates). Without a configured fence there is nothing to
     * verify against, so coordinates are not demanded.
     */
    protected function geofenceEnforced(): bool
    {
        $features = $this->tenant()->getMobileAppConfig()['features'] ?? [];
        if (! ($features['geofence_check_in'] ?? true)) {
            return false;
        }

        if (class_exists(AttendanceTypeSetting::class)) {
            $setting = AttendanceTypeSetting::getForType(Attendance::TYPE_GEOFENCE, $this->branch()?->id);
            if ($setting && ! empty($setting->getSetting('locations', []))) {
                return true;
            }
        }

        if (class_exists(\Modules\Core\Models\Branch::class)) {
            try {
                return \Modules\Core\Models\Branch::active()
                    ->whereNotNull('latitude')
                    ->whereNotNull('longitude')
                    ->exists();
            } catch (\Throwable) {
                return false;
            }
        }

        return false;
    }

    /**
     * Sync offline-recorded punches.
     * POST /api/v2/attendance/sync
     *
     * The app queues check-in/out punches taken while offline (each with the
     * coordinates and timestamp captured at punch time) and submits them here
     * once connectivity returns. The backend is the authority: every punch is
     * re-validated against the geofence — a punch outside it is REFUSED, one
     * inside is accepted with its original timestamp. Per-punch results let
     * the app tell the user exactly which punches were rejected and why.
     */
    public function syncOffline(Request $request): JsonResponse
    {
        $staffProfile = $this->staffProfile();

        if (! $staffProfile) {
            return $this->forbidden(__('mobile_api::mobile.auth.not_staff'));
        }

        if (! class_exists(Attendance::class)) {
            return $this->error('Attendance module not available', 503);
        }

        $validated = $request->validate([
            'punches' => 'required|array|min:1|max:50',
            'punches.*.client_id' => 'required|string|max:64',
            'punches.*.type' => 'required|in:check_in,check_out',
            'punches.*.recorded_at' => 'required|date',
            'punches.*.latitude' => 'required|numeric',
            'punches.*.longitude' => 'required|numeric',
            'punches.*.method' => 'sometimes|in:manual,geofence,qr_static,qr_dynamic,biometric',
        ]);

        // Chronological order so a check_in/check_out pair in one batch
        // resolves correctly regardless of submission order.
        $punches = collect($validated['punches'])
            ->sortBy(fn ($p) => \Carbon\Carbon::parse($p['recorded_at'])->timestamp)
            ->values();

        $results = [];

        foreach ($punches as $punch) {
            $results[] = $this->processOfflinePunch($punch, $staffProfile);
        }

        $accepted = collect($results)->where('accepted', true)->count();

        return $this->success([
            'results' => $results,
            'accepted' => $accepted,
            'rejected' => count($results) - $accepted,
        ], __('mobile_api::mobile.attendance.offline_synced', [
            'accepted' => $accepted,
            'total' => count($results),
        ]));
    }

    /**
     * Validate and apply a single offline punch. Returns a result row for
     * the sync response; never throws for business rejections.
     */
    protected function processOfflinePunch(array $punch, $staffProfile): array
    {
        $reject = fn (string $code, string $message) => [
            'client_id' => $punch['client_id'],
            'accepted' => false,
            'error_code' => $code,
            'message' => $message,
        ];

        $recordedAt = \Carbon\Carbon::parse($punch['recorded_at']);

        // Timestamp sanity: small forward skew allowed, and punches older
        // than 7 days are refused — the queue should not resurrect history.
        if ($recordedAt->isAfter(now()->addMinutes(5))) {
            return $reject('FUTURE_PUNCH', __('mobile_api::mobile.attendance.punch_in_future'));
        }
        if ($recordedAt->isBefore(now()->subDays(7))) {
            return $reject('PUNCH_TOO_OLD', __('mobile_api::mobile.attendance.punch_too_old'));
        }

        // Backend geofence verdict — the whole point of the offline flow:
        // "we'll check your location is correct". Outside → refused.
        $validation = $this->validateGeofenceLocation(
            (float) $punch['latitude'],
            (float) $punch['longitude'],
            $staffProfile
        );
        if (! $validation['valid']) {
            return $reject('OUT_OF_GEOFENCE', __('mobile_api::mobile.attendance.invalid_location'));
        }

        $method = $punch['method'] ?? Attendance::TYPE_MANUAL;
        $punchDate = $recordedAt->toDateString();

        $existing = Attendance::where('staff_profile_id', $staffProfile->id)
            ->whereDate('attendance_date', $punchDate)
            ->first();

        if ($punch['type'] === 'check_in') {
            // Same two-level gate as live check-in: general method
            // availability first, then the per-employee restriction.
            if (class_exists(AttendanceTypeSetting::class)
                && ! AttendanceTypeSetting::isMethodEnabled($method, $this->branch()?->id)) {
                return $reject('METHOD_DISABLED', __('mobile_api::mobile.attendance.method_disabled'));
            }
            if (! $staffProfile->isCheckInMethodAllowed($method)) {
                return $reject('METHOD_NOT_ALLOWED', __('mobile_api::mobile.attendance.method_not_allowed'));
            }
            if ($existing && $existing->check_out_time) {
                return $reject('ALREADY_COMPLETED', __('mobile_api::mobile.attendance.already_completed_today'));
            }
            if ($existing) {
                return $reject('ALREADY_CHECKED_IN', __('mobile_api::mobile.attendance.already_checked_in'));
            }

            try {
                $attendance = Attendance::create([
                    'tenant_id' => current_tenant_id(),
                    'staff_profile_id' => $staffProfile->id,
                    'branch_id' => $this->branch()?->id,
                    'attendance_date' => $punchDate,
                    'check_in_time' => $recordedAt,
                    'attendance_type' => $method,
                    'status' => Attendance::STATUS_PRESENT,
                    'check_in_latitude' => $punch['latitude'],
                    'check_in_longitude' => $punch['longitude'],
                    'is_offline_entry' => true,
                    'location_verified' => true,
                ]);
            } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                return $reject('ALREADY_CHECKED_IN', __('mobile_api::mobile.attendance.already_checked_in'));
            }

            return [
                'client_id' => $punch['client_id'],
                'accepted' => true,
                'attendance_id' => $attendance->id,
            ];
        }

        // check_out
        if (! $existing || ! $existing->check_in_time) {
            return $reject('NO_CHECK_IN', __('mobile_api::mobile.attendance.not_checked_in'));
        }
        if ($existing->check_out_time) {
            return $reject('ALREADY_COMPLETED', __('mobile_api::mobile.attendance.already_completed_today'));
        }

        // check_in_time is a TIME column — anchor it to the attendance date
        // before comparing against the punch's full timestamp.
        $checkInAt = $existing->attendance_date->copy()
            ->setTimeFromTimeString($existing->check_in_time->format('H:i:s'));

        if ($recordedAt->lessThanOrEqualTo($checkInAt)) {
            return $reject('INVALID_SEQUENCE', __('mobile_api::mobile.attendance.punch_before_check_in'));
        }

        $totalMinutes = $checkInAt->diffInMinutes($recordedAt);
        $breakMinutes = $existing->breaks()->sum('duration_minutes');
        $workedHours = round(max(0, $totalMinutes - $breakMinutes) / 60, 2);

        $existing->update([
            'check_out_time' => $recordedAt,
            'working_hours' => $workedHours,
            'check_out_latitude' => $punch['latitude'],
            'check_out_longitude' => $punch['longitude'],
            'is_offline_entry' => true,
        ]);

        return [
            'client_id' => $punch['client_id'],
            'accepted' => true,
            'attendance_id' => $existing->id,
            'worked_hours' => $workedHours,
        ];
    }

    /**
     * Start break.
     * POST /api/v2/attendance/break/start
     */
    public function startBreak(Request $request): JsonResponse
    {
        $staffProfile = $this->staffProfile();

        if (! $staffProfile) {
            return $this->forbidden(__('mobile_api::mobile.auth.not_staff'));
        }

        if (! class_exists(Attendance::class)) {
            return $this->error('Attendance module not available', 503);
        }

        $attendance = Attendance::where('staff_profile_id', $staffProfile->id)
            ->whereDate('attendance_date', today())
            ->whereNull('check_out_time')
            ->first();

        if (! $attendance) {
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

        if (! $staffProfile) {
            return $this->forbidden(__('mobile_api::mobile.auth.not_staff'));
        }

        if (! class_exists(Attendance::class)) {
            return $this->error('Attendance module not available', 503);
        }

        $attendance = Attendance::where('staff_profile_id', $staffProfile->id)
            ->whereDate('attendance_date', today())
            ->whereNull('check_out_time')
            ->first();

        if (! $attendance) {
            return $this->error(__('mobile_api::mobile.attendance.not_checked_in'), 400);
        }

        $break = $attendance->breaks()
            ->whereNull('end_time')
            ->first();

        if (! $break) {
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

        if (! $staffProfile) {
            return $this->forbidden(__('mobile_api::mobile.auth.not_staff'));
        }

        if (! class_exists(Attendance::class)) {
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

        $formatted = collect($records->items())->map(fn ($r) => [
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

        if (! $staffProfile) {
            return $this->forbidden(__('mobile_api::mobile.auth.not_staff'));
        }

        if (! class_exists(Attendance::class)) {
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

        if (! $staffProfile) {
            return $this->forbidden(__('mobile_api::mobile.auth.not_staff'));
        }

        if (! class_exists(AttendanceViolation::class)) {
            return $this->success([]);
        }

        $violations = AttendanceViolation::where('staff_profile_id', $staffProfile->id)
            ->orderByDesc('violation_date')
            ->paginate($this->getPerPage());

        $formatted = collect($violations->items())->map(fn ($v) => [
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

        if (! $staffProfile) {
            return $this->forbidden(__('mobile_api::mobile.auth.not_staff'));
        }

        $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        if (! class_exists(AttendanceViolation::class)) {
            return $this->error('Attendance module not available', 503);
        }

        $violation = AttendanceViolation::where('staff_profile_id', $staffProfile->id)
            ->find($id);

        if (! $violation) {
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
     * Get current dynamic QR code for display.
     * GET /api/v2/attendance/qr-dynamic/current
     */
    public function getDynamicQr(): JsonResponse
    {
        if (! class_exists(AttendanceTypeSetting::class)) {
            return $this->error('Attendance module not available', 503);
        }

        $setting = AttendanceTypeSetting::getForType(Attendance::TYPE_QR_DYNAMIC, $this->branch()?->id);

        if (! $setting) {
            return $this->error('Dynamic QR not enabled', 400);
        }

        $code = $setting->getCurrentDynamicCode();

        if (! $code) {
            return $this->error('Dynamic QR not configured', 400);
        }

        $refreshInterval = $setting->getSetting('refresh_interval_seconds', 30);
        $currentTime = time();
        $nextRefresh = (floor($currentTime / $refreshInterval) + 1) * $refreshInterval;
        $secondsRemaining = $nextRefresh - $currentTime;

        return $this->success([
            'code' => $code,
            'refresh_interval' => $refreshInterval,
            'seconds_remaining' => $secondsRemaining,
            'expires_at' => now()->addSeconds($secondsRemaining)->toIso8601String(),
        ]);
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
        $locations = [];
        $defaultRadius = 100;
        $staffProfile = $this->staffProfile();

        // Get staff's allowed geofence locations (null = all allowed)
        $allowedBranchIds = $staffProfile?->getAllowedGeofenceLocations();

        // Get geofence settings if available
        if (class_exists(AttendanceTypeSetting::class)) {
            $geofenceSetting = AttendanceTypeSetting::getForType(Attendance::TYPE_GEOFENCE, $this->branch()?->id);
            if ($geofenceSetting) {
                $defaultRadius = $geofenceSetting->getSetting('radius_meters', 100);

                // Get custom locations from settings
                $customLocations = $geofenceSetting->getSetting('locations', []);
                foreach ($customLocations as $loc) {
                    if (! empty($loc['lat']) && ! empty($loc['lng'])) {
                        $locationBranchId = $loc['branch_id'] ?? null;
                        $isAllowed = $allowedBranchIds === null ||
                            ($locationBranchId !== null && in_array((int) $locationBranchId, $allowedBranchIds));

                        $locations[] = [
                            'id' => $loc['id'] ?? null,
                            'branch_id' => $locationBranchId,
                            'name' => $loc['name'] ?? 'Custom Location',
                            'latitude' => (float) $loc['lat'],
                            'longitude' => (float) $loc['lng'],
                            'radius' => $loc['radius'] ?? $defaultRadius,
                            'source' => 'custom',
                            'allowed' => $isAllowed,
                        ];
                    }
                }
            }
        }

        // Get branches with coordinates (if columns exist)
        if (class_exists(\Modules\Core\Models\Branch::class)) {
            try {
                $branches = \Modules\Core\Models\Branch::active()
                    ->whereNotNull('latitude')
                    ->whereNotNull('longitude')
                    ->get();

                foreach ($branches as $branch) {
                    $isAllowed = $allowedBranchIds === null || in_array((int) $branch->id, $allowedBranchIds);

                    $locations[] = [
                        'id' => $branch->id,
                        'branch_id' => $branch->id,
                        'name' => $branch->name,
                        'latitude' => (float) $branch->latitude,
                        'longitude' => (float) $branch->longitude,
                        'radius' => $branch->geofence_radius ?? $defaultRadius,
                        'source' => 'branch',
                        'allowed' => $isAllowed,
                    ];
                }
            } catch (\Illuminate\Database\QueryException $e) {
                // Columns don't exist yet (migration not run) - skip branch locations
            }
        }

        return $this->success([
            'locations' => $locations,
            'default_radius' => $defaultRadius,
            'has_location_restrictions' => $allowedBranchIds !== null,
            'allowed_location_ids' => $allowedBranchIds,
            'settings' => [
                'require_high_accuracy' => class_exists(AttendanceTypeSetting::class)
                    ? (AttendanceTypeSetting::getForType(Attendance::TYPE_GEOFENCE, $this->branch()?->id)?->getSetting('require_high_accuracy', true) ?? true)
                    : true,
                'min_accuracy_meters' => class_exists(AttendanceTypeSetting::class)
                    ? (AttendanceTypeSetting::getForType(Attendance::TYPE_GEOFENCE, $this->branch()?->id)?->getSetting('min_accuracy_meters', 50) ?? 50)
                    : 50,
            ],
        ]);
    }

    // Helper methods

    protected function getEnabledCheckInMethods(): array
    {
        if (! class_exists(AttendanceTypeSetting::class)) {
            return ['manual'];
        }

        $branchId = $this->branch()?->id;
        $methods = AttendanceTypeSetting::isManualEnabled($branchId) ? ['manual'] : [];

        $enabledTypes = AttendanceTypeSetting::getEnabledTypes($branchId);

        return array_values(array_unique(array_merge($methods, $enabledTypes)));
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
        if (! class_exists(AttendanceTypeSetting::class)) {
            return ['valid' => false, 'reason' => 'not_configured'];
        }

        $setting = AttendanceTypeSetting::getForType($type, $this->branch()?->id);

        if (! $setting) {
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

    protected function validateGeofenceLocation(float $lat, float $lng, $staffProfile = null): array
    {
        $defaultRadius = 100;
        $allLocations = [];

        // Get staff's allowed geofence locations (null = all allowed)
        $allowedBranchIds = $staffProfile?->getAllowedGeofenceLocations();

        // Get geofence settings
        if (class_exists(AttendanceTypeSetting::class)) {
            $setting = AttendanceTypeSetting::getForType(Attendance::TYPE_GEOFENCE, $this->branch()?->id);
            if ($setting) {
                $defaultRadius = $setting->getSetting('radius_meters', 100);

                // Get custom locations from settings
                $customLocations = $setting->getSetting('locations', []);
                foreach ($customLocations as $loc) {
                    if (! empty($loc['lat']) && ! empty($loc['lng'])) {
                        // Check if this location is allowed for the staff
                        $locationBranchId = $loc['branch_id'] ?? null;
                        if ($allowedBranchIds !== null && $locationBranchId !== null) {
                            if (! in_array((int) $locationBranchId, $allowedBranchIds)) {
                                continue; // Skip this location
                            }
                        }

                        $allLocations[] = [
                            'branch_id' => $locationBranchId,
                            'name' => $loc['name'] ?? 'Custom Location',
                            'lat' => (float) $loc['lat'],
                            'lng' => (float) $loc['lng'],
                            'radius' => $loc['radius'] ?? $defaultRadius,
                        ];
                    }
                }
            }
        }

        // Get branches with coordinates (if columns exist)
        if (class_exists(\Modules\Core\Models\Branch::class)) {
            try {
                $branchQuery = \Modules\Core\Models\Branch::active()
                    ->whereNotNull('latitude')
                    ->whereNotNull('longitude');

                // Filter by allowed branch IDs if staff has restrictions
                if ($allowedBranchIds !== null) {
                    $branchQuery->whereIn('id', $allowedBranchIds);
                }

                $branches = $branchQuery->get();

                foreach ($branches as $branch) {
                    $allLocations[] = [
                        'branch_id' => $branch->id,
                        'name' => $branch->name,
                        'lat' => (float) $branch->latitude,
                        'lng' => (float) $branch->longitude,
                        'radius' => $branch->geofence_radius ?? $defaultRadius,
                    ];
                }
            } catch (\Illuminate\Database\QueryException $e) {
                // Columns don't exist yet (migration not run) - skip branch locations
            }
        }

        // If no locations configured/allowed, allow check-in
        if (empty($allLocations)) {
            if ($allowedBranchIds !== null) {
                // Staff has location restrictions but no valid locations found
                return ['valid' => false, 'distance' => null, 'message' => 'No allowed locations configured'];
            }

            return ['valid' => true, 'distance' => null, 'message' => 'No geofence configured'];
        }

        // Find closest location and check if within any radius
        $closestLocation = null;
        $closestDistance = PHP_FLOAT_MAX;

        foreach ($allLocations as $location) {
            $distance = $this->calculateDistance($lat, $lng, $location['lat'], $location['lng']);

            if ($distance < $closestDistance) {
                $closestDistance = $distance;
                $closestLocation = $location;
            }

            // If within this location's radius, return valid immediately
            if ($distance <= $location['radius']) {
                return [
                    'valid' => true,
                    'distance' => round($distance),
                    'allowed_radius' => $location['radius'],
                    'location_name' => $location['name'],
                    'branch_id' => $location['branch_id'] ?? null,
                ];
            }
        }

        // Not within any location
        return [
            'valid' => false,
            'distance' => round($closestDistance),
            'allowed_radius' => $closestLocation['radius'] ?? $defaultRadius,
            'location_name' => $closestLocation['name'] ?? null,
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
