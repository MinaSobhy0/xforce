<?php

namespace Modules\MobileApi\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Booking\Models\Appointment;
use Modules\Booking\Models\PractitionerScheduleAssignment;
use Modules\Booking\Models\PractitionerTimeOff;
use Modules\Booking\Models\WorkSchedule;

class CalendarController extends BaseApiController
{
    /**
     * Get calendar events for a month.
     * GET /api/v2/calendar
     *
     * Returns all events (appointments, time-off, shifts) for the authenticated doctor.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $this->user();

        if (! $user) {
            return $this->unauthorized();
        }

        $month = $request->integer('month', now()->month);
        $year = $request->integer('year', now()->year);

        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        // Build calendar days with all events
        $calendarDays = [];
        $current = $startDate->copy();

        while ($current <= $endDate) {
            $dateString = $current->toDateString();

            $calendarDays[$dateString] = [
                'date' => $dateString,
                'day' => $current->day,
                'day_name' => $current->format('D'),
                'is_today' => $current->isToday(),
                'is_weekend' => $current->isWeekend(),
                'events' => [],
            ];

            $current->addDay();
        }

        // Get appointments
        $appointments = $this->getAppointments($user->id, $startDate, $endDate);
        foreach ($appointments as $appointment) {
            $dateKey = $appointment['date'];
            if (isset($calendarDays[$dateKey])) {
                $calendarDays[$dateKey]['events'][] = $appointment;
            }
        }

        // Get time-off
        $timeOffs = $this->getTimeOffs($user->id, $startDate, $endDate);
        foreach ($timeOffs as $timeOff) {
            // Time-off can span multiple days
            $timeOffStart = Carbon::parse($timeOff['start_date']);
            $timeOffEnd = Carbon::parse($timeOff['end_date']);

            // SECURITY: Clamp iteration to the requested date range to prevent DoS
            // from time-off entries spanning very long periods
            $iterStart = $timeOffStart->lt($startDate) ? $startDate->copy() : $timeOffStart->copy();
            $iterEnd = $timeOffEnd->gt($endDate) ? $endDate : $timeOffEnd;

            $timeOffCurrent = $iterStart;
            while ($timeOffCurrent <= $iterEnd) {
                $dateKey = $timeOffCurrent->toDateString();
                if (isset($calendarDays[$dateKey])) {
                    $calendarDays[$dateKey]['events'][] = array_merge($timeOff, [
                        'date' => $dateKey,
                    ]);
                }
                $timeOffCurrent->addDay();
            }
        }

        // Get shifts/working hours
        $shifts = $this->getShifts($startDate, $endDate);
        foreach ($shifts as $shift) {
            $dateKey = $shift['date'];
            if (isset($calendarDays[$dateKey])) {
                $calendarDays[$dateKey]['shift'] = $shift;
                $calendarDays[$dateKey]['is_working_day'] = $shift['is_working'];
            }
        }

        // Sort events by time within each day
        foreach ($calendarDays as &$day) {
            usort($day['events'], function ($a, $b) {
                $timeA = $a['time'] ?? '00:00';
                $timeB = $b['time'] ?? '00:00';

                return strcmp($timeA, $timeB);
            });
        }

        return $this->success([
            'month' => $month,
            'year' => $year,
            'month_name' => Carbon::create($year, $month, 1)->format('F'),
            'days' => array_values($calendarDays),
            'summary' => [
                'total_appointments' => count($appointments),
                'total_time_off_days' => count($timeOffs),
                'working_days' => collect($calendarDays)->where('is_working_day', true)->count(),
            ],
        ]);
    }

    /**
     * Get calendar events for a specific day.
     * GET /api/v2/calendar/{date}
     */
    public function day(string $date): JsonResponse
    {
        $user = $this->user();

        if (! $user) {
            return $this->unauthorized();
        }

        try {
            $targetDate = Carbon::parse($date);
        } catch (\Exception $e) {
            return $this->error(__('mobile_api::mobile.calendar.invalid_date'), 400);
        }

        // Get appointments for the day
        $appointments = $this->getAppointments($user->id, $targetDate, $targetDate);

        // Get time-off for the day
        $timeOffs = $this->getTimeOffsForDate($user->id, $targetDate);

        // Get shift for the day
        $shift = $this->getShiftForDate($targetDate);

        // Combine all events
        $events = array_merge($appointments, $timeOffs);

        // Sort by time
        usort($events, function ($a, $b) {
            $timeA = $a['time'] ?? '00:00';
            $timeB = $b['time'] ?? '00:00';

            return strcmp($timeA, $timeB);
        });

        return $this->success([
            'date' => $targetDate->toDateString(),
            'day_name' => $targetDate->format('l'),
            'is_today' => $targetDate->isToday(),
            'is_working_day' => $shift['is_working'] ?? false,
            'shift' => $shift,
            'events' => $events,
            'summary' => [
                'total_appointments' => count($appointments),
                'completed_appointments' => collect($appointments)->where('status', 'completed')->count(),
                'has_time_off' => count($timeOffs) > 0,
            ],
        ]);
    }

