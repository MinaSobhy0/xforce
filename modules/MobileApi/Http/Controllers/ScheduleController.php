<?php

namespace Modules\MobileApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Booking\Models\PractitionerScheduleAssignment;
use Modules\Booking\Models\WorkSchedule;

class ScheduleController extends BaseApiController
{
    /**
     * Get current schedule assignment.
     * GET /api/v2/schedule/current
     */
    public function current(): JsonResponse
    {
        $staffProfile = $this->staffProfile();

        if (!$staffProfile) {
            return $this->forbidden(__('mobile_api::mobile.auth.not_staff'));
        }

        if (!class_exists(PractitionerScheduleAssignment::class)) {
            return $this->success([
                'has_schedule' => false,
                'message' => __('mobile_api::mobile.schedule.no_schedule'),
            ]);
        }

        $assignment = PractitionerScheduleAssignment::with('workSchedule')
            ->where('staff_profile_id', $staffProfile->id)
            ->active()
            ->currentlyEffective()
            ->first();

        if (!$assignment || !$assignment->workSchedule) {
            return $this->success([
                'has_schedule' => false,
                'message' => __('mobile_api::mobile.schedule.no_schedule'),
            ]);
        }

        $schedule = $assignment->workSchedule;

        return $this->success([
            'has_schedule' => true,
            'schedule' => [
                'id' => $schedule->id,
                'name' => $schedule->name,
                'type' => $schedule->schedule_type, // fixed, flexible
                'total_weekly_hours' => $schedule->total_weekly_hours,
                'working_days_count' => $schedule->working_days_count,
            ],
            'assigned_from' => $assignment->effective_from?->toDateString(),
            'assigned_until' => $assignment->effective_until?->toDateString(),
            'is_primary' => $assignment->is_primary,
        ]);
    }

    /**
     * Get shifts for the week/month.
     * GET /api/v2/schedule/shifts
     */
    public function shifts(Request $request): JsonResponse
    {
        $staffProfile = $this->staffProfile();

        if (!$staffProfile) {
            return $this->forbidden(__('mobile_api::mobile.auth.not_staff'));
        }

        $startDate = $request->filled('start_date')
            ? \Carbon\Carbon::parse($request->start_date)
            : now()->startOfWeek();

        $endDate = $request->filled('end_date')
            ? \Carbon\Carbon::parse($request->end_date)
            : $startDate->copy()->endOfWeek();

        $shifts = $this->getShiftsForPeriod($staffProfile, $startDate, $endDate);

        return $this->success([
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
            'shifts' => $shifts,
        ]);
    }

    /**
     * Get shift for a specific date.
     * GET /api/v2/schedule/shifts/{date}
     */
    public function shiftForDate(string $date): JsonResponse
    {
        $staffProfile = $this->staffProfile();

        if (!$staffProfile) {
            return $this->forbidden(__('mobile_api::mobile.auth.not_staff'));
        }

        try {
            $targetDate = \Carbon\Carbon::parse($date);
        } catch (\Exception $e) {
            return $this->error('Invalid date format', 400);
        }

        $shift = $this->getShiftForDate($staffProfile, $targetDate);

        if (!$shift) {
            return $this->success([
                'has_shift' => false,
                'date' => $targetDate->toDateString(),
                'day_name' => $targetDate->format('l'),
                'message' => __('mobile_api::mobile.schedule.no_shift'),
            ]);
        }

        return $this->success([
            'has_shift' => true,
            'date' => $targetDate->toDateString(),
            'day_name' => $targetDate->format('l'),
            'shift' => $shift,
        ]);
    }

