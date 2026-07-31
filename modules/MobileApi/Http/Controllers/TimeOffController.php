<?php

namespace Modules\MobileApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Attendance\Services\AttendanceService;
use Modules\Booking\Models\PractitionerTimeOff;
use Modules\Booking\Models\TimeOffAllocation;
use Modules\Booking\Models\TimeOffType;

class TimeOffController extends BaseApiController
{
    /**
     * Get available time off types.
     * GET /api/v2/time-off/types
     */
    public function types(): JsonResponse
    {
        $staffProfile = $this->staffProfile();

        if (! class_exists(TimeOffType::class) || ! $staffProfile) {
            return $this->success([]);
        }

        // Only types the employee holds a currently-valid allocation for,
        // with balance still available to request (pending requests count
        // against it). The app must never offer a type the backend would
        // reject — this mirrors both balance() and the store() validation.
        $allocations = TimeOffAllocation::with('timeOffType')
            ->forStaffProfile($staffProfile->id)
            ->coveringDate(now())
            ->whereHas('timeOffType', fn ($q) => $q->where('is_active', true))
            ->get()
            ->filter(fn ($a) => $a->timeOffType && $a->available_for_request > 0)
            ->sortBy(fn ($a) => $a->timeOffType->sort_order ?? 0)
            ->values();

        $types = $allocations->map(fn ($a) => [
            'id' => $a->timeOffType->id,
            'name' => $a->timeOffType->translated_name,
            'code' => $a->timeOffType->code,
            'color' => $a->timeOffType->color,
            'is_paid' => $a->timeOffType->is_paid,
            'requires_approval' => $a->timeOffType->requires_approval,
            'request_unit' => $a->timeOffType->request_unit,
            'allow_half_day' => $a->timeOffType->allow_half_day,
            'hours_per_day' => $a->timeOffType->hours_per_day,
            'remaining' => (float) $a->available_for_request,
            'unit_label' => $a->timeOffType->getUnitLabel(),
        ]);

        return $this->success($types);
    }

    /**
     * Get time off balances for current user.
     * GET /api/v2/time-off/balance
     */
    public function balance(): JsonResponse
    {
        $staffProfile = $this->staffProfile();

        if (! class_exists(TimeOffAllocation::class) || ! $staffProfile) {
            return $this->success([]);
        }

        // Return allocations that cover today (Odoo-style validity).
        $allocations = TimeOffAllocation::with('timeOffType')
            ->forStaffProfile($staffProfile->id)
            ->coveringDate(now())
            ->whereHas('timeOffType', fn ($q) => $q->where('is_active', true))
            ->get();

        $balances = $allocations->map(function ($allocation) {
            $type = $allocation->timeOffType;

            return [
                'type_id' => $type->id,
                'type' => $type->translated_name,
                'code' => $type->code,
                'color' => $type->color,
                'request_unit' => $type->request_unit,
                'allocated' => (float) $allocation->allocated_days,
                'used' => (float) $allocation->used_days,
                'carried_over' => (float) $allocation->carried_over_days,
                'remaining' => (float) $allocation->remaining,
                'unit_label' => $type->getUnitLabel(),
            ];
        });

        return $this->success($balances);
    }

    /**
     * Get time off requests.
     * GET /api/v2/time-off/requests
     */
    public function requests(Request $request): JsonResponse
    {
        $staffProfile = $this->staffProfile();

        if (! class_exists(PractitionerTimeOff::class) || ! $staffProfile) {
            return $this->success([]);
        }

        $query = PractitionerTimeOff::with('timeOffType')
            ->forStaffProfile($staffProfile->id)
            ->ordered();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('year')) {
            $query->whereYear('start_date', $request->year);
        }

        $paginator = $query->paginate($this->getPerPage());

        // Map items using the formatter
        $data = collect($paginator->items())->map(fn ($r) => $this->formatTimeOffRequest($r));

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Get time off request details.
     * GET /api/v2/time-off/requests/{id}
     */
    public function show(int $id): JsonResponse
    {
        $staffProfile = $this->staffProfile();

        if (! class_exists(PractitionerTimeOff::class) || ! $staffProfile) {
            return $this->notFound();
        }

        $timeOff = PractitionerTimeOff::with(['timeOffType', 'approvedBy'])
            ->forStaffProfile($staffProfile->id)
            ->find($id);

        if (! $timeOff) {
            return $this->notFound();
        }

        return $this->success($this->formatTimeOffRequest($timeOff, true));
    }

