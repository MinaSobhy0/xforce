<?php

namespace Modules\Attendance\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Attendance\Models\Attendance;
use Modules\Attendance\Models\AttendanceBreak;
use Modules\Attendance\Models\AttendanceLog;
use Modules\Booking\Models\WorkSchedule;
use Modules\Staff\Models\StaffProfile;

class AttendanceService
{
    protected AttendanceRuleService $ruleService;

    public function __construct(AttendanceRuleService $ruleService)
    {
        $this->ruleService = $ruleService;
    }

    /**
     * Check in a staff member.
     */
    public function checkIn(
        StaffProfile $staff,
        array $locationData = [],
        string $source = AttendanceLog::SOURCE_MANUAL,
        ?string $attendanceType = null
    ): Attendance {
        // Check if already checked in today
        $existingAttendance = $this->getTodayAttendance($staff);

        if ($existingAttendance && $existingAttendance->isCheckedIn()) {
            throw new \Exception(__('attendance::attendance.already_checked_in'));
        }

        return DB::transaction(function () use ($staff, $locationData, $source, $attendanceType, $existingAttendance) {
            $now = now();
            $checkInTime = $now->format('H:i:s');

            // Get working schedule
            $schedule = $this->getStaffSchedule($staff);

            // Create or update attendance record
            $attendance = $existingAttendance ?? new Attendance();
            $attendance->fill([
                'tenant_id' => $staff->tenant_id,
                'staff_profile_id' => $staff->id,
                'branch_id' => $staff->branch_id ?? null,
                'working_schedule_id' => $schedule?->id,
                'attendance_date' => $now->toDateString(),
                'check_in_time' => $checkInTime,
                'attendance_type' => $attendanceType ?? $this->determineAttendanceType($source),
                'status' => Attendance::STATUS_PRESENT,
                'created_by' => auth()->id(),
            ]);
            $attendance->save();

            // Create attendance log
            $this->createLog($attendance, AttendanceLog::TYPE_CHECK_IN, $locationData, $source);

            // Evaluate late check-in violation
            if ($schedule) {
                $daySchedule = $schedule->getDaySchedule($now->dayOfWeek);
                if ($daySchedule && !empty($daySchedule['start_time']) && $checkInTime > $daySchedule['start_time']) {
                    $this->ruleService->evaluateLateCheckIn(
                        $attendance,
                        $checkInTime,
                        $daySchedule['start_time']
                    );
                }
            }

            return $attendance->fresh();
        });
    }

    /**
     * Check out a staff member.
     */
    public function checkOut(
        StaffProfile $staff,
        array $locationData = [],
        string $source = AttendanceLog::SOURCE_MANUAL
    ): Attendance {
        $attendance = $this->getTodayAttendance($staff);

        if (!$attendance || !$attendance->isCheckedIn()) {
            throw new \Exception(__('attendance::attendance.not_checked_in'));
        }

        if ($attendance->isCheckedOut()) {
            throw new \Exception(__('attendance::attendance.already_checked_out'));
        }

        return DB::transaction(function () use ($attendance, $locationData, $source) {
            $now = now();
            $checkOutTime = $now->format('H:i:s');

            // End any ongoing breaks
            $this->endAllOngoingBreaks($attendance);

            // Update attendance record
            $attendance->check_out_time = $checkOutTime;
            $attendance->working_hours = $attendance->calculateWorkingHours();
            $attendance->updated_by = auth()->id();
            $attendance->save();

            // Create attendance log
            $this->createLog($attendance, AttendanceLog::TYPE_CHECK_OUT, $locationData, $source);

            // Get working schedule and evaluate early checkout
            $schedule = $attendance->workingSchedule;
            if ($schedule) {
                $daySchedule = $schedule->getDaySchedule(now()->dayOfWeek);
                if ($daySchedule && !empty($daySchedule['end_time']) && $checkOutTime < $daySchedule['end_time']) {
                    $this->ruleService->evaluateEarlyCheckOut(
                        $attendance,
                        $checkOutTime,
                        $daySchedule['end_time']
                    );
                }
            }

            return $attendance->fresh();
        });
    }

    /**
     * Start a break.
     */
    public function startBreak(Attendance $attendance, ?string $reason = null): AttendanceBreak
    {
        if (!$attendance->isCheckedIn() || $attendance->isCheckedOut()) {
            throw new \Exception('Cannot start break - not in active work session');
        }

        // Check if there's already an ongoing break
        $ongoingBreak = $attendance->breaks()->ongoing()->first();
        if ($ongoingBreak) {
            throw new \Exception('There is already an ongoing break');
        }

        return DB::transaction(function () use ($attendance, $reason) {
            $break = AttendanceBreak::create([
                'tenant_id' => $attendance->tenant_id,
                'attendance_id' => $attendance->id,
                'start_time' => now(),
                'reason' => $reason,
            ]);

            // Create log
            $this->createLog($attendance, AttendanceLog::TYPE_BREAK_START);

            return $break;
        });
    }

