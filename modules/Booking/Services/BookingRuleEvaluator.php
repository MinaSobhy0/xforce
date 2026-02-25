<?php

namespace Modules\Booking\Services;

use Modules\Booking\Models\Appointment;
use Modules\Booking\Models\BookingRule;
use Modules\Booking\Models\BookingBlackoutDate;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class BookingRuleEvaluator
{
    protected Collection $rules;
    protected Collection $blackoutDates;
    protected ?string $branchId = null;
    protected ?string $serviceId = null;
    protected bool $isOnlineBooking = false;
    protected bool $isStaffBooking = false;

    /**
     * Load rules and blackout dates for evaluation.
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

        // Load applicable rules
        $this->rules = BookingRule::active()
            ->applicableTo($branchId, $serviceId)
            ->orderedByPriority()
            ->get();

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
     * Check if a specific time slot is blocked by rules.
     */
    public function isSlotBlocked(
        Carbon $date,
        string $time,
        ?string $practitionerId = null
    ): bool|array {
        // First check blackout dates
        $dateBlocked = $this->isDateBlocked($date);
        if ($dateBlocked !== false) {
            return $dateBlocked;
        }

        // Evaluate each rule
        foreach ($this->rules as $rule) {
            if (!$rule->appliesTo(
                $date,
                $time,
                $this->branchId,
                $this->serviceId,
                $practitionerId,
                $this->isOnlineBooking
            )) {
                continue;
            }

            // Check slot block rules
            if ($rule->shouldBlockSlot()) {
                return [
                    'blocked' => true,
                    'reason' => $rule->getBlockReason() ?? $rule->name,
                    'rule_id' => $rule->id,
                    'rule_type' => $rule->rule_type,
                ];
            }

            // Check online booking restrictions
            if ($this->isOnlineBooking && $rule->restrictsOnlineBooking()) {
                return [
                    'blocked' => true,
                    'reason' => $rule->getBlockReason() ?? __('booking::config.online_restricted'),
                    'rule_id' => $rule->id,
                    'rule_type' => $rule->rule_type,
                ];
            }
        }

        return false;
    }

    /**
     * Get the effective buffer time for a service/slot.
     */
    public function getEffectiveBuffer(
        Carbon $date,
        ?string $time = null,
        ?string $practitionerId = null,
        int $defaultBuffer = 5
    ): int {
        foreach ($this->rules as $rule) {
            if ($rule->rule_type !== BookingRule::TYPE_BUFFER_OVERRIDE) {
                continue;
            }

            if (!$rule->appliesTo(
                $date,
                $time,
                $this->branchId,
                $this->serviceId,
                $practitionerId,
                $this->isOnlineBooking
            )) {
                continue;
            }

            $customBuffer = $rule->getBufferMinutes();
            if ($customBuffer !== null) {
                return $customBuffer;
            }
        }

        return $defaultBuffer;
    }

    /**
     * Get effective time restrictions for a date.
     */
    public function getEffectiveTimeRestrictions(Carbon $date): ?array
    {
        foreach ($this->rules as $rule) {
            if ($rule->rule_type !== BookingRule::TYPE_TIME_RESTRICTION) {
                continue;
            }

            if (!$rule->appliesTo($date, null, $this->branchId, $this->serviceId)) {
                continue;
            }

            $allowedStart = $rule->getAllowedStartTime();
            $allowedEnd = $rule->getAllowedEndTime();

            if ($allowedStart || $allowedEnd) {
                return [
                    'start' => $allowedStart,
                    'end' => $allowedEnd,
                    'rule_id' => $rule->id,
                    'rule_name' => $rule->name,
                ];
            }
        }

        return null;
    }

    /**
     * Get effective advance booking restrictions.
     */
    public function getEffectiveAdvanceBooking(Carbon $date): ?array
    {
        foreach ($this->rules as $rule) {
            if ($rule->rule_type !== BookingRule::TYPE_ADVANCE_BOOKING) {
                continue;
            }

            if (!$rule->appliesTo($date, null, $this->branchId, $this->serviceId)) {
                continue;
            }

            $minHours = $rule->actions['min_hours'] ?? null;
            $maxDays = $rule->actions['max_days'] ?? null;

            if ($minHours !== null || $maxDays !== null) {
                return [
                    'min_hours' => $minHours,
                    'max_days' => $maxDays,
                    'rule_id' => $rule->id,
                ];
            }
        }

        return null;
    }

    /**
     * Check capacity limits for a date/practitioner.
     */
    public function checkCapacityLimits(
        Carbon $date,
        ?string $practitionerId = null
    ): ?array {
        foreach ($this->rules as $rule) {
            if ($rule->rule_type !== BookingRule::TYPE_CAPACITY_LIMIT) {
                continue;
            }

            if (!$rule->appliesTo($date, null, $this->branchId, $this->serviceId, $practitionerId)) {
                continue;
            }

            $maxAppointments = $rule->getMaxAppointments();
            $scope = $rule->actions['scope'] ?? 'day';

            if ($maxAppointments === null) {
                continue;
            }

            // Count existing appointments
            $query = Appointment::query()
                ->forDate($date)
                ->forBranch($this->branchId)
                ->active();

            if ($this->serviceId) {
                $query->forService($this->serviceId);
            }

            if ($scope === 'practitioner' && $practitionerId) {
                $query->forPractitioner($practitionerId);
            }

            $currentCount = $query->count();

            if ($currentCount >= $maxAppointments) {
                return [
                    'exceeded' => true,
                    'max' => $maxAppointments,
                    'current' => $currentCount,
                    'scope' => $scope,
                    'rule_id' => $rule->id,
                    'rule_name' => $rule->name,
                ];
            }
        }

        return null;
    }

    /**
     * Filter a collection of time slots based on rules.
     */
    public function filterSlots(Collection $slots, Carbon $date): Collection
    {
        // Check if entire date is blocked
        $dateBlocked = $this->isDateBlocked($date);
        if ($dateBlocked !== false) {
            return collect();
        }

        // Get time restrictions
        $timeRestrictions = $this->getEffectiveTimeRestrictions($date);

        return $slots->filter(function ($slot) use ($date, $timeRestrictions) {
            $startTime = $slot['start_time'] ?? $slot['start'] ?? null;
            if (!$startTime) {
                return true;
            }

            // Apply time restrictions
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

            // Check if slot is blocked by rules
            $blocked = $this->isSlotBlocked($date, $startTime);
            if ($blocked !== false) {
                return false;
            }

            return true;
        });
    }

    /**
     * Get all applicable rules for debugging/display.
     */
    public function getApplicableRules(): Collection
    {
        return $this->rules;
    }

    /**
     * Get all applicable blackout dates for display.
     */
    public function getApplicableBlackouts(): Collection
    {
        return $this->blackoutDates;
    }

    /**
     * Evaluate all rules and return a summary of what would be applied.
     */
    public function evaluateSummary(Carbon $date, ?string $time = null): array
    {
        $summary = [
            'date_blocked' => false,
            'blocked_reason' => null,
            'time_restrictions' => null,
            'buffer_override' => null,
            'capacity_limit' => null,
            'applicable_rules' => [],
            'applicable_blackouts' => [],
        ];

        // Check date blocking
        $dateBlocked = $this->isDateBlocked($date);
        if ($dateBlocked !== false) {
            $summary['date_blocked'] = true;
            $summary['blocked_reason'] = $dateBlocked['reason'];
        }

        // Get time restrictions
        $summary['time_restrictions'] = $this->getEffectiveTimeRestrictions($date);

        // Get buffer override
        $defaultBuffer = config('booking.buffer_minutes', 5);
        $effectiveBuffer = $this->getEffectiveBuffer($date, $time);
        if ($effectiveBuffer !== $defaultBuffer) {
            $summary['buffer_override'] = $effectiveBuffer;
        }

        // Check capacity
        $summary['capacity_limit'] = $this->checkCapacityLimits($date);

        // List applicable rules
        foreach ($this->rules as $rule) {
            if ($rule->appliesTo($date, $time, $this->branchId, $this->serviceId)) {
                $summary['applicable_rules'][] = [
                    'id' => $rule->id,
                    'name' => $rule->name,
                    'type' => $rule->rule_type,
                    'priority' => $rule->priority,
                ];
            }
        }

        // List applicable blackouts
        foreach ($this->blackoutDates as $blackout) {
            if ($blackout->blocksDate($date, $this->branchId, $this->isOnlineBooking, $this->isStaffBooking)) {
                $summary['applicable_blackouts'][] = [
                    'id' => $blackout->id,
                    'name' => $blackout->name,
                    'start_date' => $blackout->start_date->format('Y-m-d'),
                    'end_date' => $blackout->end_date->format('Y-m-d'),
                ];
            }
        }

        return $summary;
    }
}