    /**
     * Submit a time off request.
     * POST /api/v2/time-off/requests
     */
    public function store(Request $request): JsonResponse
    {
        $staffProfile = $this->staffProfile();
        if (! $staffProfile) {
            return $this->error(__('mobile_api::mobile.time_off.no_staff_profile'), 400);
        }

        $validated = $request->validate([
            'time_off_type_id' => 'required|integer|exists:time_off_types,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'is_full_day' => 'sometimes|boolean',
            'reason' => 'sometimes|string|max:1000',
        ]);

        if (! class_exists(PractitionerTimeOff::class)) {
            return $this->error('Time off module not available', 503);
        }

        $type = TimeOffType::find($validated['time_off_type_id']);
        if (! $type || ! $type->is_active) {
            return $this->error(__('mobile_api::mobile.time_off.invalid_type'), 400);
        }

        $startDate = \Carbon\Carbon::parse($validated['start_date']);
        $endDate = \Carbon\Carbon::parse($validated['end_date']);
        $isFullDay = $validated['is_full_day'] ?? true;

        // TC-18: Excuses are past-tense justifications — reject if the
        // requested date is in the future. Uses the tenant timezone so a
        // late-evening submit doesn't accidentally count as "tomorrow".
        if ($type->isExcuse() && $startDate->isAfter(now()->startOfDay())) {
            return $this->businessRuleError(
                'Excuses cannot be submitted for future dates.',
                'FUTURE_EXCUSE_NOT_ALLOWED'
            );
        }

        // TC-19: An excuse's [start_time, end_time] must overlap the
        // employee's working schedule on that date — the whole point of an
        // excuse is justifying absence during work hours.
        if ($type->isExcuse()
            && ! empty($validated['start_time'])
            && ! empty($validated['end_time'])
        ) {
            if (! $this->overlapsWorkingHours(
                $staffProfile,
                $startDate,
                $validated['start_time'],
                $validated['end_time']
            )) {
                return $this->businessRuleError(
                    'Excuses must fall within your working hours.',
                    'OUTSIDE_WORKING_HOURS'
                );
            }
        }

        // Check for overlapping requests
        $overlapping = PractitionerTimeOff::forStaffProfile($staffProfile->id)
            ->whereIn('status', [PractitionerTimeOff::STATUS_PENDING, PractitionerTimeOff::STATUS_APPROVED])
            ->forDateRange($validated['start_date'], $validated['end_date'])
            ->exists();

        if ($overlapping) {
            return $this->businessRuleError(
                __('mobile_api::mobile.time_off.dates_overlap'),
                'DATES_OVERLAP'
            );
        }

        // Calculate days/hours requested
        $daysRequested = 0;
        $hoursRequested = null;

        if ($type->isHourBased()) {
            // Without both times the requested amount would compute to 0 and
            // slip past the balance check below.
            if (empty($validated['start_time']) || empty($validated['end_time'])) {
                return $this->businessRuleError(
                    __('mobile_api::mobile.time_off.times_required'),
                    'TIMES_REQUIRED'
                );
            }

            $hoursRequested = PractitionerTimeOff::calculateHoursFromTimeRange(
                $validated['start_time'],
                $validated['end_time']
            );
            $daysRequested = $type->convertToDays($hoursRequested);
        } else {
            // Calculate days
            $daysRequested = $startDate->diffInDays($endDate) + 1;
            if (! $isFullDay && $type->allow_half_day) {
                $daysRequested = 0.5;
            }
        }

        $amountToCheck = $type->isHourBased() ? $hoursRequested : $daysRequested;

        // Zero or negative amounts (e.g. end_time before start_time) must not
        // reach the balance check — they'd trivially pass it.
        if ($amountToCheck <= 0) {
            return $this->businessRuleError(
                __('mobile_api::mobile.time_off.invalid_duration'),
                'INVALID_DURATION'
            );
        }

        // TC-17: Balance validation — reject when requested exceeds remaining.
        // Pending requests are counted too: `used_days` only moves on
        // approval, so without this several pending requests could
        // collectively exceed the allocation.
        //
        // Allocations are the source of truth (synced from Odoo when the
        // integration is on): a request whose start date isn't covered by an
        // existing allocation is rejected outright — the backend never mints
        // a default-allocation row on behalf of a request.
        // Message and error_code must be machine-parsable by the mobile app.
        $allocation = TimeOffAllocation::getForDate($staffProfile->id, $type->id, $startDate);

        if (! $allocation) {
            return $this->businessRuleError(
                __('mobile_api::mobile.time_off.no_allocation'),
                'NO_ALLOCATION'
            );
        }

        if (! $allocation->hasAvailableForRequest($amountToCheck)) {
            $available = max(0.0, $allocation->available_for_request);
            $unit = $type->isHourBased() ? 'hours' : 'days';
            $formattedRequested = $type->formatValue($amountToCheck);
            $formattedRemaining = $type->formatValue($available);

            return $this->businessRuleError(
                "Requested {$unit} ({$formattedRequested}) exceed your remaining balance ({$formattedRemaining}).",
                'INSUFFICIENT_BALANCE'
            );
        }

        // Check max per request
        $maxPerRequest = $type->getEffectiveMaxPerRequest();
        if ($maxPerRequest !== null && $amountToCheck > $maxPerRequest) {
            return $this->businessRuleError(
                __('mobile_api::mobile.time_off.exceeds_max_per_request', [
                    'max' => $type->formatValue($maxPerRequest),
                ]),
                'EXCEEDS_MAX_PER_REQUEST'
            );
        }

        // Create request
        $timeOff = PractitionerTimeOff::create([
            'tenant_id' => current_tenant_id(),
            'staff_profile_id' => $staffProfile->id,
            'branch_id' => $staffProfile->branch_id,
            'time_off_type_id' => $type->id,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'start_time' => $validated['start_time'] ?? null,
            'end_time' => $validated['end_time'] ?? null,
            'is_full_day' => $isFullDay,
            'days_requested' => $daysRequested,
            'hours_requested' => $hoursRequested,
            'reason' => $validated['reason'] ?? null,
            'status' => PractitionerTimeOff::STATUS_PENDING,
        ]);

        return $this->success([
            'id' => $timeOff->id,
            'status' => $timeOff->status,
        ], __('mobile_api::mobile.time_off.request_submitted'));
    }

