<?php

namespace Modules\Api\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Attendance\Models\Attendance;
use Modules\Attendance\Models\AttendanceBreak;
use Modules\Attendance\Models\AttendanceViolation;
use Modules\Attendance\Models\WorkingSchedule;
use Modules\Attendance\Services\AttendanceService;
use Modules\Staff\Models\StaffProfile;

class AttendanceController extends BaseApiController
{
    public function __construct(
        protected AttendanceService $attendanceService
    ) {}

    /**
     * Get current attendance status for authenticated staff
     */
    public function status(Request $request): JsonResponse
    {
        $staff = $this->getStaffProfile($request);

        if (!$staff) {
            return $this->error(__('api::api.staff_profile_not_found'), 404);
        }

        $status = $this->attendanceService->getStatus($staff);

        return $this->success([
            'is_checked_in' => $status['is_checked_in'],
            'can_check_in' => $status['can_check_in'],
            'can_check_out' => $status['can_check_out'],
            'is_on_break' => $status['is_on_break'],
            'today_attendance' => $status['today_attendance'] ? [
                'id' => $status['today_attendance']->id,
                'check_in_time' => $status['today_attendance']->check_in_time,
                'check_out_time' => $status['today_attendance']->check_out_time,
                'working_hours' => $status['today_attendance']->working_hours,
                'status' => $status['today_attendance']->status,
                'attendance_type' => $status['today_attendance']->attendance_type,
            ] : null,
            'active_break' => $status['active_break'] ? [
                'id' => $status['active_break']->id,
                'break_type' => $status['active_break']->break_type,
                'start_time' => $status['active_break']->start_time,
            ] : null,
            'schedule' => $this->formatSchedule($status['schedule']),
        ]);
    }