    /**
     * End a break.
     */
    public function endBreak(AttendanceBreak $break): AttendanceBreak
    {
        if ($break->isCompleted()) {
            throw new \Exception('Break is already ended');
        }

        return DB::transaction(function () use ($break) {
            $break->endBreak();

            // Create log
            $this->createLog($break->attendance, AttendanceLog::TYPE_BREAK_END);

            // Update attendance working hours
            $break->attendance->updateWorkingHours();

            return $break->fresh();
        });
    }

    /**
     * End all ongoing breaks for an attendance.
     */
    protected function endAllOngoingBreaks(Attendance $attendance): void
    {
        $ongoingBreaks = $attendance->breaks()->ongoing()->get();

        foreach ($ongoingBreaks as $break) {
            $break->endBreak();
        }
    }

    /**
     * Create manual attendance entry.
     */
    public function createManual(StaffProfile $staff, array $data): Attendance
    {
        $date = Carbon::parse($data['attendance_date']);

        // Check if attendance already exists for this date
        $existing = Attendance::where('tenant_id', $staff->tenant_id)
            ->where('staff_profile_id', $staff->id)
            ->whereDate('attendance_date', $date)
            ->first();

        if ($existing) {
            throw new \Exception('Attendance record already exists for this date');
        }

        return DB::transaction(function () use ($staff, $data, $date) {
            $schedule = $this->getStaffSchedule($staff);

            $attendance = Attendance::create([
                'tenant_id' => $staff->tenant_id,
                'staff_profile_id' => $staff->id,
                'branch_id' => $data['branch_id'] ?? $staff->branch_id,
                'working_schedule_id' => $schedule?->id,
                'attendance_date' => $date,
                'check_in_time' => $data['check_in_time'] ?? null,
                'check_out_time' => $data['check_out_time'] ?? null,
                'attendance_type' => Attendance::TYPE_MANUAL,
                'status' => $data['status'] ?? Attendance::STATUS_PRESENT,
                'notes' => $data['notes'] ?? null,
                'late_reason' => $data['late_reason'] ?? null,
                'early_checkout_reason' => $data['early_checkout_reason'] ?? null,
                'created_by' => auth()->id(),
            ]);

            // Calculate working hours if both times are set
            if ($attendance->check_in_time && $attendance->check_out_time) {
                $attendance->working_hours = $attendance->calculateWorkingHours();
                $attendance->save();
            }

            // Evaluate violations if applicable
            if ($schedule) {
                $daySchedule = $schedule->getDaySchedule($date->dayOfWeek);
                if ($daySchedule) {
                    $startTime = $daySchedule['start_time'] ?? null;
                    $endTime = $daySchedule['end_time'] ?? null;

                    if ($attendance->check_in_time && $startTime && $attendance->check_in_time > $startTime) {
                        $this->ruleService->evaluateLateCheckIn(
                            $attendance,
                            $attendance->check_in_time,
                            $startTime
                        );
                    }

                    if ($attendance->check_out_time && $endTime && $attendance->check_out_time < $endTime) {
                        $this->ruleService->evaluateEarlyCheckOut(
                            $attendance,
                            $attendance->check_out_time,
                            $endTime
                        );
                    }
                }
            }

            return $attendance;
        });
    }

    /**
     * Get today's attendance for a staff member.
     */
    public function getTodayAttendance(StaffProfile $staff): ?Attendance
    {
        return Attendance::where('tenant_id', $staff->tenant_id)
            ->where('staff_profile_id', $staff->id)
            ->whereDate('attendance_date', today())
            ->first();
    }

    /**
     * Get current status for a staff member.
     */
    public function getStatus(StaffProfile $staff): array
    {
        $attendance = $this->getTodayAttendance($staff);
        $schedule = $this->getStaffSchedule($staff);
        $daySchedule = $schedule?->getDaySchedule(today()->dayOfWeek);

        $status = [
            'is_working_day' => $schedule ? $schedule->isWorkingDay(today()->dayOfWeek) : true,
            'schedule' => $schedule ? [
                'name' => $schedule->name,
                'start_time' => $daySchedule['start_time'] ?? null,
                'end_time' => $daySchedule['end_time'] ?? null,
            ] : null,
            'is_checked_in' => false,
            'is_checked_out' => false,
            'is_on_break' => false,
            'check_in_time' => null,
            'check_out_time' => null,
            'working_hours' => 0,
            'current_break' => null,
        ];

        if ($attendance) {
            $status['is_checked_in'] = $attendance->isCheckedIn();
            $status['is_checked_out'] = $attendance->isCheckedOut();
            $status['check_in_time'] = $attendance->check_in_time?->format('H:i');
            $status['check_out_time'] = $attendance->check_out_time?->format('H:i');
            $status['working_hours'] = $attendance->working_hours;

            // Check for ongoing break
            $ongoingBreak = $attendance->breaks()->ongoing()->first();
            if ($ongoingBreak) {
                $status['is_on_break'] = true;
                $status['current_break'] = [
                    'id' => $ongoingBreak->id,
                    'start_time' => $ongoingBreak->start_time->format('H:i'),
                    'duration' => $ongoingBreak->calculateDuration(),
                ];
            }
        }

        return $status;
    }