    /**
     * Cancel a time off request.
     * POST /api/v2/time-off/requests/{id}/cancel
     */
    public function cancel(int $id): JsonResponse
    {
        $staffProfile = $this->staffProfile();

        if (! class_exists(PractitionerTimeOff::class) || ! $staffProfile) {
            return $this->error('Time off module not available', 503);
        }

        $timeOff = PractitionerTimeOff::forStaffProfile($staffProfile->id)->find($id);

        if (! $timeOff) {
            return $this->notFound();
        }

        if (! $timeOff->isPending()) {
            return $this->error(__('mobile_api::mobile.time_off.cannot_cancel'), 400);
        }

        $timeOff->cancel();

        return $this->success(null, __('mobile_api::mobile.time_off.request_cancelled'));
    }

    /**
     * Get team time off calendar.
     * GET /api/v2/time-off/calendar
     */
    public function calendar(Request $request): JsonResponse
    {
        $branch = $this->branch();

        if (! class_exists(PractitionerTimeOff::class)) {
            return $this->success([]);
        }

        $month = $request->integer('month', now()->month);
        $year = $request->integer('year', now()->year);

        $startDate = \Carbon\Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $query = PractitionerTimeOff::with(['staffProfile.user', 'timeOffType'])
            ->approved()
            ->forDateRange($startDate, $endDate);

        // Filter by branch if available
        if ($branch) {
            $query->forBranch($branch->id);
        }

        $timeOffs = $query->get();

        return $this->success([
            'month' => $month,
            'year' => $year,
            'entries' => $timeOffs->map(fn ($t) => [
                'id' => $t->id,
                'staff_name' => $t->staffProfile?->user?->full_name ?? 'Unknown',
                'type' => $t->timeOffType?->translated_name ?? $t->type_label,
                'color' => $t->timeOffType?->color ?? 'gray',
                'start_date' => $t->start_date->toDateString(),
                'end_date' => $t->end_date->toDateString(),
                'is_full_day' => $t->is_full_day,
            ])->all(),
        ]);
    }

    /**
     * Format time off request for response.
     */
    protected function formatTimeOffRequest(PractitionerTimeOff $timeOff, bool $detailed = false): array
    {
        $data = [
            'id' => $timeOff->id,
            'type' => $timeOff->timeOffType?->translated_name ?? $timeOff->type_label,
            'type_id' => $timeOff->time_off_type_id,
            'color' => $timeOff->timeOffType?->color ?? 'gray',
            'start_date' => $timeOff->start_date->toDateString(),
            'end_date' => $timeOff->end_date->toDateString(),
            'is_full_day' => $timeOff->is_full_day,
            'duration' => $timeOff->display_duration,
            'status' => $timeOff->status,
            'status_label' => $timeOff->status_label,
            'status_color' => $timeOff->status_color,
            'created_at' => $timeOff->created_at->toDateTimeString(),
        ];

        if ($detailed) {
            $data['start_time'] = $timeOff->start_time;
            $data['end_time'] = $timeOff->end_time;
            $data['reason'] = $timeOff->reason;
            $data['days_requested'] = $timeOff->days_requested;
            $data['hours_requested'] = $timeOff->hours_requested;
            $data['approved_by'] = $timeOff->approvedBy?->full_name;
            $data['approved_at'] = $timeOff->approved_at?->toDateTimeString();
            $data['notes'] = $timeOff->notes;
        }

        return $data;
    }

    /**
     * Check whether the requested [start_time, end_time] window on the given
     * date overlaps at least one working slot on the employee's schedule.
     *
     * If the employee has no schedule at all we accept — the tenant simply
     * hasn't configured working hours yet and we don't want to block excuse
     * submission on missing setup.
     */
    protected function overlapsWorkingHours(
        $staffProfile,
        \Carbon\Carbon $date,
        string $startTime,
        string $endTime
    ): bool {
        $schedule = app(AttendanceService::class)->getStaffSchedule($staffProfile);
        if (! $schedule) {
            return true;
        }

        $dayOfWeek = (int) $date->dayOfWeek;

        // Sample the start and end minute; if either is inside a working
        // slot (and outside a break window), the excuse overlaps working
        // hours. WorkSchedule::isAvailableAt() handles both fixed and
        // break-window logic internally.
        return $schedule->isAvailableAt($dayOfWeek, $startTime)
            || $schedule->isAvailableAt($dayOfWeek, $endTime);
    }
}
