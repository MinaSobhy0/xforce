<?php

namespace Modules\Booking\Services;

use Modules\Booking\Models\Appointment;
use Modules\Booking\Models\BookingConfig;
use Modules\Booking\Models\BookingBlackoutDate;
use Modules\Core\Models\Branch;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class BookingRuleEvaluator
{
    protected ?BookingConfig $config = null;
    protected ?Branch $branch = null;
    protected Collection $blackoutDates;
    protected ?string $branchId = null;
    protected ?string $serviceId = null;
    protected bool $isOnlineBooking = false;
    protected bool $isStaffBooking = false;

    /**
     * Load configuration and blackout dates for evaluation.
     */
    public function forContext(
        string $branchId,
        ?string $serviceId = null,
        bool $isOnlineBooking = false,
        bool $isStaffBooking = false
    ): self {
        $this->branchId = $branchId;
        $this->serviceId = $serviceId;
        $this->isOnlineBooking = $isOnlineBooking;
        $this->isStaffBooking = !$isOnlineBooking;

        // Load branch for working hours
        $this->branch = Branch::find($branchId);

        // Load configuration for this branch
        $this->config = BookingConfig::getForBranch($branchId);

        // Load applicable blackout dates
        $this->blackoutDates = BookingBlackoutDate::active()
            ->forBranch($branchId)
            ->get();

        return $this;
    }

    /**
     * Check if a date is blocked (by blackout dates).
     */
    public function isDateBlocked(Carbon $date): bool|array
    {
        $blockingBlackout = $this->blackoutDates->first(function ($blackout) use ($date) {
            return $blackout->blocksDate($date, $this->branchId, $this->isOnlineBooking, $this->isStaffBooking);
        });

        if ($blockingBlackout) {
            return [
                'blocked' => true,
                'reason' => $blockingBlackout->name,
                'blackout_id' => $blockingBlackout->id,
            ];
        }

        return false;
    }

    /**
     * Check if a specific time slot is blocked.
     */
    public function isSlotBlocked(
        Carbon $date,
        string $time,
        ?string $practitionerId = null
    ): bool|array {
        // Check blackout dates
        $dateBlocked = $this->isDateBlocked($date);
        if ($dateBlocked !== false) {
            return $dateBlocked;
        }

        // Check break times from branch working hours
        $breakTimes = $this->getBreakTimes($date);
        foreach ($breakTimes as $break) {
            if ($time >= $break['start'] && $time < $break['end']) {
                return [
                    'blocked' => true,
                    'reason' => $break['rule_name'] ?? __('booking::config.break_time'),
                    'rule_type' => 'break_time',
                ];
            }
        }

        return false;
    }

    // ========================================
    // SLOT GENERATION CONFIG
    // ========================================

    /**
     * Get the effective slot duration from config.
     */
    public function getEffectiveSlotDuration(?int $serviceDuration = null): int
    {
        // Service duration takes priority if set
        if ($serviceDuration) {
            return $serviceDuration;
        }

        return $this->config->slot_duration ?? 30;
    }

    /**
     * Get the effective slot interval from config.
     */
    public function getEffectiveSlotInterval(?int $serviceDuration = null): int
    {
        if ($this->config->slot_interval) {
            return $this->config->slot_interval;
        }

        // Fall back to service duration or slot duration
        return $serviceDuration ?? $this->config->slot_duration ?? 30;
    }

    /**
     * Get the effective buffer time from config.
     */
    public function getEffectiveBuffer(
        Carbon $date = null,
        ?string $time = null,
        ?string $practitionerId = null
    ): int {
        return $this->config->buffer_minutes ?? 5;
    }

    // ========================================
    // TIME/SCHEDULE CONFIG
    // ========================================

    /**
     * Get effective working hours from Branch settings.
     */
    public function getEffectiveWorkingHours(Carbon $date = null): array
    {
        // Get day of week (0 = Sunday, 6 = Saturday)
        $dayOfWeek = $date ? $date->dayOfWeek : now()->dayOfWeek;
        $dayName = strtolower(Carbon::getDays()[$dayOfWeek]);

        // Try to get from Branch working_hours
        if ($this->branch && $this->branch->working_hours) {
            $dayHours = $this->branch->getWorkingHoursForDay($dayName);

            if ($dayHours) {
                // Check if closed
                if (!empty($dayHours['is_closed'])) {
                    return [
                        'start' => null,
                        'end' => null,
                        'is_closed' => true,
                        'rule_id' => null,
                        'rule_name' => null,
                        'is_special' => false,
                    ];
                }

                return [
                    'start' => $dayHours['open_time'] ?? $dayHours['start'] ?? $dayHours['open'] ?? '09:00',
                    'end' => $dayHours['close_time'] ?? $dayHours['end'] ?? $dayHours['close'] ?? '21:00',
                    'rule_id' => null,
                    'rule_name' => null,
                    'is_special' => false,
                ];
            }
        }

        // Default fallback
        return [
            'start' => '09:00',
            'end' => '21:00',
            'rule_id' => null,
            'rule_name' => null,
            'is_special' => false,
        ];
    }

    /**
     * Get break times from Branch settings.
     */
    public function getBreakTimes(Carbon $date): array
    {
        // Get day of week
        $dayOfWeek = $date->dayOfWeek;
        $dayName = strtolower(Carbon::getDays()[$dayOfWeek]);

        // Try to get from Branch working_hours
        if ($this->branch && $this->branch->working_hours) {
            $dayHours = $this->branch->getWorkingHoursForDay($dayName);

            if ($dayHours && !empty($dayHours['break_start']) && !empty($dayHours['break_end'])) {
                return [
                    [
                        'start' => $dayHours['break_start'],
                        'end' => $dayHours['break_end'],
                        'rule_id' => null,
                        'rule_name' => __('booking::config.break_time'),
                    ],
                ];
            }
        }

        return [];
    }

    /**
     * Get effective time restrictions for a date.
     */
    public function getEffectiveTimeRestrictions(Carbon $date): ?array
    {
        // No time restrictions in simplified config
        return null;
    }

    /**
     * Get online booking hours restrictions.
     */
    public function getOnlineBookingHours(Carbon $date): ?array
    {
        // Uses same working hours
        return null;
    }

    // ========================================
    // ADVANCE BOOKING CONFIG
    // ========================================

    /**
     * Get minimum advance booking hours from config.
     */
    public function getMinAdvanceHours(): int
    {
        return $this->config->min_advance_hours ?? 2;
    }

    /**
     * Get maximum advance booking days from config.
     */
    public function getMaxAdvanceDays(): int
    {
        return $this->config->max_advance_days ?? 60;
    }

    /**
     * Get same-day booking configuration from config.
     */
    public function getSameDayBookingConfig(): array
    {
        return [
            'allowed' => $this->config->allow_same_day ?? true,
            'cutoff_time' => $this->config->same_day_cutoff,
            'rule_id' => null,
            'rule_name' => null,
        ];
    }

    /**
     * Check if a date is within allowed booking advance range.
     */
    public function isDateWithinAdvanceRange(Carbon $date): bool|array
    {
        $now = now();
        $dateStart = $date->copy()->startOfDay();
        $isToday = $dateStart->isSameDay($now);

        // Check same-day booking
        if ($isToday) {
            $sameDayConfig = $this->getSameDayBookingConfig();
            if (!$sameDayConfig['allowed']) {
                return [
                    'allowed' => false,
                    'reason' => __('booking::config.same_day_not_allowed'),
                    'rule_id' => null,
                ];
            }

            if ($sameDayConfig['cutoff_time']) {
                $cutoff = Carbon::parse($sameDayConfig['cutoff_time']);
                if ($now->format('H:i') > $cutoff->format('H:i')) {
                    return [
                        'allowed' => false,
                        'reason' => __('booking::config.same_day_cutoff_passed'),
                        'rule_id' => null,
                    ];
                }
            }
        }

        // Check max advance days
        $maxDays = $this->getMaxAdvanceDays();
        $daysUntil = $now->startOfDay()->diffInDays($dateStart, false);
        if ($daysUntil > $maxDays) {
            return [
                'allowed' => false,
                'reason' => __('booking::config.exceeds_max_advance', ['days' => $maxDays]),
            ];
        }

        return true;
    }

    /**
     * Check if a slot time meets minimum advance requirement.
     */
    public function meetsMinAdvanceRequirement(Carbon $slotDateTime): bool
    {
        $minHours = $this->getMinAdvanceHours();
        if ($minHours <= 0) {
            return true;
        }

        $hoursUntilSlot = now()->diffInHours($slotDateTime, false);
        return $hoursUntilSlot >= $minHours;
    }

    // ========================================
    // CAPACITY CONFIG
    // ========================================

    /**
     * Check all capacity limits for a date/practitioner.
     */
    public function checkCapacityLimits(
        Carbon $date,
        ?string $practitionerId = null,
        ?string $time = null
    ): ?array {
        // Check practitioner daily capacity
        if ($practitionerId && $this->config->max_per_doctor_daily) {
            $currentCount = Appointment::query()
                ->forDate($date)
                ->forPractitioner($practitionerId)
                ->active()
                ->count();

            if ($currentCount >= $this->config->max_per_doctor_daily) {
                return [
                    'exceeded' => true,
                    'type' => 'practitioner',
                    'max' => $this->config->max_per_doctor_daily,
                    'current' => $currentCount,
                    'practitioner_id' => $practitionerId,
                ];
            }
        }

        return null;
    }

    // ========================================
    // ONLINE BOOKING CONFIG
    // ========================================

    /**
     * Check if online booking is enabled.
     */
    public function isOnlineBookingEnabled(): bool
    {
        // Online booking is enabled by default in simplified config
        return true;
    }

    /**
     * Check if practitioner selection is allowed for online booking.
     */
    public function isOnlinePractitionerSelectionAllowed(): bool
    {
        return true;
    }

    // ========================================
    // CONFIRMATION CONFIG
    // ========================================

    /**
     * Check if auto-confirm is enabled.
     */
    public function isAutoConfirmEnabled(): bool
    {
        return false;
    }

    /**
     * Get deposit requirements.
     */
    public function getDepositRequirements(): ?array
    {
        return null;
    }

    /**
     * Check if approval is required.
     */
    public function isApprovalRequired(): bool
    {
        return false;
    }

    // ========================================
    // RESOURCE CONFIG
    // ========================================

    /**
     * Get room preference configuration.
     */
    public function getRoomPreference(): ?array
    {
        return [
            'source' => $this->config->room_assignment ?? 'service',
            'preferred_rooms' => [],
            'strict' => false,
            'check_availability' => $this->config->check_room_availability ?? true,
            'allow_overlap' => $this->config->allow_room_overlap ?? false,
        ];
    }

    /**
     * Get equipment requirements configuration.
     */
    public function getEquipmentRequirements(): ?array
    {
        return [
            'source' => $this->config->equipment_assignment ?? 'service',
            'required_equipment' => [],
            'strict' => false,
            'check_availability' => $this->config->check_equipment_availability ?? true,
            'allow_overlap' => $this->config->allow_equipment_overlap ?? false,
        ];
    }

    /**
     * Get practitioner requirements configuration.
     */
    public function getPractitionerRequirements(): ?array
    {
        return [
            'source' => 'service',
            'required_practitioners' => [],
            'required_qualifications' => [],
            'strict' => false,
            'check_schedule' => $this->config->check_doctor_schedule ?? true,
            'check_timeoff' => $this->config->check_doctor_timeoff ?? true,
            'allow_overlap' => $this->config->allow_doctor_overlap ?? false,
        ];
    }

    // ========================================
    // UTILITY METHODS
    // ========================================

    /**
     * Filter a collection of time slots based on configuration.
     */
    public function filterSlots(Collection $slots, Carbon $date): Collection
    {
        // Check if entire date is blocked
        $dateBlocked = $this->isDateBlocked($date);
        if ($dateBlocked !== false) {
            return collect();
        }

        // Check date is within advance booking range
        $advanceCheck = $this->isDateWithinAdvanceRange($date);
        if ($advanceCheck !== true) {
            return collect();
        }

        // Get working hours (from Branch)
        $workingHours = $this->getEffectiveWorkingHours($date);

        // Check if branch is closed on this day
        if (!empty($workingHours['is_closed']) || empty($workingHours['start']) || empty($workingHours['end'])) {
            return collect();
        }

        // Get break times (from Branch)
        $breakTimes = $this->getBreakTimes($date);

        // Get min advance hours
        $minAdvanceHours = $this->getMinAdvanceHours();

        return $slots->filter(function ($slot) use ($date, $workingHours, $breakTimes, $minAdvanceHours) {
            $startTime = $slot['start_time'] ?? $slot['start'] ?? null;
            if (!$startTime) {
                return true;
            }

            $slotDateTime = Carbon::parse($date->format('Y-m-d') . ' ' . $startTime);

            // Check min advance hours
            if ($minAdvanceHours > 0) {
                $hoursUntilSlot = now()->diffInHours($slotDateTime, false);
                if ($hoursUntilSlot < $minAdvanceHours) {
                    return false;
                }
            }

            // Apply working hours
            if ($startTime < $workingHours['start'] || $startTime >= $workingHours['end']) {
                return false;
            }

            // Apply break times
            foreach ($breakTimes as $break) {
                if ($startTime >= $break['start'] && $startTime < $break['end']) {
                    return false;
                }
            }

            // Check if slot is blocked
            $blocked = $this->isSlotBlocked($date, $startTime);
            if ($blocked !== false) {
                return false;
            }

            return true;
        });
    }

    /**
     * Get all applicable blackout dates for display.
     */
    public function getApplicableBlackouts(): Collection
    {
        return $this->blackoutDates;
    }

    /**
     * Get configuration summary.
     */
    public function getConfigSummary(): array
    {
        return [
            'slot_duration' => $this->config->slot_duration,
            'slot_interval' => $this->config->effective_slot_interval,
            'buffer_minutes' => $this->config->buffer_minutes,
            'working_hours' => $this->branch?->working_hours,
            'working_hours_source' => 'branch',
            'min_advance_hours' => $this->config->min_advance_hours,
            'max_advance_days' => $this->config->max_advance_days,
            'allow_same_day' => $this->config->allow_same_day,
            'check_doctor_schedule' => $this->config->check_doctor_schedule,
            'check_doctor_timeoff' => $this->config->check_doctor_timeoff,
            'max_per_doctor_daily' => $this->config->max_per_doctor_daily,
            'allow_doctor_overlap' => $this->config->allow_doctor_overlap,
            'room_assignment' => $this->config->room_assignment,
            'check_room_availability' => $this->config->check_room_availability,
            'allow_room_overlap' => $this->config->allow_room_overlap,
            'equipment_assignment' => $this->config->equipment_assignment,
            'check_equipment_availability' => $this->config->check_equipment_availability,
            'allow_equipment_overlap' => $this->config->allow_equipment_overlap,
        ];
    }
}
