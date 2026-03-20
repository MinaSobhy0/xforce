<?php

namespace Modules\MobileApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScheduleController extends BaseApiController
{
    /**
     * Get current schedule assignment.
     * GET /api/v2/schedule/current
     */
    public function current(): JsonResponse
    {
        $staffProfile = $this->staffProfile();

        if (!class_exists(\Modules\Attendance\Models\ScheduleAssignment::class)) {
            return $this->success([
                'has_schedule' => false,
                'message' => __('mobile_api::mobile.schedule.no_schedule'),
            ]);
        }

        $assignment = \Modules\Attendance\Models\ScheduleAssignment::with('schedule')
            ->where('staff_profile_id', $staffProfile->id)
            ->where('is_active', true)
            ->first();

        if (!$assignment || !$assignment->schedule) {
            return $this->success([
                'has_schedule' => false,
                'message' => __('mobile_api::mobile.schedule.no_schedule'),
            ]);
        }

        $schedule = $assignment->schedule;

        return $this->success([
            'has_schedule' => true,
            'schedule' => [
                'id' => $schedule->id,
                'name' => $schedule->name,
                'type' => $schedule->type, // fixed, flexible, rotating
                'working_days' => $schedule->working_days ?? [],
            ],
            'assigned_from' => $assignment->effective_from?->toDateString(),
        ]);
    }

    /**
     * Get shifts for the week/month.
     * GET /api/v2/schedule/shifts
     */
    public function shifts(Request $request): JsonResponse
    {
        $staffProfile = $this->staffProfile();

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

        if (!class_exists(\Modules\Attendance\Models\ScheduleAssignment::class)) {
            return $this->success([]);
        }

        $assignment = \Modules\Attendance\Models\ScheduleAssignment::with('schedule')
            ->where('staff_profile_id', $staffProfile->id)
            ->where('is_active', true)
            ->first();

        if (!$assignment || !$assignment->schedule) {
            return $this->success([]);
        }

        $schedule = $assignment->schedule;

        // Get working hours for each day
        $workingHours = [];
        $days = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

        foreach ($days as $index => $day) {
            $dayConfig = $schedule->working_days[$day] ?? null;

            if ($dayConfig && ($dayConfig['is_working'] ?? false)) {
                $workingHours[] = [
                    'day' => $day,
                    'day_index' => $index,
                    'is_working' => true,
                    'start_time' => $dayConfig['start_time'] ?? '09:00',
                    'end_time' => $dayConfig['end_time'] ?? '17:00',
                    'break_start' => $dayConfig['break_start'] ?? null,
                    'break_end' => $dayConfig['break_end'] ?? null,
                ];
            } else {
                $workingHours[] = [
                    'day' => $day,
                    'day_index' => $index,
                    'is_working' => false,
                ];
            }
        }

        return $this->success([
            'schedule_name' => $schedule->name,
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
        // Check for specific shift override first
        if (class_exists(\Modules\Attendance\Models\ShiftOverride::class)) {
            $override = \Modules\Attendance\Models\ShiftOverride::where('staff_profile_id', $staffProfile->id)
                ->whereDate('date', $date)
                ->first();

            if ($override) {
                if ($override->is_day_off) {
                    return null;
                }

                return [
                    'type' => 'override',
                    'start_time' => $override->start_time,
                    'end_time' => $override->end_time,
                    'break_start' => $override->break_start,
                    'break_end' => $override->break_end,
                    'note' => $override->note,
                ];
            }
        }

        // Get from schedule assignment
        if (!class_exists(\Modules\Attendance\Models\ScheduleAssignment::class)) {
            return null;
        }

        $assignment = \Modules\Attendance\Models\ScheduleAssignment::with('schedule')
            ->where('staff_profile_id', $staffProfile->id)
            ->where('is_active', true)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_from')
                    ->orWhere('effective_from', '<=', $date);
            })
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $date);
            })
            ->first();

        if (!$assignment || !$assignment->schedule) {
            return null;
        }

        $schedule = $assignment->schedule;
        $dayName = strtolower($date->format('l'));
        $dayConfig = $schedule->working_days[$dayName] ?? null;

        if (!$dayConfig || !($dayConfig['is_working'] ?? false)) {
            return null;
        }

        return [
            'type' => 'scheduled',
            'start_time' => $dayConfig['start_time'] ?? '09:00',
            'end_time' => $dayConfig['end_time'] ?? '17:00',
            'break_start' => $dayConfig['break_start'] ?? null,
            'break_end' => $dayConfig['break_end'] ?? null,
        ];
    }
}