    /**
     * Check in with GPS coordinates
     */
    public function checkIn(Request $request): JsonResponse
    {
        $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'altitude' => 'nullable|numeric',
            'accuracy' => 'nullable|numeric',
            'address' => 'nullable|string|max:500',
            'device_info' => 'nullable|array',
        ]);

        $staff = $this->getStaffProfile($request);

        if (!$staff) {
            return $this->error(__('api::api.staff_profile_not_found'), 404);
        }

        // Check if already checked in
        if ($this->attendanceService->isCheckedIn($staff)) {
            return $this->error(__('attendance::attendance.already_checked_in'), 422);
        }

        try {
            DB::beginTransaction();

            $attendance = $this->attendanceService->checkIn($staff, [
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'altitude' => $request->altitude,
                'accuracy' => $request->accuracy,
                'address' => $request->address,
                'device_info' => $request->device_info,
                'source' => Attendance::SOURCE_MOBILE,
            ]);

            DB::commit();

            return $this->success([
                'attendance_id' => $attendance->id,
                'check_in_time' => $attendance->check_in_time,
                'status' => $attendance->status,
                'attendance_type' => $attendance->attendance_type,
                'is_late' => $attendance->attendance_type === Attendance::TYPE_LATE,
            ], __('attendance::attendance.checked_in_successfully'), 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Check out with GPS coordinates
     */
    public function checkOut(Request $request): JsonResponse
    {
        $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'altitude' => 'nullable|numeric',
            'accuracy' => 'nullable|numeric',
            'address' => 'nullable|string|max:500',
            'device_info' => 'nullable|array',
        ]);

        $staff = $this->getStaffProfile($request);

        if (!$staff) {
            return $this->error(__('api::api.staff_profile_not_found'), 404);
        }

        $attendance = $this->attendanceService->getTodayAttendance($staff);

        if (!$attendance || $attendance->check_out_time) {
            return $this->error(__('attendance::attendance.not_checked_in'), 422);
        }

        try {
            DB::beginTransaction();

            $attendance = $this->attendanceService->checkOut($attendance, [
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'altitude' => $request->altitude,
                'accuracy' => $request->accuracy,
                'address' => $request->address,
                'device_info' => $request->device_info,
                'source' => Attendance::SOURCE_MOBILE,
            ]);

            DB::commit();

            return $this->success([
                'attendance_id' => $attendance->id,
                'check_in_time' => $attendance->check_in_time,
                'check_out_time' => $attendance->check_out_time,
                'working_hours' => $attendance->working_hours,
                'overtime_hours' => $attendance->overtime_hours,
                'status' => $attendance->status,
            ], __('attendance::attendance.checked_out_successfully'));

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Start a break
     */
    public function startBreak(Request $request): JsonResponse
    {
        $request->validate([
            'break_type' => 'nullable|string|max:50',
        ]);

        $staff = $this->getStaffProfile($request);

        if (!$staff) {
            return $this->error(__('api::api.staff_profile_not_found'), 404);
        }

        $attendance = $this->attendanceService->getTodayAttendance($staff);

        if (!$attendance || !$attendance->isCheckedIn()) {
            return $this->error(__('attendance::attendance.not_checked_in'), 422);
        }

        // Check for active break
        $activeBreak = $attendance->breaks()->whereNull('end_time')->first();
        if ($activeBreak) {
            return $this->error(__('api::api.already_on_break'), 422);
        }

        try {
            $break = $this->attendanceService->startBreak(
                $attendance,
                $request->input('break_type', 'regular')
            );

            return $this->success([
                'break_id' => $break->id,
                'break_type' => $break->break_type,
                'start_time' => $break->start_time,
            ], __('api::api.break_started'));

        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * End a break
     */
    public function endBreak(Request $request): JsonResponse
    {
        $staff = $this->getStaffProfile($request);

        if (!$staff) {
            return $this->error(__('api::api.staff_profile_not_found'), 404);
        }

        $attendance = $this->attendanceService->getTodayAttendance($staff);

        if (!$attendance) {
            return $this->error(__('attendance::attendance.not_checked_in'), 422);
        }

        $activeBreak = $attendance->breaks()->whereNull('end_time')->first();

        if (!$activeBreak) {
            return $this->error(__('api::api.not_on_break'), 422);
        }

        try {
            $break = $this->attendanceService->endBreak($activeBreak);

            return $this->success([
                'break_id' => $break->id,
                'break_type' => $break->break_type,
                'start_time' => $break->start_time,
                'end_time' => $break->end_time,
                'duration_minutes' => $break->duration_minutes,
            ], __('api::api.break_ended'));

        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Get attendance history
     */
    public function history(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $staff = $this->getStaffProfile($request);

        if (!$staff) {
            return $this->error(__('api::api.staff_profile_not_found'), 404);
        }

        $query = Attendance::where('staff_profile_id', $staff->id)
            ->orderBy('attendance_date', 'desc');

        if ($request->start_date) {
            $query->where('attendance_date', '>=', $request->start_date);
        }

        if ($request->end_date) {
            $query->where('attendance_date', '<=', $request->end_date);
        }

        $paginator = $query->paginate($this->getPerPage());

        $data = collect($paginator->items())->map(fn ($a) => [
            'id' => $a->id,
            'date' => $a->attendance_date->format('Y-m-d'),
            'check_in_time' => $a->check_in_time,
            'check_out_time' => $a->check_out_time,
            'working_hours' => $a->working_hours,
            'overtime_hours' => $a->overtime_hours,
            'status' => $a->status,
            'attendance_type' => $a->attendance_type,
            'violations_count' => $a->violations()->count(),
        ]);

        return $this->paginated($paginator)->setData([
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
     * Get staff working schedule
     */
    public function schedule(Request $request): JsonResponse
    {
        $staff = $this->getStaffProfile($request);

        if (!$staff) {
            return $this->error(__('api::api.staff_profile_not_found'), 404);
        }

        $schedule = $staff->workingSchedule ?? WorkingSchedule::where('is_default', true)->first();

        if (!$schedule) {
            return $this->error(__('api::api.no_schedule_assigned'), 404);
        }

        return $this->success($this->formatSchedule($schedule));
    }

    /**
     * Get violations for authenticated staff
     */
    public function violations(Request $request): JsonResponse
    {
        $request->validate([
            'status' => 'nullable|string|in:pending,approved,waived,disputed,applied',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $staff = $this->getStaffProfile($request);

        if (!$staff) {
            return $this->error(__('api::api.staff_profile_not_found'), 404);
        }

        $query = AttendanceViolation::where('staff_profile_id', $staff->id)
            ->orderBy('violation_date', 'desc');

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->start_date) {
            $query->where('violation_date', '>=', $request->start_date);
        }

        if ($request->end_date) {
            $query->where('violation_date', '<=', $request->end_date);
        }

        $paginator = $query->paginate($this->getPerPage());

        $data = collect($paginator->items())->map(fn ($v) => [
            'id' => $v->id,
            'violation_date' => $v->violation_date->format('Y-m-d'),
            'violation_type' => $v->violation_type,
            'violation_minutes' => $v->violation_minutes,
            'penalty_amount' => $v->penalty_amount_minor / 100,
            'status' => $v->status,
            'reason' => $v->reason,
            'can_dispute' => $v->status === AttendanceViolation::STATUS_PENDING,
        ]);

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
     * Dispute a violation
     */
    public function disputeViolation(Request $request, string $violationId): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|min:10|max:1000',
        ]);

        $staff = $this->getStaffProfile($request);

        if (!$staff) {
            return $this->error(__('api::api.staff_profile_not_found'), 404);
        }

        $violation = AttendanceViolation::where('id', $violationId)
            ->where('staff_profile_id', $staff->id)
            ->first();

        if (!$violation) {
            return $this->error(__('api::api.violation_not_found'), 404);
        }

        if ($violation->status !== AttendanceViolation::STATUS_PENDING) {
            return $this->error(__('api::api.cannot_dispute_violation'), 422);
        }

        try {
            $violation->dispute($request->reason);

            return $this->success([
                'id' => $violation->id,
                'status' => $violation->status,
                'dispute_reason' => $violation->dispute_reason,
                'disputed_at' => $violation->disputed_at->toIso8601String(),
            ], __('attendance::attendance.violation_disputed'));

        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Get monthly summary for authenticated staff
     */
    public function monthlySummary(Request $request): JsonResponse
    {
        $request->validate([
            'month' => 'nullable|date_format:Y-m',
        ]);

        $staff = $this->getStaffProfile($request);

        if (!$staff) {
            return $this->error(__('api::api.staff_profile_not_found'), 404);
        }

        $month = $request->input('month', now()->format('Y-m'));
        $startDate = Carbon::parse($month . '-01')->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $attendances = Attendance::where('staff_profile_id', $staff->id)
            ->whereBetween('attendance_date', [$startDate, $endDate])
            ->get();

        $violations = AttendanceViolation::where('staff_profile_id', $staff->id)
            ->whereBetween('violation_date', [$startDate, $endDate])
            ->get();

        return $this->success([
            'month' => $month,
            'total_days' => $attendances->count(),
            'present_days' => $attendances->where('status', Attendance::STATUS_PRESENT)->count(),
            'absent_days' => $attendances->where('status', Attendance::STATUS_ABSENT)->count(),
            'half_days' => $attendances->where('status', Attendance::STATUS_HALF_DAY)->count(),
            'leave_days' => $attendances->where('status', Attendance::STATUS_LEAVE)->count(),
            'late_days' => $attendances->where('attendance_type', Attendance::TYPE_LATE)->count(),
            'total_working_hours' => round($attendances->sum('working_hours'), 2),
            'total_overtime_hours' => round($attendances->sum('overtime_hours'), 2),
            'violations_count' => $violations->count(),
            'pending_violations' => $violations->where('status', AttendanceViolation::STATUS_PENDING)->count(),
            'total_penalty' => round($violations->where('status', '!=', AttendanceViolation::STATUS_WAIVED)->sum('penalty_amount_minor') / 100, 2),
        ]);
    }

    /**
     * Get staff profile from authenticated user
     */
    protected function getStaffProfile(Request $request): ?StaffProfile
    {
        $user = $request->user();

        if (!$user) {
            return null;
        }

        return StaffProfile::where('user_id', $user->id)->first();
    }

    /**
     * Format working schedule for API response
     */
    protected function formatSchedule(?WorkingSchedule $schedule): ?array
    {
        if (!$schedule) {
            return null;
        }

        return [
            'id' => $schedule->id,
            'name' => $schedule->name,
            'type' => $schedule->type,
            'start_time' => $schedule->start_time,
            'end_time' => $schedule->end_time,
            'hours_per_day' => $schedule->hours_per_day,
            'working_days' => $schedule->working_days,
            'grace_period_late_minutes' => $schedule->grace_period_late_minutes,
            'grace_period_early_minutes' => $schedule->grace_period_early_minutes,
            'has_break' => $schedule->has_break,
            'break_duration_minutes' => $schedule->break_duration_minutes,
            'break_start' => $schedule->break_start,
            'break_end' => $schedule->break_end,
            'is_today_working_day' => $schedule->isWorkingDate(now()),
        ];
    }
}
