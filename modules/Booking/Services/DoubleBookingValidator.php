<?php

namespace Modules\Booking\Services;

use Carbon\Carbon;
use Modules\Booking\Models\Appointment;
use Illuminate\Support\Collection;

class DoubleBookingValidator
{
    /**
     * Check for conflicts with existing appointments.
     *
     * @return array Array of conflict messages, empty if no conflicts
     */
    public function validate(
        Carbon $date,
        string $startTime,
        string $endTime,
        ?int $practitionerId = null,
        ?int $roomId = null,
        ?int $equipmentId = null,
        ?int $excludeAppointmentId = null
    ): array {
        $conflicts = [];

        $startDateTime = $date->copy()->setTimeFromTimeString($startTime);
        $endDateTime = $date->copy()->setTimeFromTimeString($endTime);

        // Check practitioner availability
        if ($practitionerId) {
            $practitionerConflict = $this->checkPractitionerConflict(
                $date,
                $startDateTime,
                $endDateTime,
                $practitionerId,
                $excludeAppointmentId
            );

            if ($practitionerConflict) {
                $conflicts['practitioner'] = $practitionerConflict;
            }
        }

        // Check room availability
        if ($roomId) {
            $roomConflict = $this->checkRoomConflict(
                $date,
                $startDateTime,
                $endDateTime,
                $roomId,
                $excludeAppointmentId
            );

            if ($roomConflict) {
                $conflicts['room'] = $roomConflict;
            }
        }

        // Check equipment availability
        if ($equipmentId) {
            $equipmentConflict = $this->checkEquipmentConflict(
                $date,
                $startDateTime,
                $endDateTime,
                $equipmentId,
                $excludeAppointmentId
            );

            if ($equipmentConflict) {
                $conflicts['equipment'] = $equipmentConflict;
            }
        }

        return $conflicts;
    }

    /**
     * Check if practitioner has conflicting appointments.
     */
    protected function checkPractitionerConflict(
        Carbon $date,
        Carbon $startDateTime,
        Carbon $endDateTime,
        int $practitionerId,
        ?int $excludeAppointmentId
    ): ?string {
        $conflictingAppointment = $this->findConflictingAppointment(
            $date,
            $startDateTime,
            $endDateTime,
            'practitioner_user_id',
            $practitionerId,
            $excludeAppointmentId
        );

        if ($conflictingAppointment) {
            return __('booking::appointments.validation.practitioner_busy', [
                'time' => $conflictingAppointment->start_time->format('H:i') . ' - ' . $conflictingAppointment->end_time->format('H:i'),
                'patient' => $conflictingAppointment->patient?->name ?? 'Unknown',
            ]);
        }

        return null;
    }

    /**
     * Check if room has conflicting appointments.
     */
    protected function checkRoomConflict(
        Carbon $date,
        Carbon $startDateTime,
        Carbon $endDateTime,
        int $roomId,
        ?int $excludeAppointmentId
    ): ?string {
        $conflictingAppointment = $this->findConflictingAppointment(
            $date,
            $startDateTime,
            $endDateTime,
            'room_id',
            $roomId,
            $excludeAppointmentId
        );

        if ($conflictingAppointment) {
            return __('booking::appointments.validation.room_busy', [
                'time' => $conflictingAppointment->start_time->format('H:i') . ' - ' . $conflictingAppointment->end_time->format('H:i'),
                'patient' => $conflictingAppointment->patient?->name ?? 'Unknown',
            ]);
        }

        return null;
    }

    /**
     * Check if equipment has conflicting appointments.
     */
    protected function checkEquipmentConflict(
        Carbon $date,
        Carbon $startDateTime,
        Carbon $endDateTime,
        int $equipmentId,
        ?int $excludeAppointmentId
    ): ?string {
        $conflictingAppointment = $this->findConflictingAppointment(
            $date,
            $startDateTime,
            $endDateTime,
            'equipment_id',
            $equipmentId,
            $excludeAppointmentId
        );

        if ($conflictingAppointment) {
            return __('booking::appointments.validation.equipment_busy', [
                'time' => $conflictingAppointment->start_time->format('H:i') . ' - ' . $conflictingAppointment->end_time->format('H:i'),
                'patient' => $conflictingAppointment->patient?->name ?? 'Unknown',
            ]);
        }

        return null;
    }

    /**
     * Find a conflicting appointment based on time overlap.
     */
    protected function findConflictingAppointment(
        Carbon $date,
        Carbon $startDateTime,
        Carbon $endDateTime,
        string $field,
        int $value,
        ?int $excludeAppointmentId
    ): ?Appointment {
        $query = Appointment::query()
            ->where('date', $date->toDateString())
            ->where($field, $value)
            ->whereNotIn('status', [
                Appointment::STATUS_CANCELLED,
                Appointment::STATUS_NO_SHOW,
            ])
            ->where(function ($q) use ($startDateTime, $endDateTime) {
                // Check for time overlap:
                // New appointment starts during existing OR ends during existing OR contains existing
                $q->where(function ($inner) use ($startDateTime, $endDateTime) {
                    // New start is within existing appointment
                    $inner->whereRaw('? >= start_time AND ? < end_time', [
                        $startDateTime->format('H:i:s'),
                        $startDateTime->format('H:i:s'),
                    ]);
                })->orWhere(function ($inner) use ($startDateTime, $endDateTime) {
                    // New end is within existing appointment
                    $inner->whereRaw('? > start_time AND ? <= end_time', [
                        $endDateTime->format('H:i:s'),
                        $endDateTime->format('H:i:s'),
                    ]);
                })->orWhere(function ($inner) use ($startDateTime, $endDateTime) {
                    // New appointment contains existing
                    $inner->whereRaw('start_time >= ? AND end_time <= ?', [
                        $startDateTime->format('H:i:s'),
                        $endDateTime->format('H:i:s'),
                    ]);
                });
            });

        if ($excludeAppointmentId) {
            $query->where('id', '!=', $excludeAppointmentId);
        }

        return $query->with('patient')->first();
    }

    /**
     * Get available time slots for a given date, practitioner, room, and/or equipment.
     */
    public function getAvailableSlots(
        Carbon $date,
        int $durationMinutes,
        ?int $practitionerId = null,
        ?int $roomId = null,
        ?int $equipmentId = null,
        string $startOfDay = '09:00',
        string $endOfDay = '21:00',
        int $slotIntervalMinutes = 15
    ): Collection {
        $slots = collect();
        $current = $date->copy()->setTimeFromTimeString($startOfDay);
        $dayEnd = $date->copy()->setTimeFromTimeString($endOfDay);

        while ($current->copy()->addMinutes($durationMinutes)->lte($dayEnd)) {
            $slotEnd = $current->copy()->addMinutes($durationMinutes);

            $conflicts = $this->validate(
                $date,
                $current->format('H:i'),
                $slotEnd->format('H:i'),
                $practitionerId,
                $roomId,
                $equipmentId
            );

            if (empty($conflicts)) {
                $slots->push([
                    'start' => $current->format('H:i'),
                    'end' => $slotEnd->format('H:i'),
                ]);
            }

            $current->addMinutes($slotIntervalMinutes);
        }

        return $slots;
    }
}
