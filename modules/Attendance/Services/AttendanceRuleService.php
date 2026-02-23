<?php

namespace Modules\Attendance\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Attendance\Models\Attendance;
use Modules\Attendance\Models\AttendanceRule;
use Modules\Attendance\Models\AttendanceRuleAction;
use Modules\Attendance\Models\AttendanceViolation;
use Modules\Auth\Models\User;
use Modules\Booking\Models\WorkSchedule;
use Modules\Staff\Models\StaffProfile;

class AttendanceRuleService
{
    /**
     * Evaluate late check-in and create violation if applicable.
     */
    public function evaluateLateCheckIn(
        Attendance $attendance,
        $checkInTime,
        $scheduledTime
    ): ?AttendanceViolation {
        $schedule = $attendance->workingSchedule;
        if (!$schedule) {
            return null;
        }

        // Calculate late minutes based on schedule
        $daySchedule = $schedule->getDaySchedule(Carbon::parse($attendance->attendance_date)->dayOfWeek);
        if (!$daySchedule || empty($daySchedule['start_time'])) {
            return null;
        }

        $scheduledStart = Carbon::parse($daySchedule['start_time']);
        $actualCheckIn = Carbon::parse($checkInTime);
        $lateMinutes = $actualCheckIn->diffInMinutes($scheduledStart, false);
        if ($lateMinutes <= 0) {
            return null;
        }

        // Find applicable rule
        $rule = $this->findApplicableRule(
            $attendance->staffProfile,
            AttendanceRule::CATEGORY_LATE_CHECKIN,
            $schedule
        );

        if (!$rule) {
            return null;
        }

        // Get occurrence count for this staff
        $occurrenceCount = $this->getViolationOccurrenceCount(
            $attendance->staff_profile_id,
            AttendanceViolation::TYPE_LATE_CHECKIN
        );

        // Find applicable action
        $action = $rule->getApplicableAction($lateMinutes, $occurrenceCount);
        if (!$action) {
            return null;
        }

        return $this->createViolation(
            $attendance,
            $rule,
            $action,
            AttendanceViolation::TYPE_LATE_CHECKIN,
            $lateMinutes,
            $scheduledTime,
            $checkInTime,
            0 // Grace period - can be configured later
        );
    }

    /**
     * Evaluate early check-out and create violation if applicable.
     */
    public function evaluateEarlyCheckOut(
        Attendance $attendance,
        $checkOutTime,
        $scheduledTime
    ): ?AttendanceViolation {
        $schedule = $attendance->workingSchedule;
        if (!$schedule) {
            return null;
        }

        // Calculate early minutes based on schedule
        $daySchedule = $schedule->getDaySchedule(Carbon::parse($attendance->attendance_date)->dayOfWeek);
        if (!$daySchedule || empty($daySchedule['end_time'])) {
            return null;
        }

        $scheduledEnd = Carbon::parse($daySchedule['end_time']);
        $actualCheckOut = Carbon::parse($checkOutTime);
        $earlyMinutes = $scheduledEnd->diffInMinutes($actualCheckOut, false);
        if ($earlyMinutes <= 0) {
            return null;
        }

        // Find applicable rule
        $rule = $this->findApplicableRule(
            $attendance->staffProfile,
            AttendanceRule::CATEGORY_EARLY_CHECKOUT,
            $schedule
        );

        if (!$rule) {
            return null;
        }

        // Get occurrence count
        $occurrenceCount = $this->getViolationOccurrenceCount(
            $attendance->staff_profile_id,
            AttendanceViolation::TYPE_EARLY_CHECKOUT
        );

        // Find applicable action
        $action = $rule->getApplicableAction($earlyMinutes, $occurrenceCount);
        if (!$action) {
            return null;
        }

        return $this->createViolation(
            $attendance,
            $rule,
            $action,
            AttendanceViolation::TYPE_EARLY_CHECKOUT,
            $earlyMinutes,
            $scheduledTime,
            $checkOutTime,
            0 // Grace period - can be configured later
        );
    }

