<?php

namespace Modules\Booking\Services;

use Modules\Booking\Models\Appointment;
use Modules\Booking\Models\PractitionerSchedule;
use Modules\Booking\Models\PractitionerTimeOff;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AvailabilityService
{
    protected int $slotDuration;
    protected int $bufferMinutes;

    public function __construct()
    {
        $this->slotDuration = config('booking.default_slot_duration', 30);
        $this->bufferMinutes = config('booking.buffer_minutes', 5);
    }

    /**
     * Get available slots for a practitioner on a specific date.
     */
    public function getAvailableSlots(
        string $practitionerId,
        string $branchId,
        Carbon $date,
        int $durationMinutes = null,
        ?string $roomId = null,
        ?string $equipmentId = null
    ): array {
        $duration = $durationMinutes ?? $this->slotDuration;
        $dayOfWeek = $date->dayOfWeek;

        // Get practitioner schedule for this day
        $schedule = PractitionerSchedule::query()
            ->forPractitioner($practitionerId)
            ->forBranch($branchId)
            ->forDay($dayOfWeek)
            ->available()
            ->first();

        if (!$schedule) {
            return [];
        }

        // Check for time off
        $timeOff = PractitionerTimeOff::query()
            ->forPractitioner($practitionerId)
            ->approved()
            ->where(function ($q) use ($branchId) {
                $q->whereNull('branch_id')
                    ->orWhere('branch_id', $branchId);
            })
            ->where('start_date', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', $date);
            })
            ->first();

        if ($timeOff && $timeOff->is_full_day) {
            return [];
        }

        // Get existing appointments
        $existingAppointments = Appointment::query()
            ->forPractitioner($practitionerId)
            ->forDate($date)
            ->active()
            ->orderBy('start_time')
            ->get();

        // Generate time slots
        $slots = [];
        $startTime = Carbon::parse($schedule->start_time);
        $endTime = Carbon::parse($schedule->end_time);
        $breakStart = $schedule->break_start ? Carbon::parse($schedule->break_start) : null;
        $breakEnd = $schedule->break_end ? Carbon::parse($schedule->break_end) : null;

        $current = $startTime->copy();

        while ($current->copy()->addMinutes($duration)->lte($endTime)) {
            $slotEnd = $current->copy()->addMinutes($duration);

            // Check if slot is during break
            if ($breakStart && $breakEnd) {
                if ($current->lt($breakEnd) && $slotEnd->gt($breakStart)) {
                    $current = $breakEnd->copy();
                    continue;
                }
            }

            // Check if slot is during time off (partial day)
            if ($timeOff && !$timeOff->is_full_day) {
                $timeOffStart = Carbon::parse($timeOff->start_time);
                $timeOffEnd = Carbon::parse($timeOff->end_time);
                if ($current->lt($timeOffEnd) && $slotEnd->gt($timeOffStart)) {
                    $current->addMinutes($this->slotDuration);
                    continue;
                }
            }

            // Check if slot conflicts with existing appointments
            $conflict = false;
            foreach ($existingAppointments as $appointment) {
                $appointmentStart = Carbon::parse($appointment->start_time);
                $appointmentEnd = $appointment->end_time
                    ? Carbon::parse($appointment->end_time)
                    : $appointmentStart->copy()->addMinutes($appointment->duration_minutes);

                // Add buffer time
                $appointmentStart->subMinutes($this->bufferMinutes);
                $appointmentEnd->addMinutes($this->bufferMinutes);

                if ($current->lt($appointmentEnd) && $slotEnd->gt($appointmentStart)) {
                    $conflict = true;
                    break;
                }
            }

            if (!$conflict) {
                // Check room availability if specified
                if ($roomId && !$this->isRoomAvailable($roomId, $date, $current, $slotEnd)) {
                    $current->addMinutes($this->slotDuration);
                    continue;
                }

                // Check equipment availability if specified
                if ($equipmentId && !$this->isEquipmentAvailable($equipmentId, $date, $current, $slotEnd)) {
                    $current->addMinutes($this->slotDuration);
                    continue;
                }

                $slots[] = [
                    'start' => $current->format('H:i'),
                    'end' => $slotEnd->format('H:i'),
                    'available' => true,
                    'room_id' => $roomId,
                    'equipment_id' => $equipmentId,
                ];
            }

            $current->addMinutes($this->slotDuration);
        }

        return $slots;
    }

    /**
     * Check if a specific time slot is available.
     */
    public function isSlotAvailable(
        string $practitionerId,
        string $branchId,
        Carbon $date,
        string $startTime,
        int $durationMinutes,
        ?string $excludeAppointmentId = null
    ): bool {
        $start = Carbon::parse($startTime);
        $end = $start->copy()->addMinutes($durationMinutes);
        $dayOfWeek = $date->dayOfWeek;

        // Check practitioner schedule
        $schedule = PractitionerSchedule::query()
            ->forPractitioner($practitionerId)
            ->forBranch($branchId)
            ->forDay($dayOfWeek)
            ->available()
            ->first();

        if (!$schedule) {
            return false;
        }

        $scheduleStart = Carbon::parse($schedule->start_time);
        $scheduleEnd = Carbon::parse($schedule->end_time);

        if ($start->lt($scheduleStart) || $end->gt($scheduleEnd)) {
            return false;
        }

        // Check break time
        if ($schedule->break_start && $schedule->break_end) {
            $breakStart = Carbon::parse($schedule->break_start);
            $breakEnd = Carbon::parse($schedule->break_end);
            if ($start->lt($breakEnd) && $end->gt($breakStart)) {
                return false;
            }
        }

        // Check time off
        $hasTimeOff = PractitionerTimeOff::query()
            ->forPractitioner($practitionerId)
            ->approved()
            ->where(function ($q) use ($branchId) {
                $q->whereNull('branch_id')
                    ->orWhere('branch_id', $branchId);
            })
            ->where('start_date', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', $date);
            })
            ->exists();

        if ($hasTimeOff) {
            return false;
        }

        // Check conflicting appointments
        $conflictQuery = Appointment::query()
            ->forPractitioner($practitionerId)
            ->forDate($date)
            ->active()
            ->where(function ($q) use ($start, $end) {
                $q->whereRaw("start_time < ?", [$end->format('H:i:s')])
                    ->whereRaw("COALESCE(end_time, start_time + (duration_minutes || ' minutes')::interval) > ?", [$start->format('H:i:s')]);
            });

        if ($excludeAppointmentId) {
            $conflictQuery->where('id', '!=', $excludeAppointmentId);
        }

        return !$conflictQuery->exists();
    }

    /**
     * Check if a room is available.
     */
    public function isRoomAvailable(string $roomId, Carbon $date, Carbon $start, Carbon $end): bool
    {
        return !Appointment::query()
            ->forRoom($roomId)
            ->forDate($date)
            ->active()
            ->where(function ($q) use ($start, $end) {
                $q->whereRaw("start_time < ?", [$end->format('H:i:s')])
                    ->whereRaw("COALESCE(end_time, start_time + (duration_minutes || ' minutes')::interval) > ?", [$start->format('H:i:s')]);
            })
            ->exists();
    }

    /**
     * Check if equipment is available.
     */
    public function isEquipmentAvailable(string $equipmentId, Carbon $date, Carbon $start, Carbon $end): bool
    {
        return !Appointment::query()
            ->where('equipment_id', $equipmentId)
            ->forDate($date)
            ->active()
            ->where(function ($q) use ($start, $end) {
                $q->whereRaw("start_time < ?", [$end->format('H:i:s')])
                    ->whereRaw("COALESCE(end_time, start_time + (duration_minutes || ' minutes')::interval) > ?", [$start->format('H:i:s')]);
            })
            ->exists();
    }

    /**
     * Get available practitioners for a specific time slot.
     */
    public function getAvailablePractitioners(
        string $branchId,
        Carbon $date,
        string $startTime,
        int $durationMinutes
    ): Collection {
        $dayOfWeek = $date->dayOfWeek;

        // Get practitioners with schedules on this day
        $schedules = PractitionerSchedule::query()
            ->forBranch($branchId)
            ->forDay($dayOfWeek)
            ->available()
            ->with('practitioner')
            ->get();

        $availablePractitioners = collect();

        foreach ($schedules as $schedule) {
            if ($this->isSlotAvailable(
                $schedule->user_id,
                $branchId,
                $date,
                $startTime,
                $durationMinutes
            )) {
                $availablePractitioners->push($schedule->practitioner);
            }
        }

        return $availablePractitioners;
    }

    /**
     * Get next available slot for a practitioner.
     */
    public function getNextAvailableSlot(
        string $practitionerId,
        string $branchId,
        int $durationMinutes,
        ?Carbon $fromDate = null,
        int $maxDaysAhead = 30
    ): ?array {
        $date = $fromDate ?? today();
        $endDate = $date->copy()->addDays($maxDaysAhead);

        while ($date->lte($endDate)) {
            $slots = $this->getAvailableSlots($practitionerId, $branchId, $date, $durationMinutes);

            if (!empty($slots)) {
                return [
                    'date' => $date->format('Y-m-d'),
                    'slot' => $slots[0],
                ];
            }

            $date->addDay();
        }

        return null;
    }
}