    /**
     * Get calendar events for a week.
     * GET /api/v2/calendar/week
     */
    public function week(Request $request): JsonResponse
    {
        $user = $this->user();

        if (! $user) {
            return $this->unauthorized();
        }

        $startDate = $request->filled('start_date')
            ? Carbon::parse($request->start_date)->startOfWeek()
            : now()->startOfWeek();

        $endDate = $startDate->copy()->endOfWeek();

        // Build week days
        $weekDays = [];
        $current = $startDate->copy();

        while ($current <= $endDate) {
            $dateString = $current->toDateString();

            $weekDays[$dateString] = [
                'date' => $dateString,
                'day' => $current->day,
                'day_name' => $current->format('D'),
                'is_today' => $current->isToday(),
                'is_weekend' => $current->isWeekend(),
                'events' => [],
            ];

            $current->addDay();
        }

        // Get appointments
        $appointments = $this->getAppointments($user->id, $startDate, $endDate);
        foreach ($appointments as $appointment) {
            $dateKey = $appointment['date'];
            if (isset($weekDays[$dateKey])) {
                $weekDays[$dateKey]['events'][] = $appointment;
            }
        }

        // Get time-off
        $timeOffs = $this->getTimeOffs($user->id, $startDate, $endDate);
        foreach ($timeOffs as $timeOff) {
            $timeOffStart = Carbon::parse($timeOff['start_date']);
            $timeOffEnd = Carbon::parse($timeOff['end_date']);

            // SECURITY: Clamp iteration to the requested date range to prevent DoS
            // from time-off entries spanning very long periods
            $iterStart = $timeOffStart->lt($startDate) ? $startDate->copy() : $timeOffStart->copy();
            $iterEnd = $timeOffEnd->gt($endDate) ? $endDate : $timeOffEnd;

            $timeOffCurrent = $iterStart;
            while ($timeOffCurrent <= $iterEnd) {
                $dateKey = $timeOffCurrent->toDateString();
                if (isset($weekDays[$dateKey])) {
                    $weekDays[$dateKey]['events'][] = array_merge($timeOff, [
                        'date' => $dateKey,
                    ]);
                }
                $timeOffCurrent->addDay();
            }
        }

        // Get shifts
        $shifts = $this->getShifts($startDate, $endDate);
        foreach ($shifts as $shift) {
            $dateKey = $shift['date'];
            if (isset($weekDays[$dateKey])) {
                $weekDays[$dateKey]['shift'] = $shift;
                $weekDays[$dateKey]['is_working_day'] = $shift['is_working'];
            }
        }

        // Sort events
        foreach ($weekDays as &$day) {
            usort($day['events'], function ($a, $b) {
                return strcmp($a['time'] ?? '00:00', $b['time'] ?? '00:00');
            });
        }

        return $this->success([
            'week_start' => $startDate->toDateString(),
            'week_end' => $endDate->toDateString(),
            'days' => array_values($weekDays),
            'summary' => [
                'total_appointments' => count($appointments),
                'working_days' => collect($weekDays)->where('is_working_day', true)->count(),
            ],
        ]);
    }