    /**
     * Evaluate missed check-in.
     */
    public function evaluateMissedCheckIn(
        StaffProfile $staff,
        $date
    ): ?AttendanceViolation {
        // Get the staff's schedule
        $schedule = $this->getStaffSchedule($staff);
        if (!$schedule) {
            return null;
        }

        // Check if it's a working day
        $checkDate = Carbon::parse($date);
        if (!$schedule->isWorkingDay($checkDate->dayOfWeek)) {
            return null;
        }

        // Check if attendance exists for this date
        $attendance = Attendance::where('tenant_id', $staff->tenant_id)
            ->where('staff_profile_id', $staff->id)
            ->whereDate('attendance_date', $checkDate)
            ->first();

        if ($attendance && $attendance->check_in_time) {
            return null; // Has check-in, not missed
        }

        // Find applicable rule
        $rule = $this->findApplicableRule(
            $staff,
            AttendanceRule::CATEGORY_MISSED_CHECKIN,
            $schedule
        );

        if (!$rule) {
            return null;
        }

        // Get occurrence count
        $occurrenceCount = $this->getViolationOccurrenceCount(
            $staff->id,
            AttendanceViolation::TYPE_MISSED_CHECKIN
        );

        // Find applicable action
        $action = $rule->getApplicableAction(0, $occurrenceCount);
        if (!$action) {
            return null;
        }

        // Create attendance record if it doesn't exist
        if (!$attendance) {
            $attendance = Attendance::create([
                'tenant_id' => $staff->tenant_id,
                'staff_profile_id' => $staff->id,
                'branch_id' => $staff->branch_id,
                'working_schedule_id' => $schedule->id,
                'attendance_date' => $checkDate,
                'attendance_type' => Attendance::TYPE_MANUAL,
                'status' => Attendance::STATUS_ABSENT,
            ]);
        }

        $daySchedule = $schedule->getDaySchedule($checkDate->dayOfWeek);
        $startTime = $daySchedule['start_time'] ?? null;

        return $this->createViolation(
            $attendance,
            $rule,
            $action,
            AttendanceViolation::TYPE_MISSED_CHECKIN,
            0,
            $startTime,
            null,
            0
        );
    }

    /**
     * Evaluate missed check-out.
     */
    public function evaluateMissedCheckOut(Attendance $attendance): ?AttendanceViolation
    {
        // Check if attendance has check-in but no check-out
        if (!$attendance->check_in_time || $attendance->check_out_time) {
            return null;
        }

        $schedule = $attendance->workingSchedule;
        if (!$schedule) {
            return null;
        }

        // Find applicable rule
        $rule = $this->findApplicableRule(
            $attendance->staffProfile,
            AttendanceRule::CATEGORY_MISSED_CHECKOUT,
            $schedule
        );

        if (!$rule) {
            return null;
        }

        // Get occurrence count
        $occurrenceCount = $this->getViolationOccurrenceCount(
            $attendance->staff_profile_id,
            AttendanceViolation::TYPE_MISSED_CHECKOUT
        );

        // Find applicable action
        $action = $rule->getApplicableAction(0, $occurrenceCount);
        if (!$action) {
            return null;
        }

        $daySchedule = $schedule->getDaySchedule(Carbon::parse($attendance->attendance_date)->dayOfWeek);
        $endTime = $daySchedule['end_time'] ?? null;

        return $this->createViolation(
            $attendance,
            $rule,
            $action,
            AttendanceViolation::TYPE_MISSED_CHECKOUT,
            0,
            $endTime,
            $attendance->check_in_time,
            0
        );
    }

    /**
     * Find applicable rule for a staff member and category.
     */
    public function findApplicableRule(
        StaffProfile $staff,
        string $category,
        ?WorkSchedule $schedule = null
    ): ?AttendanceRule {
        $query = AttendanceRule::where('tenant_id', $staff->tenant_id)
            ->where('category', $category)
            ->where('is_active', true);

        // If schedule is provided, prioritize rules for that schedule
        if ($schedule) {
            $query->where(function ($q) use ($schedule) {
                $q->where('working_schedule_id', $schedule->id)
                  ->orWhereNull('working_schedule_id');
            })->orderByRaw('working_schedule_id IS NOT NULL DESC');
        } else {
            $query->whereNull('working_schedule_id');
        }

        return $query->orderBy('sequence')->first();
    }

