<?php

namespace Modules\MobileApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TimeOffController extends BaseApiController
{
    /**
     * Get available leave types.
     * GET /api/v2/time-off/types
     */
    public function types(): JsonResponse
    {
        if (!class_exists(\Modules\Attendance\Models\LeaveType::class)) {
            // Return default types if model doesn't exist
            return $this->success([
                ['id' => 1, 'name' => 'Vacation', 'code' => 'vacation'],
                ['id' => 2, 'name' => 'Sick Leave', 'code' => 'sick'],
                ['id' => 3, 'name' => 'Personal', 'code' => 'personal'],
            ]);
        }

        $types = \Modules\Attendance\Models\LeaveType::where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'code' => $t->code,
                'paid' => $t->is_paid ?? true,
                'requires_approval' => $t->requires_approval ?? true,
            ]);

        return $this->success($types);
    }

    /**
     * Get leave balances.
     * GET /api/v2/time-off/balance
     */
    public function balance(): JsonResponse
    {
        $staffProfile = $this->staffProfile();

        if (!class_exists(\Modules\Attendance\Models\LeaveBalance::class)) {
            return $this->success([]);
        }

        $balances = \Modules\Attendance\Models\LeaveBalance::with('leaveType')
            ->where('staff_profile_id', $staffProfile->id)
            ->whereYear('year', now()->year)
            ->get()
            ->map(fn($b) => [
                'type' => $b->leaveType?->name ?? 'Unknown',
                'code' => $b->leaveType?->code,
                'allocated' => $b->allocated_days,
                'used' => $b->used_days,
                'remaining' => $b->allocated_days - $b->used_days,
                'pending' => $b->pending_days ?? 0,
            ]);

        return $this->success($balances);
    }

    /**
     * Get leave requests.
     * GET /api/v2/time-off/requests
     */
    public function requests(Request $request): JsonResponse
    {
        $staffProfile = $this->staffProfile();

        if (!class_exists(\Modules\Attendance\Models\LeaveRequest::class)) {
            return $this->success([]);
        }

        $query = \Modules\Attendance\Models\LeaveRequest::with('leaveType')
            ->where('staff_profile_id', $staffProfile->id)
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $requests = $query->paginate($this->getPerPage());

        return $this->paginated($requests);
    }

    /**
     * Get leave request details.
     * GET /api/v2/time-off/requests/{id}
     */
    public function show(int $id): JsonResponse
    {
        $staffProfile = $this->staffProfile();

        if (!class_exists(\Modules\Attendance\Models\LeaveRequest::class)) {
            return $this->notFound();
        }

        $request = \Modules\Attendance\Models\LeaveRequest::with(['leaveType', 'approver'])
            ->where('staff_profile_id', $staffProfile->id)
            ->find($id);

        if (!$request) {
            return $this->notFound();
        }

        return $this->success([
            'id' => $request->id,
            'type' => $request->leaveType?->name,
            'start_date' => $request->start_date->toDateString(),
            'end_date' => $request->end_date->toDateString(),
            'days' => $request->days_count,
            'reason' => $request->reason,
            'status' => $request->status,
            'approved_by' => $request->approver?->full_name,
            'approved_at' => $request->approved_at?->toDateTimeString(),
            'rejection_reason' => $request->rejection_reason,
            'created_at' => $request->created_at->toDateTimeString(),
        ]);
    }

    /**
     * Submit a leave request.
     * POST /api/v2/time-off/requests
     */
    public function store(Request $request): JsonResponse
    {
        $staffProfile = $this->staffProfile();

        $validated = $request->validate([
            'leave_type_id' => 'required|integer',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'sometimes|string|max:1000',
        ]);

        if (!class_exists(\Modules\Attendance\Models\LeaveRequest::class)) {
            return $this->error('Leave module not available', 503);
        }

        // Check for overlapping requests
        $overlapping = \Modules\Attendance\Models\LeaveRequest::where('staff_profile_id', $staffProfile->id)
            ->whereIn('status', ['pending', 'approved'])
            ->where(function ($q) use ($validated) {
                $q->whereBetween('start_date', [$validated['start_date'], $validated['end_date']])
                    ->orWhereBetween('end_date', [$validated['start_date'], $validated['end_date']])
                    ->orWhere(function ($q2) use ($validated) {
                        $q2->where('start_date', '<=', $validated['start_date'])
                            ->where('end_date', '>=', $validated['end_date']);
                    });
            })
            ->exists();

        if ($overlapping) {
            return $this->error(__('mobile_api::mobile.time_off.dates_overlap'), 400);
        }

        // Calculate days
        $startDate = \Carbon\Carbon::parse($validated['start_date']);
        $endDate = \Carbon\Carbon::parse($validated['end_date']);
        $daysCount = $startDate->diffInDays($endDate) + 1;

        // Check balance
        $balance = \Modules\Attendance\Models\LeaveBalance::where('staff_profile_id', $staffProfile->id)
            ->where('leave_type_id', $validated['leave_type_id'])
            ->whereYear('year', now()->year)
            ->first();

        if ($balance) {
            $remaining = $balance->allocated_days - $balance->used_days - ($balance->pending_days ?? 0);
            if ($daysCount > $remaining) {
                return $this->error(__('mobile_api::mobile.time_off.insufficient_balance'), 400);
            }
        }

        // Create request
        $leaveRequest = \Modules\Attendance\Models\LeaveRequest::create([
            'staff_profile_id' => $staffProfile->id,
            'leave_type_id' => $validated['leave_type_id'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'days_count' => $daysCount,
            'reason' => $validated['reason'] ?? null,
            'status' => 'pending',
        ]);

        // Update pending days in balance
        if ($balance) {
            $balance->increment('pending_days', $daysCount);
        }

        return $this->success([
            'id' => $leaveRequest->id,
            'status' => 'pending',
        ], __('mobile_api::mobile.time_off.request_submitted'));
    }

    /**
     * Cancel a leave request.
     * POST /api/v2/time-off/requests/{id}/cancel
     */
    public function cancel(int $id): JsonResponse
    {
        $staffProfile = $this->staffProfile();

        if (!class_exists(\Modules\Attendance\Models\LeaveRequest::class)) {
            return $this->error('Leave module not available', 503);
        }

        $leaveRequest = \Modules\Attendance\Models\LeaveRequest::where('staff_profile_id', $staffProfile->id)
            ->find($id);

        if (!$leaveRequest) {
            return $this->notFound();
        }

        if ($leaveRequest->status !== 'pending') {
            return $this->error(__('mobile_api::mobile.time_off.cannot_cancel'), 400);
        }

        // Restore pending days
        $balance = \Modules\Attendance\Models\LeaveBalance::where('staff_profile_id', $staffProfile->id)
            ->where('leave_type_id', $leaveRequest->leave_type_id)
            ->whereYear('year', now()->year)
            ->first();

        if ($balance && $balance->pending_days > 0) {
            $balance->decrement('pending_days', min($balance->pending_days, $leaveRequest->days_count));
        }

        $leaveRequest->update(['status' => 'cancelled']);

        return $this->success(null, __('mobile_api::mobile.time_off.request_cancelled'));
    }

    /**
     * Get team leave calendar.
     * GET /api/v2/time-off/calendar
     */
    public function calendar(Request $request): JsonResponse
    {
        $staffProfile = $this->staffProfile();
        $branch = $this->branch();

        if (!class_exists(\Modules\Attendance\Models\LeaveRequest::class)) {
            return $this->success([]);
        }

        $month = $request->month ?? now()->month;
        $year = $request->year ?? now()->year;

        $startDate = \Carbon\Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $query = \Modules\Attendance\Models\LeaveRequest::with(['staffProfile.user', 'leaveType'])
            ->where('status', 'approved')
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate])
                    ->orWhere(function ($q2) use ($startDate, $endDate) {
                        $q2->where('start_date', '<=', $startDate)
                            ->where('end_date', '>=', $endDate);
                    });
            });

        // Filter by branch if available
        if ($branch) {
            $query->whereHas('staffProfile', function ($q) use ($branch) {
                $q->where('branch_id', $branch->id);
            });
        }

        $leaves = $query->get();

        return $this->success([
            'month' => $month,
            'year' => $year,
            'entries' => $leaves->map(fn($l) => [
                'staff_name' => $l->staffProfile?->user?->full_name ?? 'Unknown',
                'type' => $l->leaveType?->name,
                'start_date' => $l->start_date->toDateString(),
                'end_date' => $l->end_date->toDateString(),
            ])->all(),
        ]);
    }
}