    /**
     * Get working hours configuration.
     * GET /api/v2/schedule/working-hours
     */
    public function workingHours(): JsonResponse
    {
        $staffProfile = $this->staffProfile();

        if (!$staffProfile) {
            return $this->forbidden(__('mobile_api::mobile.auth.not_staff'));
        }

        if (!class_exists(PractitionerScheduleAssignment::class)) {
            return $this->success([]);
        }

        $assignment = PractitionerScheduleAssignment::with('workSchedule')
            ->where('staff_profile_id', $staffProfile->id)
            ->active()
            ->currentlyEffective()
            ->first();

        if (!$assignment || !$assignment->workSchedule) {
            return $this->success([]);
        }

        $schedule = $assignment->workSchedule;

        // Get working hours for each day
        $workingHours = [];
        $days = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

        // For flexible schedules
        if ($schedule->schedule_type === WorkSchedule::TYPE_FLEXIBLE) {
            $workingDays = $schedule->working_days ?? [];

            foreach ($days as $index => $day) {
                $isWorking = in_array($index, $workingDays);
                $workingHours[] = [
                    'day' => $day,
                    'day_index' => $index,
                    'is_working' => $isWorking,
                    'type' => 'flexible',
                    'required_hours' => $isWorking ? $schedule->required_hours_per_day : null,
                    'time_window' => $isWorking ? [
                        'start' => $schedule->flexible_start_time,
                        'end' => $schedule->flexible_end_time,
                    ] : null,
                ];
            }
        } else {
            // For fixed schedules - use weekly_hours with numeric keys (0=Sunday, 6=Saturday)
            $weeklyHours = $schedule->weekly_hours ?? [];

            foreach ($days as $index => $day) {
                // Check for assignment overrides first
                $effectiveConfig = $assignment->getEffectiveDaySchedule($index);

                if ($effectiveConfig && ($effectiveConfig['is_working'] ?? false)) {
                    $workingHours[] = [
                        'day' => $day,
                        'day_index' => $index,
                        'is_working' => true,
                        'type' => 'fixed',
                        'start_time' => $effectiveConfig['start_time'] ?? '09:00',
                        'end_time' => $effectiveConfig['end_time'] ?? '17:00',
                        'break_start' => $effectiveConfig['break_start'] ?? null,
                        'break_end' => $effectiveConfig['break_end'] ?? null,
                    ];
                } else {
                    $workingHours[] = [
                        'day' => $day,
                        'day_index' => $index,
                        'is_working' => false,
                    ];
                }
            }
        }

        return $this->success([
            'schedule_name' => $schedule->name,
            'schedule_type' => $schedule->schedule_type,
            'total_weekly_hours' => $schedule->total_weekly_hours,
            'hours' => $workingHours,
        ]);
    }

    /**
     * Get shifts for a period.
     */
    protected function getShiftsForPeriod($staffProfile, $startDate, $endDate): array
    {
        $shifts = [];
        $current = $startDate->copy();

        while ($current <= $endDate) {
            $shift = $this->getShiftForDate($staffProfile, $current);

            $shifts[] = [
                'date' => $current->toDateString(),
                'day_name' => $current->format('l'),
                'has_shift' => $shift !== null,
                'shift' => $shift,
            ];

            $current->addDay();
        }

        return $shifts;
    }

    /**
     * Get shift for a specific date.
     */
    protected function getShiftForDate($staffProfile, \Carbon\Carbon $date): ?array
    {
        if (!class_exists(PractitionerScheduleAssignment::class)) {
            return null;
        }

        // Get active assignment for this date
        $assignment = PractitionerScheduleAssignment::with('workSchedule')
            ->where('staff_profile_id', $staffProfile->id)
            ->active()
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_from')
                    ->orWhere('effective_from', '<=', $date);
            })
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_until')
                    ->orWhere('effective_until', '>=', $date);
            })
            ->first();

        if (!$assignment || !$assignment->workSchedule) {
            return null;
        }

        $schedule = $assignment->workSchedule;
        $dayOfWeek = (int) $date->format('w'); // 0 = Sunday, 6 = Saturday

        // For flexible schedules
        if ($schedule->schedule_type === WorkSchedule::TYPE_FLEXIBLE) {
            $workingDays = $schedule->working_days ?? [];

            if (!in_array($dayOfWeek, $workingDays)) {
                return null;
            }

            return [
                'type' => 'flexible',
                'required_hours' => $schedule->required_hours_per_day,
                'time_window' => [
                    'start' => $schedule->flexible_start_time,
                    'end' => $schedule->flexible_end_time,
                ],
            ];
        }

        // For fixed schedules - get effective day schedule (with overrides applied)
        $dayConfig = $assignment->getEffectiveDaySchedule($dayOfWeek);

        if (!$dayConfig || !($dayConfig['is_working'] ?? false)) {
            return null;
        }

        return [
            'type' => 'fixed',
            'start_time' => $dayConfig['start_time'] ?? '09:00',
            'end_time' => $dayConfig['end_time'] ?? '17:00',
            'break_start' => $dayConfig['break_start'] ?? null,
            'break_end' => $dayConfig['break_end'] ?? null,
        ];
    }
}
