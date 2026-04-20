<?php

namespace Modules\MobileApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        if (! class_exists(TimeOffType::class)) {
            return $this->success([]);
        }

        $types = TimeOffType::active()
            ->ordered()
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->translated_name,
                'code' => $t->code,
                'color' => $t->color,
                'is_paid' => $t->is_paid,
                'requires_approval' => $t->requires_approval,
                'request_unit' => $t->request_unit,
                'allow_half_day' => $t->allow_half_day,
                'hours_per_day' => $t->hours_per_day,
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
        $user = $this->user();

        if (! class_exists(PractitionerTimeOff::class)) {
            return $this->success([]);
        }

        $query = PractitionerTimeOff::with('timeOffType')
            ->where('user_id', $user->id)
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
        $user = $this->user();

        if (! class_exists(PractitionerTimeOff::class)) {
            return $this->notFound();
        }

        $timeOff = PractitionerTimeOff::with(['timeOffType', 'approvedBy'])
            ->where('user_id', $user->id)
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
        $user = $this->user();

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

        // Check for overlapping requests
        $overlapping = PractitionerTimeOff::where('user_id', $user->id)
            ->whereIn('status', [PractitionerTimeOff::STATUS_PENDING, PractitionerTimeOff::STATUS_APPROVED])
            ->forDateRange($validated['start_date'], $validated['end_date'])
            ->exists();

        if ($overlapping) {
            return $this->error(__('mobile_api::mobile.time_off.dates_overlap'), 400);
        }

        // Calculate days/hours requested
        $startDate = \Carbon\Carbon::parse($validated['start_date']);
        $endDate = \Carbon\Carbon::parse($validated['end_date']);
        $isFullDay = $validated['is_full_day'] ?? true;

        $daysRequested = 0;
        $hoursRequested = null;

        if ($type->isHourBased()) {
            // For hours-based, calculate from time range
            $hoursRequested = PractitionerTimeOff::calculateHoursFromTimeRange(
                $validated['start_time'] ?? null,
                $validated['end_time'] ?? null
            );
            $daysRequested = $type->convertToDays($hoursRequested);
        } else {
            // Calculate days
            $daysRequested = $startDate->diffInDays($endDate) + 1;
            if (! $isFullDay && $type->allow_half_day) {
                $daysRequested = 0.5;
            }
        }

        // Check balance (allocation is keyed by staff_profile_id).
        $staffProfile = $this->staffProfile();
        if (! $staffProfile) {
            return $this->error(__('mobile_api::mobile.time_off.no_staff_profile'), 400);
        }
        $allocation = TimeOffAllocation::getOrCreateForDate($staffProfile->id, $type->id, $startDate);
        $amountToCheck = $type->isHourBased() ? $hoursRequested : $daysRequested;

        if (! $allocation->hasAvailable($amountToCheck)) {
            return $this->error(__('mobile_api::mobile.time_off.insufficient_balance'), 400);
        }

        // Check max per request
        $maxPerRequest = $type->getEffectiveMaxPerRequest();
        if ($maxPerRequest !== null && $amountToCheck > $maxPerRequest) {
            return $this->error(__('mobile_api::mobile.time_off.exceeds_max_per_request', [
                'max' => $type->formatValue($maxPerRequest),
            ]), 400);
        }

        // Create request
        $timeOff = PractitionerTimeOff::create([
            'tenant_id' => current_tenant_id(),
            'user_id' => $user->id,
            'branch_id' => $this->staffProfile()?->branch_id,
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
        $user = $this->user();

        if (! class_exists(PractitionerTimeOff::class)) {
            return $this->error('Time off module not available', 503);
        }

        $timeOff = PractitionerTimeOff::where('user_id', $user->id)->find($id);

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

        $query = PractitionerTimeOff::with(['practitioner', 'timeOffType'])
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
                'staff_name' => $t->practitioner?->full_name ?? 'Unknown',
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
}
