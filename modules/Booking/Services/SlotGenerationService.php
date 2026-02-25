<?php

namespace Modules\Booking\Services;

use Modules\Booking\Models\Appointment;
use Modules\Booking\Models\PractitionerSchedule;
use Modules\Booking\Models\PractitionerScheduleAssignment;
use Modules\Booking\Models\PractitionerTimeOff;
use Modules\Booking\Models\BookingBlackoutDate;
use Modules\Services\Models\Service;
use Modules\Equipment\Models\Equipment;
use Modules\Core\Models\Room;
use Modules\Auth\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SlotGenerationService
{
    protected int $defaultSlotDuration;
    protected int $bufferMinutes;
    protected int $maxAdvanceBookingDays;
    protected int $minAdvanceHours;

    public function __construct()
    {
        $this->defaultSlotDuration = config('booking.default_slot_duration', 30);
        $this->bufferMinutes = config('booking.buffer_minutes', 5);
        $this->maxAdvanceBookingDays = config('booking.max_advance_booking_days', 60);
        $this->minAdvanceHours = config('booking.min_advance_hours', 0);
    }

    /**
     * Generate available time slots for a service at a branch on a specific date.
     * Returns slots with available practitioners, room, and equipment assignments.
     */
    public function generateAvailableSlots(
        string $serviceId,
        string $branchId,
        Carbon $date,
        ?int $durationOverride = null
    ): Collection {
        $service = Service::with(['qualifiedStaff', 'rooms', 'requiredEquipment'])
            ->find($serviceId);

        if (!$service) {
            return collect();
        }

        // Check service time restrictions
        if (!$this->isDateAllowedForService($service, $date)) {
            return collect();
        }

        // Check blackout dates
        if ($this->isDateBlocked($date, $branchId)) {
            return collect();
        }

        // Get effective duration (override or service duration)
        $duration = $durationOverride ?? $service->duration_minutes;
        $totalDuration = $duration + ($service->buffer_minutes ?? $this->bufferMinutes);

        // Get all qualified practitioners for this service at this branch
        $qualifiedPractitioners = $this->getQualifiedPractitioners($service, $branchId);

        if ($qualifiedPractitioners->isEmpty()) {
            return collect();
        }

        // Get time range for slots (considering service restrictions)
        $timeRange = $this->getTimeRangeForService($service, $date, $branchId);
        if (!$timeRange) {
            return collect();
        }

        // Generate all possible slots based on service duration
        $possibleSlots = $this->generateTimeSlots(
            $timeRange['start'],
            $timeRange['end'],
            $duration
        );

        // Build available slots with resources
        $availableSlots = collect();

        foreach ($possibleSlots as $slot) {
            $startTime = Carbon::parse($date->format('Y-m-d') . ' ' . $slot['start']);
            $endTime = Carbon::parse($date->format('Y-m-d') . ' ' . $slot['end']);

            // Skip slots in the past
            if ($startTime->isPast()) {
                continue;
            }

            // Check min advance hours
            if ($this->minAdvanceHours > 0) {
                $hoursUntilSlot = now()->diffInHours($startTime, false);
                if ($hoursUntilSlot < $this->minAdvanceHours) {
                    continue;
                }
            }

            // Find available practitioners for this slot
            $availablePractitioners = $this->getAvailablePractitioners(
                $serviceId,
                $branchId,
                $startTime,
                $totalDuration,
                $qualifiedPractitioners
            );

            if ($availablePractitioners->isEmpty()) {
                continue;
            }

            // Find available room (primary first, then backups)
            $room = $this->findAvailableRoom(
                $serviceId,
                $branchId,
                $startTime,
                $totalDuration
            );

            // Find available equipment if required
            $equipment = $this->findAvailableEquipment(
                $serviceId,
                $branchId,
                $startTime,
                $totalDuration
            );

            // Check if equipment is required but not available
            $requiredEquipment = $service->requiredEquipment()
                ->wherePivot('is_mandatory', true)
                ->get();

            if ($requiredEquipment->isNotEmpty() && !$equipment) {
                continue;
            }

            // Map StaffProfile to practitioner data with enhanced info
            $practitionerData = $this->enrichPractitionerData(
                $availablePractitioners,
                $branchId,
                $startTime,
                $totalDuration
            );

            // Check if room is primary for this service
            $isRoomPrimary = $room ? $this->isRoomPrimaryForService($service, $room->id) : false;

            $availableSlots->push([
                'start_time' => $slot['start'],
                'end_time' => $slot['end'],
                'date' => $date->format('Y-m-d'),
                'datetime_start' => $startTime->format('Y-m-d H:i:s'),
                'datetime_end' => $endTime->format('Y-m-d H:i:s'),
                'duration' => $duration,
                'available_practitioners' => $practitionerData,
                'room' => $room ? [
                    'id' => $room->id,
                    'name' => $room->name,
                    'is_primary' => $isRoomPrimary,
                ] : null,
                'room_id' => $room?->id,
                'room_name' => $room?->name,
                'equipment' => $equipment ? [
                    'id' => $equipment->id,
                    'name' => $equipment->name,
                ] : null,
                'equipment_id' => $equipment?->id,
                'equipment_name' => $equipment?->name,
            ]);
        }

        return $availableSlots;
    }

    /**
     * Get practitioners qualified for a service and available at a specific time.
     */
    /**
     * Get practitioners available at a specific time.
     * Now works with StaffProfile models instead of User models.
     */
    public function getAvailablePractitioners(
        string $serviceId,
        string $branchId,
        Carbon $datetime,
        int $duration,
        ?Collection $qualifiedPractitioners = null
    ): Collection {
        $service = Service::find($serviceId);
        if (!$service) {
            return collect();
        }

        // Practitioners are now StaffProfile models
        $practitioners = $qualifiedPractitioners ?? $this->getQualifiedPractitioners($service, $branchId);
        $endTime = $datetime->copy()->addMinutes($duration);
        $date = $datetime->copy()->startOfDay();
        $dayOfWeek = $datetime->dayOfWeek;
        $startTimeStr = $datetime->format('H:i');

        return $practitioners->filter(function ($staffProfile) use ($branchId, $date, $datetime, $endTime, $dayOfWeek, $startTimeStr, $duration) {
            // Check schedule assignment by staff_profile_id
            $assignment = PractitionerScheduleAssignment::query()
                ->forStaffProfile($staffProfile->id)
                ->forBranch($branchId)
                ->active()
                ->currentlyEffective()
                ->with('workSchedule')
                ->first();

            if (!$assignment || !$assignment->isAvailableAt($dayOfWeek, $startTimeStr)) {
                // Fall back to legacy PractitionerSchedule (uses user_id)
                $schedule = PractitionerSchedule::query()
                    ->forPractitioner($staffProfile->user_id)
                    ->forBranch($branchId)
                    ->forDay($dayOfWeek)
                    ->available()
                    ->first();

                if (!$schedule) {
                    return false;
                }

                // Check if within schedule hours
                if ($startTimeStr < $schedule->start_time || $endTime->format('H:i') > $schedule->end_time) {
                    return false;
                }

                // Check break
                if ($schedule->break_start && $schedule->break_end) {
                    if ($startTimeStr < $schedule->break_end && $endTime->format('H:i') > $schedule->break_start) {
                        return false;
                    }
                }
            }

            // Check time off (uses user_id)
            $hasTimeOff = PractitionerTimeOff::query()
                ->forPractitioner($staffProfile->user_id)
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

            if ($hasTimeOff) {
                if ($hasTimeOff->is_full_day) {
                    return false;
                }
                // Check partial day
                if ($hasTimeOff->start_time && $hasTimeOff->end_time) {
                    if ($startTimeStr < $hasTimeOff->end_time && $endTime->format('H:i') > $hasTimeOff->start_time) {
                        return false;
                    }
                }
            }

            // Check conflicting appointments (uses user_id for practitioner_id)
            $hasConflict = Appointment::query()
                ->forPractitioner($staffProfile->user_id)
                ->forDate($date)
                ->active()
                ->where(function ($q) use ($datetime, $endTime) {
                    $q->whereRaw("start_time < ?", [$endTime->format('H:i:s')])
                        ->whereRaw("COALESCE(end_time, start_time + (duration_minutes || ' minutes')::interval) > ?", [$datetime->format('H:i:s')]);
                })
                ->exists();

            return !$hasConflict;
        })->values();
    }

    /**
     * Find an available room for a service (tries primary first, then backups by priority).
     */
    public function findAvailableRoom(
        string $serviceId,
        string $branchId,
        Carbon $datetime,
        int $duration
    ): ?Room {
        $service = Service::find($serviceId);
        if (!$service) {
            return null;
        }

        $date = $datetime->copy()->startOfDay();
        $endTime = $datetime->copy()->addMinutes($duration);

        // Try primary room first
        $primaryRoom = $service->rooms()
            ->wherePivot('is_primary', true)
            ->where('branch_id', $branchId)
            ->active()
            ->bookable()
            ->first();

        if ($primaryRoom && $this->isRoomAvailable($primaryRoom->id, $date, $datetime, $endTime)) {
            return $primaryRoom;
        }

        // Try backup rooms in priority order
        $backupRooms = $service->rooms()
            ->wherePivot('is_primary', false)
            ->where('branch_id', $branchId)
            ->active()
            ->bookable()
            ->orderByPivot('priority')
            ->get();

        foreach ($backupRooms as $room) {
            if ($this->isRoomAvailable($room->id, $date, $datetime, $endTime)) {
                return $room;
            }
        }

        // If no service-specific room is available, try any bookable room at branch
        $anyRoom = Room::query()
            ->inBranch($branchId)
            ->active()
            ->bookable()
            ->get();

        foreach ($anyRoom as $room) {
            if ($this->isRoomAvailable($room->id, $date, $datetime, $endTime)) {
                return $room;
            }
        }

        return null;
    }

    /**
     * Find available equipment for a service.
     */
    public function findAvailableEquipment(
        string $serviceId,
        string $branchId,
        Carbon $datetime,
        int $duration
    ): ?Equipment {
        $service = Service::find($serviceId);
        if (!$service) {
            return null;
        }

        $date = $datetime->copy()->startOfDay();
        $endTime = $datetime->copy()->addMinutes($duration);

        // Get required equipment for this service at this branch
        $requiredEquipment = $service->requiredEquipment()
            ->wherePivot('is_mandatory', true)
            ->where('branch_id', $branchId)
            ->where('status', Equipment::STATUS_ACTIVE)
            ->get();

        if ($requiredEquipment->isEmpty()) {
            return null;
        }

        $equipment = $requiredEquipment;

        foreach ($equipment as $item) {
            if ($this->isEquipmentAvailable($item->id, $date, $datetime, $endTime)) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Check if a specific slot is available for booking.
     */
    public function isSlotAvailable(
        string $serviceId,
        string $branchId,
        string $practitionerId,
        Carbon $datetime,
        int $duration,
        ?string $excludeAppointmentId = null
    ): bool {
        $date = $datetime->copy()->startOfDay();
        $endTime = $datetime->copy()->addMinutes($duration);
        $dayOfWeek = $datetime->dayOfWeek;
        $startTimeStr = $datetime->format('H:i');

        // Check practitioner schedule
        $assignment = PractitionerScheduleAssignment::query()
            ->forPractitioner($practitionerId)
            ->forBranch($branchId)
            ->active()
            ->currentlyEffective()
            ->with('workSchedule')
            ->first();

        if ($assignment) {
            if (!$assignment->isAvailableAt($dayOfWeek, $startTimeStr)) {
                return false;
            }
        } else {
            // Fall back to legacy schedule
            $schedule = PractitionerSchedule::query()
                ->forPractitioner($practitionerId)
                ->forBranch($branchId)
                ->forDay($dayOfWeek)
                ->available()
                ->first();

            if (!$schedule) {
                return false;
            }

            if ($startTimeStr < $schedule->start_time || $endTime->format('H:i') > $schedule->end_time) {
                return false;
            }

            if ($schedule->break_start && $schedule->break_end) {
                if ($startTimeStr < $schedule->break_end && $endTime->format('H:i') > $schedule->break_start) {
                    return false;
                }
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
            ->where(function ($q) use ($datetime, $endTime) {
                $q->whereRaw("start_time < ?", [$endTime->format('H:i:s')])
                    ->whereRaw("COALESCE(end_time, start_time + (duration_minutes || ' minutes')::interval) > ?", [$datetime->format('H:i:s')]);
            });

        if ($excludeAppointmentId) {
            $conflictQuery->where('id', '!=', $excludeAppointmentId);
        }

        return !$conflictQuery->exists();
    }

    /**
     * Get available slots for multiple services (multi-service booking support).
     */
    public function generateSlotsForMultipleServices(
        array $serviceIds,
        string $branchId,
        Carbon $date,
        array $durationOverrides = []
    ): array {
        $result = [];

        foreach ($serviceIds as $serviceId) {
            $duration = $durationOverrides[$serviceId] ?? null;
            $slots = $this->generateAvailableSlots($serviceId, $branchId, $date, $duration);
            $result[$serviceId] = $slots;
        }

        return $result;
    }

    /**
     * Find next available slot for a service.
     */
    public function findNextAvailableSlot(
        string $serviceId,
        string $branchId,
        ?Carbon $fromDate = null,
        int $maxDaysAhead = 30
    ): ?array {
        $date = $fromDate ?? today();
        $endDate = $date->copy()->addDays($maxDaysAhead);

        while ($date->lte($endDate)) {
            $slots = $this->generateAvailableSlots($serviceId, $branchId, $date);

            if ($slots->isNotEmpty()) {
                return [
                    'date' => $date->format('Y-m-d'),
                    'slot' => $slots->first(),
                ];
            }

            $date->addDay();
        }

        return null;
    }

    /**
     * Validate multiple service bookings don't conflict.
     */
    public function validateMultipleBookings(array $bookingItems): array
    {
        $conflicts = [];

        // Group by date and check for resource conflicts
        $byDate = collect($bookingItems)->groupBy('date');

        foreach ($byDate as $date => $items) {
            $practitionerSlots = [];
            $roomSlots = [];
            $equipmentSlots = [];

            foreach ($items as $index => $item) {
                $start = $item['start_time'];
                $end = $item['end_time'];

                // Check practitioner conflict
                if (!empty($item['practitioner_id'])) {
                    $key = $item['practitioner_id'];
                    if (isset($practitionerSlots[$key])) {
                        foreach ($practitionerSlots[$key] as $existingSlot) {
                            if ($this->timeSlotsOverlap($start, $end, $existingSlot['start'], $existingSlot['end'])) {
                                $conflicts[] = "Practitioner conflict at {$start} on {$date}";
                            }
                        }
                    }
                    $practitionerSlots[$key][] = ['start' => $start, 'end' => $end];
                }

                // Check room conflict
                if (!empty($item['room_id'])) {
                    $key = $item['room_id'];
                    if (isset($roomSlots[$key])) {
                        foreach ($roomSlots[$key] as $existingSlot) {
                            if ($this->timeSlotsOverlap($start, $end, $existingSlot['start'], $existingSlot['end'])) {
                                $conflicts[] = "Room conflict at {$start} on {$date}";
                            }
                        }
                    }
                    $roomSlots[$key][] = ['start' => $start, 'end' => $end];
                }

                // Check equipment conflict
                if (!empty($item['equipment_id'])) {
                    $key = $item['equipment_id'];
                    if (isset($equipmentSlots[$key])) {
                        foreach ($equipmentSlots[$key] as $existingSlot) {
                            if ($this->timeSlotsOverlap($start, $end, $existingSlot['start'], $existingSlot['end'])) {
                                $conflicts[] = "Equipment conflict at {$start} on {$date}";
                            }
                        }
                    }
                    $equipmentSlots[$key][] = ['start' => $start, 'end' => $end];
                }
            }
        }

        return $conflicts;
    }

    // Protected helper methods

    protected function getQualifiedPractitioners(Service $service, string $branchId): Collection
    {
        // Get qualified staff profiles for this service
        // Now qualifiedStaff() returns StaffProfile models
        return $service->qualifiedStaff()
            ->with('user')
            ->where('is_active', true)
            ->where(function ($query) use ($branchId) {
                // Staff profile is at this branch
                $query->where('branch_id', $branchId)
                // Or has a schedule assignment at this branch
                ->orWhereExists(function ($subQuery) use ($branchId) {
                    $subQuery->select(DB::raw(1))
                        ->from('practitioner_schedule_assignments')
                        ->whereColumn('practitioner_schedule_assignments.staff_profile_id', 'staff_profiles.id')
                        ->where('practitioner_schedule_assignments.branch_id', $branchId)
                        ->where('practitioner_schedule_assignments.is_active', true)
                        ->whereNull('practitioner_schedule_assignments.deleted_at')
                        ->where(function ($q) {
                            $q->whereNull('practitioner_schedule_assignments.effective_from')
                                ->orWhere('practitioner_schedule_assignments.effective_from', '<=', now());
                        })
                        ->where(function ($q) {
                            $q->whereNull('practitioner_schedule_assignments.effective_until')
                                ->orWhere('practitioner_schedule_assignments.effective_until', '>=', now());
                        });
                });
            })
            ->get();
    }

    protected function isDateAllowedForService(Service $service, Carbon $date): bool
    {
        $restrictions = $service->time_slot_restrictions;
        if (empty($restrictions)) {
            return true;
        }

        // Check allowed days (empty array means all days allowed)
        if (!empty($restrictions['allowed_days']) && !in_array($date->dayOfWeek, $restrictions['allowed_days'])) {
            return false;
        }

        // Check blackout dates
        if (isset($restrictions['blackout_dates']) && in_array($date->format('Y-m-d'), $restrictions['blackout_dates'])) {
            return false;
        }

        // Check max advance days
        if (isset($restrictions['max_advance_days'])) {
            $daysUntil = now()->startOfDay()->diffInDays($date, false);
            if ($daysUntil > $restrictions['max_advance_days']) {
                return false;
            }
        }

        return true;
    }

    protected function getTimeRangeForService(Service $service, Carbon $date, string $branchId): ?array
    {
        $restrictions = $service->time_slot_restrictions ?? [];

        $startTime = $restrictions['allowed_time_start'] ?? config('booking.default_start_time', '09:00');
        $endTime = $restrictions['allowed_time_end'] ?? config('booking.default_end_time', '21:00');

        return [
            'start' => $startTime,
            'end' => $endTime,
        ];
    }

    protected function generateTimeSlots(string $startTime, string $endTime, int $durationMinutes): array
    {
        $slots = [];
        $start = Carbon::createFromFormat('H:i', $startTime);
        $end = Carbon::createFromFormat('H:i', $endTime);

        $current = $start->copy();

        while ($current->copy()->addMinutes($durationMinutes)->lte($end)) {
            $slots[] = [
                'start' => $current->format('H:i'),
                'end' => $current->copy()->addMinutes($durationMinutes)->format('H:i'),
            ];
            $current->addMinutes($durationMinutes);
        }

        return $slots;
    }

    protected function isRoomAvailable(string $roomId, Carbon $date, Carbon $start, Carbon $end): bool
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

    protected function isEquipmentAvailable(string $equipmentId, Carbon $date, Carbon $start, Carbon $end): bool
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

    protected function timeSlotsOverlap(string $start1, string $end1, string $start2, string $end2): bool
    {
        return $start1 < $end2 && $end1 > $start2;
    }

    /**
     * Enrich practitioner data with status, recommendations, and next appointment info.
     */
    protected function enrichPractitionerData(
        Collection $practitioners,
        string $branchId,
        Carbon $slotStart,
        int $duration
    ): array {
        $slotEnd = $slotStart->copy()->addMinutes($duration);
        $date = $slotStart->copy()->startOfDay();
        $enrichedData = [];

        // Get recommended practitioner (first one - could be enhanced with more logic)
        $recommendedIndex = 0;

        foreach ($practitioners as $index => $staffProfile) {
            // Skip staff profiles without valid user or user_id
            if (!$staffProfile->user_id || !$staffProfile->user) {
                continue;
            }
            $practitionerId = $staffProfile->user_id;

            // Get next appointment for this practitioner after slot end
            $nextAppointment = Appointment::query()
                ->forPractitioner($practitionerId)
                ->forDate($date)
                ->active()
                ->whereRaw("start_time >= ?", [$slotEnd->format('H:i:s')])
                ->orderBy('start_time')
                ->first(['id', 'start_time', 'service_id']);

            // Determine status
            $status = 'available';
            $minutesUntilNext = null;

            if ($nextAppointment) {
                $nextStartTime = $date->copy()->setTimeFrom($nextAppointment->start_time);
                $minutesUntilNext = $slotEnd->diffInMinutes($nextStartTime, false);

                // If next appointment is within 30 minutes of slot end, mark as busy_soon
                if ($minutesUntilNext <= 30 && $minutesUntilNext > 0) {
                    $status = 'busy_soon';
                }
            }

            // Check if practitioner is preferred for this service (future enhancement)
            // For now, first available is recommended
            $isRecommended = ($index === $recommendedIndex);

            $enrichedData[] = [
                'id' => $practitionerId,
                'staff_profile_id' => $staffProfile->id,
                'name' => $staffProfile->user?->name ?? $staffProfile->user?->full_name ?? 'Unknown',
                'avatar' => $staffProfile->user?->avatar_url ?? null,
                'is_recommended' => $isRecommended,
                'status' => $status,
                'next_appointment' => $nextAppointment ? [
                    'time' => $nextAppointment->start_time,
                    'minutes_until' => $minutesUntilNext,
                ] : null,
            ];
        }

        return $enrichedData;
    }

    /**
     * Check if a room is the primary room for a service.
     */
    protected function isRoomPrimaryForService(Service $service, string $roomId): bool
    {
        return $service->rooms()
            ->wherePivot('is_primary', true)
            ->where('rooms.id', $roomId)
            ->exists();
    }

    /**
     * Check if a date is blocked by blackout dates.
     */
    protected function isDateBlocked(Carbon $date, string $branchId, bool $isOnlineBooking = false): bool
    {
        return BookingBlackoutDate::isDateBlocked($date, $branchId, $isOnlineBooking, !$isOnlineBooking);
    }

    /**
     * Get the booking rule evaluator instance.
     */
    public function getRuleEvaluator(
        string $branchId,
        ?string $serviceId = null,
        bool $isOnlineBooking = false
    ): BookingRuleEvaluator {
        return app(BookingRuleEvaluator::class)
            ->forContext($branchId, $serviceId, $isOnlineBooking);
    }

    /**
     * Generate slots with rule evaluation.
     * This is an enhanced version that applies booking rules.
     */
    public function generateAvailableSlotsWithRules(
        string $serviceId,
        string $branchId,
        Carbon $date,
        ?int $durationOverride = null,
        bool $isOnlineBooking = false
    ): Collection {
        // Get base slots
        $slots = $this->generateAvailableSlots($serviceId, $branchId, $date, $durationOverride);

        if ($slots->isEmpty()) {
            return $slots;
        }

        // Apply rule-based filtering
        $evaluator = $this->getRuleEvaluator($branchId, $serviceId, $isOnlineBooking);

        // Get time restrictions from rules
        $timeRestrictions = $evaluator->getEffectiveTimeRestrictions($date);

        // Filter slots based on rules
        return $slots->filter(function ($slot) use ($date, $evaluator, $timeRestrictions) {
            $startTime = $slot['start_time'] ?? null;
            if (!$startTime) {
                return true;
            }

            // Apply time restrictions from rules
            if ($timeRestrictions) {
                $slotTime = Carbon::parse($startTime);

                if ($timeRestrictions['start']) {
                    $restrictStart = Carbon::parse($timeRestrictions['start']);
                    if ($slotTime->lt($restrictStart)) {
                        return false;
                    }
                }

                if ($timeRestrictions['end']) {
                    $restrictEnd = Carbon::parse($timeRestrictions['end']);
                    if ($slotTime->gte($restrictEnd)) {
                        return false;
                    }
                }
            }

            // Check if slot is blocked by specific rules
            $blocked = $evaluator->isSlotBlocked($date, $startTime);
            return $blocked === false;
        })->values();
    }
}