    /**
     * Get violation occurrence count for a staff member.
     */
    protected function getViolationOccurrenceCount(
        string $staffProfileId,
        string $violationType,
        ?string $period = 'month'
    ): int {
        $query = AttendanceViolation::where('staff_profile_id', $staffProfileId)
            ->where('violation_type', $violationType)
            ->whereNotIn('status', [
                AttendanceViolation::STATUS_WAIVED,
                AttendanceViolation::STATUS_CANCELLED,
            ]);

        // Apply period filter
        switch ($period) {
            case 'day':
                $query->whereDate('violation_date', today());
                break;
            case 'week':
                $query->whereBetween('violation_date', [
                    now()->startOfWeek(),
                    now()->endOfWeek(),
                ]);
                break;
            case 'month':
                $query->whereMonth('violation_date', now()->month)
                    ->whereYear('violation_date', now()->year);
                break;
            case 'year':
                $query->whereYear('violation_date', now()->year);
                break;
        }

        return $query->count() + 1; // +1 for current violation
    }

    /**
     * Create a violation record.
     */
    protected function createViolation(
        Attendance $attendance,
        AttendanceRule $rule,
        AttendanceRuleAction $action,
        string $violationType,
        int $violationMinutes,
        $scheduledTime,
        $actualTime,
        int $gracePeriodMinutes
    ): AttendanceViolation {
        // Calculate penalty
        $penaltyAmount = $action->calculatePenalty($attendance->staffProfile, $violationMinutes);

        $penaltyDetails = [
            'rule_code' => $rule->code,
            'action_type' => $action->action_type,
            'penalty_type' => $action->penalty_type,
            'base_amount' => $action->penalty_amount_minor,
            'percentage' => $action->penalty_percentage,
            'violation_minutes' => $violationMinutes,
            'calculated_amount' => $penaltyAmount,
        ];

        $violation = AttendanceViolation::create([
            'tenant_id' => $attendance->tenant_id,
            'attendance_id' => $attendance->id,
            'staff_profile_id' => $attendance->staff_profile_id,
            'attendance_rule_id' => $rule->id,
            'attendance_rule_action_id' => $action->id,
            'violation_type' => $violationType,
            'violation_date' => $attendance->attendance_date,
            'scheduled_time' => $scheduledTime,
            'actual_time' => $actualTime,
            'grace_period_minutes' => $gracePeriodMinutes,
            'violation_minutes' => $violationMinutes,
            'penalty_amount_minor' => $penaltyAmount,
            'penalty_type' => $action->penalty_type,
            'penalty_calculation_details' => $penaltyDetails,
            'status' => $action->requires_approval
                ? AttendanceViolation::STATUS_PENDING
                : ($rule->auto_apply ? AttendanceViolation::STATUS_APPROVED : AttendanceViolation::STATUS_PENDING),
        ]);

        // Send notifications if enabled
        if ($rule->send_notification || $action->notification_enabled) {
            $this->sendViolationNotifications($violation, $rule, $action);
        }

        return $violation;
    }

    /**
     * Send violation notifications.
     */
    protected function sendViolationNotifications(
        AttendanceViolation $violation,
        AttendanceRule $rule,
        AttendanceRuleAction $action
    ): void {
        // This would integrate with the notification system
        // For now, we'll log the notification

        $recipients = [];

        // Always notify the employee
        if ($violation->staffProfile?->user) {
            $recipients[] = [
                'user' => $violation->staffProfile->user,
                'type' => 'employee',
            ];
        }

        // Notify manager if enabled
        if ($rule->notify_manager || $action->notify_manager) {
            // Get manager - this would depend on your staff hierarchy
            // For now, skip
        }

        // Notify HR if enabled
        if ($rule->notify_hr || $action->notify_hr) {
            // Get HR users - this would depend on your role system
            // For now, skip
        }

        // Log for debugging
        \Log::info('Violation notifications would be sent', [
            'violation_id' => $violation->id,
            'violation_type' => $violation->violation_type,
            'staff_id' => $violation->staff_profile_id,
        ]);
    }