    /**
     * Check if staff is currently checked in.
     */
    public function isCheckedIn(StaffProfile $staff): bool
    {
        $attendance = $this->getTodayAttendance($staff);

        return $attendance && $attendance->isCheckedIn() && !$attendance->isCheckedOut();
    }

    /**
     * Get staff's working schedule.
     */
    public function getStaffSchedule(StaffProfile $staff): ?WorkSchedule
    {
        // First check if staff has an assigned schedule via PractitionerScheduleAssignment
        $assignedSchedule = $staff->workSchedules()
            ->wherePivot('is_active', true)
            ->where(function ($query) {
                $query->whereNull('practitioner_schedule_assignments.effective_from')
                    ->orWhere('practitioner_schedule_assignments.effective_from', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('practitioner_schedule_assignments.effective_until')
                    ->orWhere('practitioner_schedule_assignments.effective_until', '>=', now());
            })
            ->wherePivot('is_primary', true)
            ->first();

        if ($assignedSchedule) {
            return $assignedSchedule;
        }

        // Fallback to default schedule for the branch or tenant
        return WorkSchedule::where('work_schedules.tenant_id', $staff->tenant_id)
            ->where(function ($query) use ($staff) {
                $query->where('work_schedules.branch_id', $staff->branch_id)
                    ->orWhereNull('work_schedules.branch_id');
            })
            ->where('work_schedules.is_active', true)
            ->orderByRaw('work_schedules.branch_id IS NOT NULL DESC')
            ->first();
    }

    /**
     * Create attendance log.
     */
    protected function createLog(
        Attendance $attendance,
        string $type,
        array $locationData = [],
        string $source = AttendanceLog::SOURCE_MANUAL
    ): AttendanceLog {
        return AttendanceLog::create([
            'tenant_id' => $attendance->tenant_id,
            'attendance_id' => $attendance->id,
            'type' => $type,
            'latitude' => $locationData['latitude'] ?? null,
            'longitude' => $locationData['longitude'] ?? null,
            'altitude' => $locationData['altitude'] ?? null,
            'horizontal_accuracy' => $locationData['horizontal_accuracy'] ?? null,
            'vertical_accuracy' => $locationData['vertical_accuracy'] ?? null,
            'speed' => $locationData['speed'] ?? null,
            'address' => $locationData['address'] ?? null,
            'source' => $source,
            'device_info' => $locationData['device_info'] ?? null,
            'created_by' => auth()->id(),
        ]);
    }

    /**
     * Determine attendance type based on source.
     */
    protected function determineAttendanceType(string $source): string
    {
        return match ($source) {
            AttendanceLog::SOURCE_MOBILE => Attendance::TYPE_MOBILE,
            AttendanceLog::SOURCE_BIOMETRIC => Attendance::TYPE_BIOMETRIC,
            AttendanceLog::SOURCE_WEB => Attendance::TYPE_MANUAL,
            default => Attendance::TYPE_MANUAL,
        };
    }

    /**
     * Get attendance summary for a date range.
     */
    public function getSummary(
        StaffProfile $staff,
        Carbon $startDate,
        Carbon $endDate
    ): array {
        $attendances = Attendance::where('tenant_id', $staff->tenant_id)
            ->where('staff_profile_id', $staff->id)
            ->whereBetween('attendance_date', [$startDate, $endDate])
            ->get();

        return [
            'total_days' => $startDate->diffInDays($endDate) + 1,
            'present_days' => $attendances->where('status', Attendance::STATUS_PRESENT)->count(),
            'absent_days' => $attendances->where('status', Attendance::STATUS_ABSENT)->count(),
            'half_days' => $attendances->where('status', Attendance::STATUS_HALF_DAY)->count(),
            'leave_days' => $attendances->where('status', Attendance::STATUS_LEAVE)->count(),
            'total_working_hours' => $attendances->sum('working_hours'),
            'total_late_hours' => $attendances->sum('late_hours'),
            'total_overtime_hours' => $attendances->sum('overtime_hours'),
        ];
    }
}