    /**
     * Get upcoming events (next 7 days).
     * GET /api/v2/calendar/upcoming
     */
    public function upcoming(Request $request): JsonResponse
    {
        $user = $this->user();

        if (! $user) {
            return $this->unauthorized();
        }

        $days = min($request->integer('days', 7), 30);
        $startDate = now()->startOfDay();
        $endDate = now()->addDays($days)->endOfDay();

        $events = [];

        // Get appointments
        $appointments = $this->getAppointments($user->id, $startDate, $endDate);
        foreach ($appointments as $appointment) {
            $events[] = $appointment;
        }

        // Get time-off
        $timeOffs = $this->getTimeOffs($user->id, $startDate, $endDate);
        foreach ($timeOffs as $timeOff) {
            $events[] = $timeOff;
        }

        // Sort by date and time
        usort($events, function ($a, $b) {
            $dateCompare = strcmp($a['date'] ?? $a['start_date'], $b['date'] ?? $b['start_date']);
            if ($dateCompare !== 0) {
                return $dateCompare;
            }

            return strcmp($a['time'] ?? '00:00', $b['time'] ?? '00:00');
        });

        return $this->success([
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
                'days' => $days,
            ],
            'events' => $events,
            'summary' => [
                'total_appointments' => count($appointments),
                'total_time_off_days' => count($timeOffs),
            ],
        ]);
    }

    /**
     * Get appointments for a date range.
     */
    protected function getAppointments(int $userId, Carbon $startDate, Carbon $endDate): array
    {
        if (! class_exists(Appointment::class)) {
            return [];
        }

        $appointments = Appointment::with(['patient', 'service'])
            ->where('practitioner_id', $userId)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        return $appointments->map(function ($a) {
            return [
                'id' => $a->id,
                'type' => 'appointment',
                'date' => $a->date->toDateString(),
                'time' => $a->start_time?->format('H:i'),
                'end_time' => $a->end_time?->format('H:i'),
                'title' => $a->patient?->full_name ?? __('mobile_api::mobile.calendar.appointment'),
                'subtitle' => $a->service?->name,
                'status' => $a->status,
                'status_color' => $this->getAppointmentStatusColor($a->status),
                'duration_minutes' => $a->duration_minutes,
                'patient' => $a->patient ? [
                    'id' => $a->patient->id,
                    'name' => $a->patient->full_name,
                    'phone' => $a->patient->phone,
                ] : null,
                'service' => $a->service ? [
                    'id' => $a->service->id,
                    'name' => $a->service->name,
                ] : null,
            ];
        })->all();
    }

    /**
     * Get time-offs for a date range.
     */
    protected function getTimeOffs(int $userId, Carbon $startDate, Carbon $endDate): array
    {
        if (! class_exists(PractitionerTimeOff::class)) {
            return [];
        }

        $timeOffs = PractitionerTimeOff::with('timeOffType')
            ->where('user_id', $userId)
            ->whereIn('status', [PractitionerTimeOff::STATUS_PENDING, PractitionerTimeOff::STATUS_APPROVED])
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate])
                    ->orWhere(function ($q) use ($startDate, $endDate) {
                        $q->where('start_date', '<=', $startDate)
                            ->where('end_date', '>=', $endDate);
                    });
            })
            ->get();

        return $timeOffs->map(function ($t) {
            return [
                'id' => $t->id,
                'type' => 'time_off',
                'start_date' => $t->start_date->toDateString(),
                'end_date' => $t->end_date->toDateString(),
                'date' => $t->start_date->toDateString(),
                'time' => $t->start_time ?? null,
                'title' => $t->timeOffType?->translated_name ?? __('mobile_api::mobile.calendar.time_off'),
                'subtitle' => $t->is_full_day
                    ? __('mobile_api::mobile.calendar.full_day')
                    : ($t->start_time.' - '.$t->end_time),
                'status' => $t->status,
                'status_color' => $t->status === 'approved' ? 'success' : 'warning',
                'color' => $t->timeOffType?->color ?? 'gray',
                'is_full_day' => $t->is_full_day,
                'reason' => $t->reason,
            ];
        })->all();
    }

    /**
     * Get time-offs for a specific date.
     */
    protected function getTimeOffsForDate(int $userId, Carbon $date): array
    {
        if (! class_exists(PractitionerTimeOff::class)) {
            return [];
        }

        $timeOffs = PractitionerTimeOff::with('timeOffType')
            ->where('user_id', $userId)
            ->whereIn('status', [PractitionerTimeOff::STATUS_PENDING, PractitionerTimeOff::STATUS_APPROVED])
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->get();

        return $timeOffs->map(function ($t) use ($date) {
            return [
                'id' => $t->id,
                'type' => 'time_off',
                'date' => $date->toDateString(),
                'start_date' => $t->start_date->toDateString(),
                'end_date' => $t->end_date->toDateString(),
                'time' => $t->start_time ?? null,
                'title' => $t->timeOffType?->translated_name ?? __('mobile_api::mobile.calendar.time_off'),
                'subtitle' => $t->is_full_day
                    ? __('mobile_api::mobile.calendar.full_day')
                    : ($t->start_time.' - '.$t->end_time),
                'status' => $t->status,
                'status_color' => $t->status === 'approved' ? 'success' : 'warning',
                'color' => $t->timeOffType?->color ?? 'gray',
                'is_full_day' => $t->is_full_day,
            ];
        })->all();
    }

    /**
     * Get shifts for a date range.
     */
    protected function getShifts(Carbon $startDate, Carbon $endDate): array
    {
        $staffProfile = $this->staffProfile();

        if (! $staffProfile || ! class_exists(PractitionerScheduleAssignment::class)) {
            return [];
        }

        $shifts = [];
        $current = $startDate->copy();

        while ($current <= $endDate) {
            $shift = $this->getShiftForDate($current);
            if ($shift) {
                $shifts[] = $shift;
            }
            $current->addDay();
        }

        return $shifts;
    }

    /**
     * Get shift for a specific date.
     */
    protected function getShiftForDate(Carbon $date): ?array
    {
        $staffProfile = $this->staffProfile();

        if (! $staffProfile || ! class_exists(PractitionerScheduleAssignment::class)) {
            return [
                'date' => $date->toDateString(),
                'is_working' => false,
            ];
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

        if (! $assignment || ! $assignment->workSchedule) {
            return [
                'date' => $date->toDateString(),
                'is_working' => false,
            ];
        }

        $schedule = $assignment->workSchedule;
        $dayOfWeek = (int) $date->format('w');

        // For flexible schedules
        if ($schedule->schedule_type === WorkSchedule::TYPE_FLEXIBLE) {
            $workingDays = $schedule->working_days ?? [];

            if (! in_array($dayOfWeek, $workingDays)) {
                return [
                    'date' => $date->toDateString(),
                    'is_working' => false,
                ];
            }

            return [
                'date' => $date->toDateString(),
                'is_working' => true,
                'type' => 'flexible',
                'required_hours' => $schedule->required_hours_per_day,
                'time_window' => [
                    'start' => $schedule->flexible_start_time,
                    'end' => $schedule->flexible_end_time,
                ],
            ];
        }

        // For fixed schedules
        $dayConfig = $assignment->getEffectiveDaySchedule($dayOfWeek);

        if (! $dayConfig || ! ($dayConfig['is_working'] ?? false)) {
            return [
                'date' => $date->toDateString(),
                'is_working' => false,
            ];
        }

        return [
            'date' => $date->toDateString(),
            'is_working' => true,
            'type' => 'fixed',
            'start_time' => $dayConfig['start_time'] ?? '09:00',
            'end_time' => $dayConfig['end_time'] ?? '17:00',
            'break_start' => $dayConfig['break_start'] ?? null,
            'break_end' => $dayConfig['break_end'] ?? null,
        ];
    }

    /**
     * Get appointment status color.
     */
    protected function getAppointmentStatusColor(string $status): string
    {
        return match ($status) {
            'scheduled', 'confirmed' => 'primary',
            'arrived' => 'info',
            'in_progress' => 'warning',
            'completed' => 'success',
            'cancelled', 'no_show' => 'danger',
            default => 'gray',
        };
    }
}