    /**
     * Get staff violation statistics.
     */
    public function getStaffViolationStats(
        StaffProfile $staff,
        Carbon $startDate,
        Carbon $endDate
    ): array {
        $violations = AttendanceViolation::where('staff_profile_id', $staff->id)
            ->whereBetween('violation_date', [$startDate, $endDate])
            ->get();

        $byType = $violations->groupBy('violation_type')->map->count();
        $byStatus = $violations->groupBy('status')->map->count();

        return [
            'total' => $violations->count(),
            'pending' => $violations->where('status', AttendanceViolation::STATUS_PENDING)->count(),
            'approved' => $violations->where('status', AttendanceViolation::STATUS_APPROVED)->count(),
            'waived' => $violations->where('status', AttendanceViolation::STATUS_WAIVED)->count(),
            'applied' => $violations->where('status', AttendanceViolation::STATUS_APPLIED)->count(),
            'total_penalty' => $violations->whereIn('status', [
                AttendanceViolation::STATUS_APPROVED,
                AttendanceViolation::STATUS_APPLIED,
            ])->sum('penalty_amount_minor'),
            'by_type' => $byType->toArray(),
            'by_status' => $byStatus->toArray(),
        ];
    }

    /**
     * Get pending violations for a manager.
     */
    public function getPendingViolations(?string $managerId = null): Collection
    {
        $query = AttendanceViolation::where('status', AttendanceViolation::STATUS_PENDING)
            ->with(['staffProfile', 'rule', 'ruleAction']);

        // If manager ID provided, filter by staff under this manager
        // This would depend on your staff hierarchy implementation

        return $query->orderBy('violation_date', 'desc')->get();
    }

    /**
     * Bulk approve violations.
     */
    public function bulkApproveViolations(
        array $violationIds,
        User $approver,
        ?string $notes = null
    ): int {
        $approved = 0;

        DB::transaction(function () use ($violationIds, $approver, $notes, &$approved) {
            $violations = AttendanceViolation::whereIn('id', $violationIds)
                ->where('status', AttendanceViolation::STATUS_PENDING)
                ->get();

            foreach ($violations as $violation) {
                if ($violation->approve($approver, $notes)) {
                    $approved++;
                }
            }
        });

        return $approved;
    }

    /**
     * Waive a violation.
     */
    public function waiveViolation(
        string $violationId,
        User $waiver,
        string $reason
    ): bool {
        $violation = AttendanceViolation::find($violationId);

        if (!$violation) {
            return false;
        }

        return $violation->waive($waiver, $reason);
    }

    /**
     * Get unapplied violations for payroll.
     */
    public function getUnappliedViolationsForPayroll(
        StaffProfile $staff,
        Carbon $startDate,
        Carbon $endDate
    ): Collection {
        return AttendanceViolation::where('staff_profile_id', $staff->id)
            ->whereBetween('violation_date', [$startDate, $endDate])
            ->readyToApply()
            ->get();
    }

    /**
     * Calculate total penalty for payroll.
     */
    public function calculateTotalPenaltyForPayroll(
        StaffProfile $staff,
        Carbon $startDate,
        Carbon $endDate
    ): int {
        return $this->getUnappliedViolationsForPayroll($staff, $startDate, $endDate)
            ->sum('penalty_amount_minor');
    }

    /**
     * Get staff's working schedule.
     */
    protected function getStaffSchedule(StaffProfile $staff): ?WorkSchedule
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
        return WorkSchedule::where('tenant_id', $staff->tenant_id)
            ->where(function ($query) use ($staff) {
                $query->where('branch_id', $staff->branch_id)
                    ->orWhereNull('branch_id');
            })
            ->where('is_active', true)
            ->orderByRaw('branch_id IS NOT NULL DESC')
            ->first();
    }
}
