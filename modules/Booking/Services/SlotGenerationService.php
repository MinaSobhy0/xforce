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
    protected ?BookingRuleEvaluator $ruleEvaluator = null;

    // Pre-loaded data for batch optimization
    protected Collection $preloadedAppointments;
    protected Collection $preloadedScheduleAssignments;
    protected Collection $preloadedLegacySchedules;
    protected Collection $preloadedTimeOffs;
    protected Collection $preloadedRoomBookings;
    protected Collection $preloadedEquipmentBookings;
    protected array $preloadedPractitionerAppointmentCounts = [];
    protected ?Carbon $preloadedDate = null;
    protected ?string $preloadedBranchId = null;

    public function __construct()
    {
        $this->preloadedAppointments = collect();
        $this->preloadedScheduleAssignments = collect();
        $this->preloadedLegacySchedules = collect();
        $this->preloadedTimeOffs = collect();
        $this->preloadedRoomBookings = collect();
        $this->preloadedEquipmentBookings = collect();
        $this->preloadedPractitionerAppointmentCounts = [];
    }

    /**
     * Pre-load all necessary data for slot generation to avoid N+1 queries.
     * This dramatically improves performance for multi-tenant environments.
     */
    protected function preloadDataForDate(
        Carbon $date,
        string $branchId,
        Service $service,
        Collection $qualifiedPractitioners
    ): void {
        // Skip if already preloaded for this date and branch
        if ($this->preloadedDate?->isSameDay($date) && $this->preloadedBranchId === $branchId) {
            return;
        }

        $this->preloadedDate = $date->copy();
        $this->preloadedBranchId = $branchId;

        $practitionerUserIds = $qualifiedPractitioners->pluck('user_id')->filter()->toArray();
        $staffProfileIds = $qualifiedPractitioners->pluck('id')->filter()->toArray();
        $dayOfWeek = $date->dayOfWeek;

        // 1. Pre-load ALL appointments for this date and branch
        $this->preloadedAppointments = Appointment::query()
            ->forDate($date)
            ->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)
                    ->orWhereNull('branch_id');
            })
            ->active()
            ->get(['id', 'practitioner_id', 'room_id', 'equipment_id', 'start_time', 'end_time', 'duration_minutes', 'service_id']);

        // 2. Pre-load schedule assignments for all qualified practitioners
        // Use forBranch scope which also checks WorkSchedule's branch_id for NULL assignment branch_id
        $scheduleAssignments = PractitionerScheduleAssignment::query()
            ->whereIn('staff_profile_id', $staffProfileIds)
            ->forBranch($branchId)
            ->active()
            ->currentlyEffective()
            ->with('workSchedule')
            ->get();

        // Key by staff_profile_id as string for consistent lookup
        $this->preloadedScheduleAssignments = $scheduleAssignments->keyBy(fn($a) => (string) $a->staff_profile_id);

        // 3. Pre-load legacy schedules for all practitioners
        $legacySchedules = PractitionerSchedule::query()
            ->whereIn('user_id', $practitionerUserIds)
            ->where('branch_id', $branchId)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_available', true)
            ->get();

        // Key by user_id as string for consistent lookup
        $this->preloadedLegacySchedules = $legacySchedules->keyBy(fn($s) => (string) $s->user_id);

        // 4. Pre-load time-off records for all practitioners (group by string user_id)
        $timeOffs = PractitionerTimeOff::query()
            ->whereIn('user_id', $practitionerUserIds)
            ->where('status', 'approved')
            ->where(function ($q) use ($branchId) {
                $q->whereNull('branch_id')
                    ->orWhere('branch_id', $branchId);
            })
            ->where('start_date', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', $date);
            })
            ->get();

        $this->preloadedTimeOffs = $timeOffs->groupBy(fn($t) => (string) $t->user_id);

        // 5. Pre-load room bookings (group all appointments by room_id)
        // This covers both service-specific rooms and fallback to any branch room
        $this->preloadedRoomBookings = $this->preloadedAppointments
            ->filter(fn($a) => $a->room_id !== null)
            ->groupBy('room_id');

        // 6. Pre-load equipment bookings (group all appointments by equipment_id)
        $this->preloadedEquipmentBookings = $this->preloadedAppointments
            ->filter(fn($a) => $a->equipment_id !== null)
            ->groupBy('equipment_id');

        // 7. Pre-load appointment counts per practitioner (for capacity checks, use string keys)
        $this->preloadedPractitionerAppointmentCounts = $this->preloadedAppointments
            ->filter(fn($a) => $a->practitioner_id !== null)
            ->groupBy(fn($a) => (string) $a->practitioner_id)
            ->map(fn($appointments) => $appointments->count())
            ->toArray();
    }

    /**
     * Clear preloaded data (useful for testing or when context changes).
     */
    public function clearPreloadedData(): void
    {
        $this->preloadedAppointments = collect();
        $this->preloadedScheduleAssignments = collect();
        $this->preloadedLegacySchedules = collect();
        $this->preloadedTimeOffs = collect();
        $this->preloadedRoomBookings = collect();
        $this->preloadedEquipmentBookings = collect();
        $this->preloadedPractitionerAppointmentCounts = [];
        $this->preloadedDate = null;
        $this->preloadedBranchId = null;
    }

    /**
     * Generate available time slots for a service at a branch on a specific date.
     * All settings are derived from booking rules.
     */
    public function generateAvailableSlots(
        string $serviceId,
        string $branchId,
        Carbon $date,
        ?int $durationOverride = null,
        bool $isOnlineBooking = false
    ): Collection {
        $service = Service::with([
            'qualifiedStaff',
            'rooms',
            'requiredEquipment',
            'category.qualifiedStaff',
            'category.rooms',
            'category.requiredEquipment',
        ])->find($serviceId);

        if (!$service) {
            return collect();
        }

        // Initialize rule evaluator for this context
        $this->ruleEvaluator = $this->getRuleEvaluator($branchId, $serviceId, $isOnlineBooking);

        // Check if online booking is enabled (for online requests)
        if ($isOnlineBooking && !$this->ruleEvaluator->isOnlineBookingEnabled()) {
            return collect();
        }

        // Check if date is within allowed advance booking range
        $advanceCheck = $this->ruleEvaluator->isDateWithinAdvanceRange($date);
        if ($advanceCheck !== true) {
            return collect();
        }

        // Check if date is blocked by blackout dates
        $dateBlocked = $this->ruleEvaluator->isDateBlocked($date);
        if ($dateBlocked !== false) {
            return collect();
        }

        // Check service time restrictions (service-specific blackouts/allowed days)
        if (!$this->isDateAllowedForService($service, $date)) {
            return collect();
        }

        // Check capacity limits for the date
        $capacityLimit = $this->ruleEvaluator->checkCapacityLimits($date);
        if ($capacityLimit && $capacityLimit['exceeded']) {
            return collect();
        }

        // Get effective duration from rules or service
        $ruleDuration = $this->ruleEvaluator->getEffectiveSlotDuration($service->duration_minutes);
        $duration = $durationOverride ?? $ruleDuration;

        // Get effective buffer from rules
        $bufferMinutes = $this->ruleEvaluator->getEffectiveBuffer($date);
        $serviceBuffer = $service->buffer_minutes ?? 0;
        $totalBuffer = max($bufferMinutes, $serviceBuffer);
        $totalDuration = $duration + $totalBuffer;

        // Get effective slot interval from rules
        $slotInterval = $this->ruleEvaluator->getEffectiveSlotInterval($duration);

        // Get all qualified practitioners for this service at this branch
        $qualifiedPractitioners = $this->getQualifiedPractitioners($service, $branchId);

        if ($qualifiedPractitioners->isEmpty()) {
            return collect();
        }

        // Pre-load all data for batch optimization (reduces N+1 queries)
        $this->preloadDataForDate($date, $branchId, $service, $qualifiedPractitioners);

        // Get working hours from rules (now from Branch settings)
        $workingHours = $this->ruleEvaluator->getEffectiveWorkingHours($date);

        // Check if branch is closed on this day
        if (!empty($workingHours['is_closed']) || empty($workingHours['start']) || empty($workingHours['end'])) {
            return collect();
        }

        // Get time restrictions from rules (may override working hours)
        $timeRestrictions = $this->ruleEvaluator->getEffectiveTimeRestrictions($date);

        // Get online hours restrictions if applicable
        $onlineHours = $isOnlineBooking ? $this->ruleEvaluator->getOnlineBookingHours($date) : null;

        // Determine effective time range
        $startTime = $workingHours['start'];
        $endTime = $workingHours['end'];

        // Apply time restrictions
        if ($timeRestrictions) {
            if ($timeRestrictions['start']) {
                $startTime = max($startTime, $timeRestrictions['start']);
            }
            if ($timeRestrictions['end']) {
                $endTime = min($endTime, $timeRestrictions['end']);
            }
        }

        // Apply online hours restrictions
        if ($onlineHours) {
            if ($onlineHours['start']) {
                $startTime = max($startTime, $onlineHours['start']);
            }
            if ($onlineHours['end']) {
                $endTime = min($endTime, $onlineHours['end']);
            }
        }

        // Apply service-specific time restrictions if any
        $serviceTimeRange = $this->getTimeRangeForService($service, $date, $branchId);
        if ($serviceTimeRange) {
            $startTime = max($startTime, $serviceTimeRange['start']);
            $endTime = min($endTime, $serviceTimeRange['end']);
        }

        // Validate time range
        if ($startTime >= $endTime) {
            return collect();
        }

        // Get break times from rules
        $breakTimes = $this->ruleEvaluator->getBreakTimes($date);

        // Get min advance hours from rules
        $minAdvanceHours = $this->ruleEvaluator->getMinAdvanceHours();

        // Generate all possible slots based on interval
        $possibleSlots = $this->generateTimeSlots($startTime, $endTime, $duration, $slotInterval);

        // Build available slots with resources
        $availableSlots = collect();

        foreach ($possibleSlots as $slot) {
            $slotStartTime = Carbon::parse($date->format('Y-m-d') . ' ' . $slot['start']);
            $slotEndTime = Carbon::parse($date->format('Y-m-d') . ' ' . $slot['end']);

            // Skip slots in the past
            if ($slotStartTime->isPast()) {
                continue;
            }

            // Check min advance hours from rules
            if ($minAdvanceHours > 0) {
                $hoursUntilSlot = now()->diffInHours($slotStartTime, false);
                if ($hoursUntilSlot < $minAdvanceHours) {
                    continue;
                }
            }

            // Check if slot falls within a break time
            $inBreak = false;
            foreach ($breakTimes as $break) {
                if ($slot['start'] >= $break['start'] && $slot['start'] < $break['end']) {
                    $inBreak = true;
                    break;
                }
            }
            if ($inBreak) {
                continue;
            }

            // Check if slot is blocked by rules
            $slotBlocked = $this->ruleEvaluator->isSlotBlocked($date, $slot['start']);
            if ($slotBlocked !== false) {
                continue;
            }

            // Check hourly capacity
            $hourlyCapacity = $this->ruleEvaluator->checkCapacityLimits($date, null, $slot['start']);
            if ($hourlyCapacity && $hourlyCapacity['exceeded']) {
                continue;
            }

            // Find available practitioners for this slot
            $availablePractitioners = $this->getAvailablePractitioners(
                $serviceId,
                $branchId,
                $slotStartTime,
                $totalDuration,
                $qualifiedPractitioners
            );

            if ($availablePractitioners->isEmpty()) {
                continue;
            }

            // Filter practitioners by capacity limits (using pre-loaded counts)
            $maxPerDoctor = $this->ruleEvaluator->getMaxPerDoctorDaily();
            if ($maxPerDoctor) {
                $availablePractitioners = $availablePractitioners->filter(function ($practitioner) use ($maxPerDoctor) {
                    $practitionerId = (string) $practitioner->user_id;
                    $currentCount = $this->preloadedPractitionerAppointmentCounts[$practitionerId] ?? 0;
                    return $currentCount < $maxPerDoctor;
                });
            }

            if ($availablePractitioners->isEmpty()) {
                continue;
            }

            // Find available room (primary first, then backups)
            $room = $this->findAvailableRoom(
                $serviceId,
                $branchId,
                $slotStartTime,
                $totalDuration,
                $service
            );

            // Find available equipment if required
            $equipment = $this->findAvailableEquipment(
                $serviceId,
                $branchId,
                $slotStartTime,
                $totalDuration,
                $service
            );

            // Check if equipment is required but not available (cascades: Service → ServiceCategory)
            $requiredEquipment = $service->getEffectiveEquipment()
                ->where('pivot.is_mandatory', true);

            if ($requiredEquipment->isNotEmpty() && !$equipment) {
                continue;
            }

            // Map StaffProfile to practitioner data with enhanced info
            $practitionerData = $this->enrichPractitionerData(
                $availablePractitioners,
                $branchId,
                $slotStartTime,
                $totalDuration
            );

            // Check if room is primary for this service
            $isRoomPrimary = $room ? $this->isRoomPrimaryForService($service, $room->id) : false;

            $availableSlots->push([
                'start_time' => $slot['start'],
                'end_time' => $slot['end'],
                'date' => $date->format('Y-m-d'),
                'datetime_start' => $slotStartTime->format('Y-m-d H:i:s'),
                'datetime_end' => $slotEndTime->format('Y-m-d H:i:s'),
                'duration' => $duration,
                'buffer' => $totalBuffer,
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
     * Get practitioners available at a specific time.
     * Now works with StaffProfile models instead of User models.
     * Optimized to use pre-loaded data when available.
     */
    public function getAvailablePractitioners(
        string $serviceId,
        string $branchId,
        Carbon $datetime,
        int $duration,
        ?Collection $qualifiedPractitioners = null
    ): Collection {
        // Use preloaded data if available, otherwise load fresh
        $usePreloaded = $this->preloadedDate?->isSameDay($datetime) && $this->preloadedBranchId === $branchId;

        if (!$qualifiedPractitioners) {
            $service = Service::find($serviceId);
            if (!$service) {
                return collect();
            }
            $qualifiedPractitioners = $this->getQualifiedPractitioners($service, $branchId);
        }

        // Practitioners are now StaffProfile models
        $practitioners = $qualifiedPractitioners;
        $endTime = $datetime->copy()->addMinutes($duration);
        $date = $datetime->copy()->startOfDay();
        $dayOfWeek = $datetime->dayOfWeek;
        $startTimeStr = $datetime->format('H:i');
        $endTimeStr = $endTime->format('H:i');

        // Get configuration flags for doctor availability checks
        $practitionerReq = $this->ruleEvaluator?->getPractitionerRequirements() ?? [];
        $checkSchedule = $practitionerReq['check_schedule'] ?? true;
        $checkTimeoff = $practitionerReq['check_timeoff'] ?? true;
        $allowOverlap = $practitionerReq['allow_overlap'] ?? false;

        return $practitioners->filter(function ($staffProfile) use ($branchId, $date, $datetime, $endTime, $dayOfWeek, $startTimeStr, $endTimeStr, $duration, $checkSchedule, $checkTimeoff, $allowOverlap, $usePreloaded) {
            // Check schedule assignment by staff_profile_id (only if check_doctor_schedule is enabled)
            if ($checkSchedule) {
                // Use preloaded data or query (cast to string for consistent key lookup)
                $assignment = $usePreloaded
                    ? ($this->preloadedScheduleAssignments->get((string) $staffProfile->id))
                    : PractitionerScheduleAssignment::query()
                        ->forStaffProfile($staffProfile->id)
                        ->forBranch($branchId)
                        ->active()
                        ->currentlyEffective()
                        ->with('workSchedule')
                        ->first();

                $assignmentAvailable = $assignment && $assignment->isAvailableAt($dayOfWeek, $startTimeStr);

                if (!$assignmentAvailable) {
                    // Fall back to legacy PractitionerSchedule (uses user_id, cast to string for key lookup)
                    $schedule = $usePreloaded
                        ? ($this->preloadedLegacySchedules->get((string) $staffProfile->user_id))
                        : PractitionerSchedule::query()
                            ->forPractitioner($staffProfile->user_id)
                            ->forBranch($branchId)
                            ->forDay($dayOfWeek)
                            ->available()
                            ->first();

                    if (!$schedule) {
                        return false;
                    }

                    // Check if within schedule hours
                    if ($startTimeStr < $schedule->start_time || $endTimeStr > $schedule->end_time) {
                        return false;
                    }

                    // Check break
                    if ($schedule->break_start && $schedule->break_end) {
                        if ($startTimeStr < $schedule->break_end && $endTimeStr > $schedule->break_start) {
                            return false;
                        }
                    }
                }
            }

            // Check time off (only if check_doctor_timeoff is enabled)
            if ($checkTimeoff) {
                // Use preloaded data or query (cast to string for consistent key lookup)
                $timeOffs = $usePreloaded
                    ? ($this->preloadedTimeOffs->get((string) $staffProfile->user_id) ?? collect())
                    : PractitionerTimeOff::query()
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
                        ->get();

                foreach ($timeOffs as $timeOff) {
                    if ($timeOff->is_full_day) {
                        return false;
                    }
                    // Check partial day
                    if ($timeOff->start_time && $timeOff->end_time) {
                        if ($startTimeStr < $timeOff->end_time && $endTimeStr > $timeOff->start_time) {
                            return false;
                        }
                    }
                }
            }

            // Check conflicting appointments (skip if allow_doctor_overlap is enabled)
            if (!$allowOverlap) {
                $hasConflict = $this->hasAppointmentConflict(
                    $staffProfile->user_id,
                    'practitioner_id',
                    $datetime,
                    $endTime,
                    $usePreloaded
                );

                if ($hasConflict) {
                    return false;
                }
            }

            return true;
        })->values();
    }

    /**
     * Check if there's an appointment conflict using preloaded data or database query.
     */
    protected function hasAppointmentConflict(
        string $resourceId,
        string $resourceField,
        Carbon $datetime,
        Carbon $endTime,
        bool $usePreloaded
    ): bool {
        $startTimeStr = $datetime->format('H:i:s');
        $endTimeStr = $endTime->format('H:i:s');

        if ($usePreloaded) {
            // Filter preloaded appointments in memory
            return $this->preloadedAppointments
                ->where($resourceField, $resourceId)
                ->filter(function ($appointment) use ($startTimeStr, $endTimeStr) {
                    $apptStart = $appointment->start_time;
                    $apptEnd = $appointment->end_time
                        ?? Carbon::createFromFormat('H:i:s', $apptStart)
                            ->addMinutes($appointment->duration_minutes)
                            ->format('H:i:s');

                    // Check overlap: start1 < end2 AND end1 > start2
                    return $apptStart < $endTimeStr && $apptEnd > $startTimeStr;
                })
                ->isNotEmpty();
        }

        // Fallback to database query
        return Appointment::query()
            ->where($resourceField, $resourceId)
            ->forDate($datetime->copy()->startOfDay())
            ->active()
            ->where(function ($q) use ($endTimeStr, $startTimeStr) {
                $q->whereRaw("start_time < ?", [$endTimeStr])
                    ->whereRaw("COALESCE(end_time, start_time + (duration_minutes || ' minutes')::interval) > ?", [$startTimeStr]);
            })
            ->exists();
    }

    /**
     * Find an available room for a service (tries primary first, then backups by priority).
     * Optimized to use pre-loaded data when available.
     */
    public function findAvailableRoom(
        string $serviceId,
        string $branchId,
        Carbon $datetime,
        int $duration,
        ?Service $service = null
    ): ?Room {
        if (!$service) {
            $service = Service::with('category.rooms')->find($serviceId);
            if (!$service) {
                return null;
            }
        }

        $date = $datetime->copy()->startOfDay();
        $endTime = $datetime->copy()->addMinutes($duration);
        $usePreloaded = $this->preloadedDate?->isSameDay($date) && $this->preloadedBranchId === $branchId;

        // Check for room preference rules
        $roomPreference = $this->ruleEvaluator?->getRoomPreference();
        $useServiceRooms = !$roomPreference || $roomPreference['source'] === 'service';
        $strictRoom = $roomPreference['strict'] ?? false;

        if ($useServiceRooms) {
            // Use getEffectiveRooms() which cascades: Service → ServiceCategory
            $effectiveRooms = $service->getEffectiveRooms();

            // Try primary room first
            $primaryRoom = $effectiveRooms
                ->where('pivot.is_primary', true)
                ->where('branch_id', $branchId)
                ->filter(fn($r) => $r->is_active && $r->is_bookable)
                ->first();

            if ($primaryRoom && $this->isRoomAvailableOptimized($primaryRoom->id, $datetime, $endTime, $usePreloaded)) {
                return $primaryRoom;
            }

            // Try backup rooms in priority order
            $backupRooms = $effectiveRooms
                ->where('pivot.is_primary', false)
                ->where('branch_id', $branchId)
                ->filter(fn($r) => $r->is_active && $r->is_bookable)
                ->sortBy('pivot.priority');

            foreach ($backupRooms as $room) {
                if ($this->isRoomAvailableOptimized($room->id, $datetime, $endTime, $usePreloaded)) {
                    return $room;
                }
            }
        } else {
            // Use manually specified rooms from rule
            $preferredRoomIds = $roomPreference['preferred_rooms'] ?? [];
            if (!empty($preferredRoomIds)) {
                $preferredRooms = Room::whereIn('id', $preferredRoomIds)
                    ->where('rooms.branch_id', $branchId)
                    ->active()
                    ->bookable()
                    ->get();

                foreach ($preferredRooms as $room) {
                    if ($this->isRoomAvailableOptimized($room->id, $datetime, $endTime, $usePreloaded)) {
                        return $room;
                    }
                }
            }
        }

        // If strict mode, don't fall back to any room
        if ($strictRoom) {
            return null;
        }

        // If no service-specific room is available, try any bookable room at branch
        $anyRoom = Room::query()
            ->inBranch($branchId)
            ->active()
            ->bookable()
            ->get();

        foreach ($anyRoom as $room) {
            if ($this->isRoomAvailableOptimized($room->id, $datetime, $endTime, $usePreloaded)) {
                return $room;
            }
        }

        return null;
    }

    /**
     * Find available equipment for a service.
     * Optimized to use pre-loaded data when available.
     */
    public function findAvailableEquipment(
        string $serviceId,
        string $branchId,
        Carbon $datetime,
        int $duration,
        ?Service $service = null
    ): ?Equipment {
        if (!$service) {
            $service = Service::with('category.requiredEquipment')->find($serviceId);
            if (!$service) {
                return null;
            }
        }

        $date = $datetime->copy()->startOfDay();
        $endTime = $datetime->copy()->addMinutes($duration);
        $usePreloaded = $this->preloadedDate?->isSameDay($date) && $this->preloadedBranchId === $branchId;

        // Check for equipment requirement rules
        $equipmentReq = $this->ruleEvaluator?->getEquipmentRequirements();
        $useServiceEquipment = !$equipmentReq || $equipmentReq['source'] === 'service';

        if ($useServiceEquipment) {
            // Use getEffectiveEquipment() which cascades: Service → ServiceCategory
            $effectiveEquipment = $service->getEffectiveEquipment();

            // Filter for mandatory equipment at this branch
            $requiredEquipment = $effectiveEquipment
                ->where('pivot.is_mandatory', true)
                ->where('branch_id', $branchId)
                ->filter(fn($e) => $e->status === Equipment::STATUS_ACTIVE);

            if ($requiredEquipment->isEmpty()) {
                return null;
            }

            foreach ($requiredEquipment as $item) {
                if ($this->isEquipmentAvailableOptimized($item->id, $datetime, $endTime, $usePreloaded)) {
                    return $item;
                }
            }
        } else {
            // Use manually specified equipment from rule
            $requiredEquipmentIds = $equipmentReq['required_equipment'] ?? [];
            if (!empty($requiredEquipmentIds)) {
                $equipment = Equipment::whereIn('id', $requiredEquipmentIds)
                    ->where('equipment.branch_id', $branchId)
                    ->where('equipment.status', Equipment::STATUS_ACTIVE)
                    ->get();

                foreach ($equipment as $item) {
                    if ($this->isEquipmentAvailableOptimized($item->id, $datetime, $endTime, $usePreloaded)) {
                        return $item;
                    }
                }
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
        ?string $excludeAppointmentId = null,
        bool $isOnlineBooking = false
    ): bool {
        // Initialize rule evaluator
        $this->ruleEvaluator = $this->getRuleEvaluator($branchId, $serviceId, $isOnlineBooking);

        $date = $datetime->copy()->startOfDay();
        $endTime = $datetime->copy()->addMinutes($duration);
        $dayOfWeek = $datetime->dayOfWeek;
        $startTimeStr = $datetime->format('H:i');

        // Check if date is within allowed range
        $advanceCheck = $this->ruleEvaluator->isDateWithinAdvanceRange($date);
        if ($advanceCheck !== true) {
            return false;
        }

        // Check if date/slot is blocked
        $slotBlocked = $this->ruleEvaluator->isSlotBlocked($date, $startTimeStr, $practitionerId);
        if ($slotBlocked !== false) {
            return false;
        }

        // Check capacity limits
        $capacityLimit = $this->ruleEvaluator->checkCapacityLimits($date, $practitionerId, $startTimeStr);
        if ($capacityLimit && $capacityLimit['exceeded']) {
            return false;
        }

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
        array $durationOverrides = [],
        bool $isOnlineBooking = false
    ): array {
        $result = [];

        foreach ($serviceIds as $serviceId) {
            $duration = $durationOverrides[$serviceId] ?? null;
            $slots = $this->generateAvailableSlots($serviceId, $branchId, $date, $duration, $isOnlineBooking);
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
        ?int $maxDaysAhead = null,
        bool $isOnlineBooking = false
    ): ?array {
        // Initialize rule evaluator to get max advance days
        $this->ruleEvaluator = $this->getRuleEvaluator($branchId, $serviceId, $isOnlineBooking);
        $maxAdvanceDays = $maxDaysAhead ?? $this->ruleEvaluator->getMaxAdvanceDays();

        $date = $fromDate ?? today();
        $endDate = $date->copy()->addDays($maxAdvanceDays);

        while ($date->lte($endDate)) {
            $slots = $this->generateAvailableSlots($serviceId, $branchId, $date, null, $isOnlineBooking);

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

    /**
     * Get booking policy summary for display (e.g., in booking UI).
     */
    public function getBookingPolicySummary(
        string $branchId,
        ?string $serviceId = null,
        bool $isOnlineBooking = false
    ): array {
        $evaluator = $this->getRuleEvaluator($branchId, $serviceId, $isOnlineBooking);

        return [
            'min_advance_hours' => $evaluator->getMinAdvanceHours(),
            'max_advance_days' => $evaluator->getMaxAdvanceDays(),
            'same_day_booking' => $evaluator->getSameDayBookingConfig(),
            'deposit_requirements' => $evaluator->getDepositRequirements(),
            'auto_confirm' => $evaluator->isAutoConfirmEnabled(),
            'online_booking_enabled' => $evaluator->isOnlineBookingEnabled(),
            'practitioner_selection_allowed' => $evaluator->isOnlinePractitionerSelectionAllowed(),
        ];
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

    // Protected helper methods

    protected function getQualifiedPractitioners(Service $service, string $branchId): Collection
    {
        // Check for practitioner requirement rules
        $practitionerReq = $this->ruleEvaluator?->getPractitionerRequirements();
        $useServicePractitioners = !$practitionerReq || $practitionerReq['source'] === 'service';

        if ($useServicePractitioners) {
            // Use getEffectiveQualifiedStaff() which cascades: Service → ServiceCategory
            $qualifiedStaff = $service->getEffectiveQualifiedStaff();

            if ($qualifiedStaff->isEmpty()) {
                return collect();
            }

            // Filter by branch and active status
            $staffProfileIds = $qualifiedStaff->pluck('id')->toArray();

            return \Modules\Staff\Models\StaffProfile::whereIn('id', $staffProfileIds)
                ->with('user')
                ->where('staff_profiles.is_active', true)
                ->where(function ($query) use ($branchId) {
                    // Staff profile is at this branch
                    $query->where('staff_profiles.branch_id', $branchId)
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
        } else {
            // Use manually specified practitioners from rule
            $requiredPractitionerIds = $practitionerReq['required_practitioners'] ?? [];
            if (!empty($requiredPractitionerIds)) {
                return \Modules\Staff\Models\StaffProfile::whereIn('user_id', $requiredPractitionerIds)
                    ->with('user')
                    ->where('staff_profiles.is_active', true)
                    ->get();
            }

            return collect();
        }
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

        // Note: max_advance_days is now handled by rules, not service restrictions
        // But we keep service-level restriction as an additional check if configured
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

        $startTime = $restrictions['allowed_time_start'] ?? null;
        $endTime = $restrictions['allowed_time_end'] ?? null;

        if ($startTime && $endTime) {
            return [
                'start' => $startTime,
                'end' => $endTime,
            ];
        }

        return null;
    }

    protected function generateTimeSlots(string $startTime, string $endTime, int $durationMinutes, ?int $intervalMinutes = null): array
    {
        $slots = [];
        $start = Carbon::createFromFormat('H:i', $startTime);
        $end = Carbon::createFromFormat('H:i', $endTime);

        // Use interval if specified, otherwise use duration
        $interval = $intervalMinutes ?? $durationMinutes;

        $current = $start->copy();

        while ($current->copy()->addMinutes($durationMinutes)->lte($end)) {
            $slots[] = [
                'start' => $current->format('H:i'),
                'end' => $current->copy()->addMinutes($durationMinutes)->format('H:i'),
            ];
            $current->addMinutes($interval);
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

    /**
     * Optimized room availability check using preloaded data when available.
     */
    protected function isRoomAvailableOptimized(string $roomId, Carbon $start, Carbon $end, bool $usePreloaded): bool
    {
        if ($usePreloaded) {
            $roomBookings = $this->preloadedRoomBookings->get($roomId) ?? collect();
            return !$this->hasTimeConflictInCollection($roomBookings, $start, $end);
        }

        return $this->isRoomAvailable($roomId, $start->copy()->startOfDay(), $start, $end);
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

    /**
     * Optimized equipment availability check using preloaded data when available.
     */
    protected function isEquipmentAvailableOptimized(string $equipmentId, Carbon $start, Carbon $end, bool $usePreloaded): bool
    {
        if ($usePreloaded) {
            $equipmentBookings = $this->preloadedEquipmentBookings->get($equipmentId) ?? collect();
            return !$this->hasTimeConflictInCollection($equipmentBookings, $start, $end);
        }

        return $this->isEquipmentAvailable($equipmentId, $start->copy()->startOfDay(), $start, $end);
    }

    /**
     * Check if any appointment in the collection conflicts with the given time range.
     */
    protected function hasTimeConflictInCollection(Collection $appointments, Carbon $start, Carbon $end): bool
    {
        $startTimeStr = $start->format('H:i:s');
        $endTimeStr = $end->format('H:i:s');

        foreach ($appointments as $appointment) {
            $apptStart = $appointment->start_time;
            $apptEnd = $appointment->end_time
                ?? Carbon::createFromFormat('H:i:s', $apptStart)
                    ->addMinutes($appointment->duration_minutes)
                    ->format('H:i:s');

            // Check overlap: start1 < end2 AND end1 > start2
            if ($apptStart < $endTimeStr && $apptEnd > $startTimeStr) {
                return true;
            }
        }

        return false;
    }

    protected function timeSlotsOverlap(string $start1, string $end1, string $start2, string $end2): bool
    {
        return $start1 < $end2 && $end1 > $start2;
    }

    /**
     * Enrich practitioner data with status, recommendations, and next appointment info.
     * Optimized to use pre-loaded data when available.
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
        $usePreloaded = $this->preloadedDate?->isSameDay($date) && $this->preloadedBranchId === $branchId;

        // Get recommended practitioner (first one - could be enhanced with more logic)
        $recommendedIndex = 0;
        $slotEndTimeStr = $slotEnd->format('H:i:s');

        foreach ($practitioners as $index => $staffProfile) {
            // Skip staff profiles without valid user or user_id
            if (!$staffProfile->user_id || !$staffProfile->user) {
                continue;
            }
            $practitionerId = $staffProfile->user_id;

            // Get next appointment for this practitioner after slot end
            $nextAppointment = null;

            if ($usePreloaded) {
                // Find next appointment from preloaded data
                $nextAppointment = $this->preloadedAppointments
                    ->where('practitioner_id', $practitionerId)
                    ->filter(fn($a) => $a->start_time >= $slotEndTimeStr)
                    ->sortBy('start_time')
                    ->first();
            } else {
                $nextAppointment = Appointment::query()
                    ->forPractitioner($practitionerId)
                    ->forDate($date)
                    ->active()
                    ->whereRaw("start_time >= ?", [$slotEndTimeStr])
                    ->orderBy('start_time')
                    ->first(['id', 'start_time', 'service_id']);
            }

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
     * Legacy method for backward compatibility.
     * @deprecated Use generateAvailableSlots with isOnlineBooking parameter instead.
     */
    public function generateAvailableSlotsWithRules(
        string $serviceId,
        string $branchId,
        Carbon $date,
        ?int $durationOverride = null,
        bool $isOnlineBooking = false
    ): Collection {
        return $this->generateAvailableSlots($serviceId, $branchId, $date, $durationOverride, $isOnlineBooking);
    }
}
