<?php

namespace Modules\Booking\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use XLinic\Framework\Core\Model\Traits\HasActivity;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Auth\Models\User;
use Modules\Core\Models\Branch;
use Modules\Services\Models\Service;
use Carbon\Carbon;

class BookingRule extends BaseModel
{
    use HasTenancy, HasActivity, SoftDeletes;

    protected $table = 'booking_rules';

    protected $fillable = [
        'tenant_id',
        'scope_level',
        'branch_id',
        'service_id',
        'name',
        'code',
        'description',
        'rule_type',
        'conditions',
        'actions',
        'priority',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'conditions' => 'array',
        'actions' => 'array',
        'priority' => 'integer',
        'is_active' => 'boolean',
    ];

    // Scope levels
    public const SCOPE_TENANT = 'tenant';
    public const SCOPE_BRANCH = 'branch';
    public const SCOPE_SERVICE = 'service';

    public const SCOPE_LEVELS = [
        self::SCOPE_TENANT => 'Tenant-wide',
        self::SCOPE_BRANCH => 'Branch-specific',
        self::SCOPE_SERVICE => 'Service-specific',
    ];

    // Rule types
    public const TYPE_SLOT_BLOCK = 'slot_block';
    public const TYPE_TIME_RESTRICTION = 'time_restriction';
    public const TYPE_CAPACITY_LIMIT = 'capacity_limit';
    public const TYPE_BUFFER_OVERRIDE = 'buffer_override';
    public const TYPE_ADVANCE_BOOKING = 'advance_booking';
    public const TYPE_ONLINE_RESTRICTION = 'online_restriction';
    public const TYPE_PRACTITIONER_LIMIT = 'practitioner_limit';

    public const RULE_TYPES = [
        self::TYPE_SLOT_BLOCK => 'Block Slots',
        self::TYPE_TIME_RESTRICTION => 'Time Restriction',
        self::TYPE_CAPACITY_LIMIT => 'Capacity Limit',
        self::TYPE_BUFFER_OVERRIDE => 'Buffer Override',
        self::TYPE_ADVANCE_BOOKING => 'Advance Booking',
        self::TYPE_ONLINE_RESTRICTION => 'Online Restriction',
        self::TYPE_PRACTITIONER_LIMIT => 'Practitioner Limit',
    ];

    public const RULE_TYPE_COLORS = [
        self::TYPE_SLOT_BLOCK => 'danger',
        self::TYPE_TIME_RESTRICTION => 'warning',
        self::TYPE_CAPACITY_LIMIT => 'info',
        self::TYPE_BUFFER_OVERRIDE => 'primary',
        self::TYPE_ADVANCE_BOOKING => 'success',
        self::TYPE_ONLINE_RESTRICTION => 'gray',
        self::TYPE_PRACTITIONER_LIMIT => 'secondary',
    ];

    public const RULE_TYPE_ICONS = [
        self::TYPE_SLOT_BLOCK => 'heroicon-o-x-circle',
        self::TYPE_TIME_RESTRICTION => 'heroicon-o-clock',
        self::TYPE_CAPACITY_LIMIT => 'heroicon-o-users',
        self::TYPE_BUFFER_OVERRIDE => 'heroicon-o-arrows-right-left',
        self::TYPE_ADVANCE_BOOKING => 'heroicon-o-calendar-days',
        self::TYPE_ONLINE_RESTRICTION => 'heroicon-o-globe-alt',
        self::TYPE_PRACTITIONER_LIMIT => 'heroicon-o-user',
    ];

    // Days of week for conditions
    public const DAYS_OF_WEEK = [
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    ];

    // Relationships
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Accessors
    public function getRuleTypeLabelAttribute(): string
    {
        return self::RULE_TYPES[$this->rule_type] ?? $this->rule_type;
    }

    public function getRuleTypeColorAttribute(): string
    {
        return self::RULE_TYPE_COLORS[$this->rule_type] ?? 'gray';
    }

    public function getRuleTypeIconAttribute(): string
    {
        return self::RULE_TYPE_ICONS[$this->rule_type] ?? 'heroicon-o-cog';
    }

    public function getScopeLevelLabelAttribute(): string
    {
        return self::SCOPE_LEVELS[$this->scope_level] ?? $this->scope_level;
    }

    public function getScopeDescriptionAttribute(): string
    {
        return match ($this->scope_level) {
            self::SCOPE_BRANCH => $this->branch?->name ?? 'Branch',
            self::SCOPE_SERVICE => $this->service?->name ?? 'Service',
            default => 'All',
        };
    }

    // Condition helpers
    public function getDaysOfWeekCondition(): array
    {
        return $this->conditions['days_of_week'] ?? [];
    }

    public function getTimeRangeCondition(): ?array
    {
        return $this->conditions['time_range'] ?? null;
    }

    public function getDateRangeCondition(): ?array
    {
        return $this->conditions['date_range'] ?? null;
    }

    public function getServicesCondition(): array
    {
        return $this->conditions['services'] ?? [];
    }

    public function getPractitionersCondition(): array
    {
        return $this->conditions['practitioners'] ?? [];
    }

    // Action helpers
    public function getBlockReason(): ?string
    {
        return $this->actions['reason'] ?? null;
    }

    public function getBufferMinutes(): ?int
    {
        return $this->actions['buffer_minutes'] ?? null;
    }

    public function getMaxAppointments(): ?int
    {
        return $this->actions['max_appointments'] ?? null;
    }

    public function getAllowedStartTime(): ?string
    {
        return $this->actions['allowed_start'] ?? null;
    }

    public function getAllowedEndTime(): ?string
    {
        return $this->actions['allowed_end'] ?? null;
    }

    // Rule evaluation
    public function appliesTo(
        Carbon $date,
        ?string $time = null,
        ?string $branchId = null,
        ?string $serviceId = null,
        ?string $practitionerId = null,
        bool $isOnlineBooking = false
    ): bool {
        // Check if rule is active
        if (!$this->is_active) {
            return false;
        }

        // Check scope
        if ($this->scope_level === self::SCOPE_BRANCH && $this->branch_id !== $branchId) {
            return false;
        }
        if ($this->scope_level === self::SCOPE_SERVICE && $this->service_id !== $serviceId) {
            return false;
        }

        // Check day of week condition
        $daysCondition = $this->getDaysOfWeekCondition();
        if (!empty($daysCondition) && !in_array($date->dayOfWeek, $daysCondition)) {
            return false;
        }

        // Check date range condition
        $dateRange = $this->getDateRangeCondition();
        if ($dateRange) {
            $startDate = isset($dateRange['start']) ? Carbon::parse($dateRange['start']) : null;
            $endDate = isset($dateRange['end']) ? Carbon::parse($dateRange['end']) : null;

            if ($startDate && $date->lt($startDate)) {
                return false;
            }
            if ($endDate && $date->gt($endDate)) {
                return false;
            }
        }

        // Check time range condition
        if ($time) {
            $timeRange = $this->getTimeRangeCondition();
            if ($timeRange) {
                $slotTime = Carbon::parse($time);
                $startTime = isset($timeRange['start']) ? Carbon::parse($timeRange['start']) : null;
                $endTime = isset($timeRange['end']) ? Carbon::parse($timeRange['end']) : null;

                if ($startTime && $slotTime->lt($startTime)) {
                    return false;
                }
                if ($endTime && $slotTime->gte($endTime)) {
                    return false;
                }
            }
        }

        // Check services condition
        $servicesCondition = $this->getServicesCondition();
        if (!empty($servicesCondition) && $serviceId && !in_array($serviceId, $servicesCondition)) {
            return false;
        }

        // Check practitioners condition
        $practitionersCondition = $this->getPractitionersCondition();
        if (!empty($practitionersCondition) && $practitionerId && !in_array($practitionerId, $practitionersCondition)) {
            return false;
        }

        return true;
    }

    public function shouldBlockSlot(): bool
    {
        return $this->rule_type === self::TYPE_SLOT_BLOCK && ($this->actions['block'] ?? false);
    }

    public function restrictsOnlineBooking(): bool
    {
        return $this->rule_type === self::TYPE_ONLINE_RESTRICTION && !($this->actions['allow'] ?? true);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('rule_type', $type);
    }

    public function scopeByScope($query, string $scopeLevel, ?string $branchId = null, ?string $serviceId = null)
    {
        $query->where('scope_level', $scopeLevel);

        if ($scopeLevel === self::SCOPE_BRANCH && $branchId) {
            $query->where('branch_id', $branchId);
        }

        if ($scopeLevel === self::SCOPE_SERVICE && $serviceId) {
            $query->where('service_id', $serviceId);
        }

        return $query;
    }

    public function scopeOrderedByPriority($query)
    {
        return $query->orderByDesc('priority');
    }

    public function scopeApplicableTo($query, string $branchId, ?string $serviceId = null)
    {
        return $query->where(function ($q) use ($branchId, $serviceId) {
            // Tenant-wide rules always apply
            $q->where('scope_level', self::SCOPE_TENANT);

            // Branch-specific rules
            $q->orWhere(function ($bq) use ($branchId) {
                $bq->where('scope_level', self::SCOPE_BRANCH)
                    ->where('branch_id', $branchId);
            });

            // Service-specific rules
            if ($serviceId) {
                $q->orWhere(function ($sq) use ($serviceId) {
                    $sq->where('scope_level', self::SCOPE_SERVICE)
                        ->where('service_id', $serviceId);
                });
            }
        });
    }

    // Static helpers
    public static function getActiveRulesFor(string $branchId, ?string $serviceId = null): \Illuminate\Support\Collection
    {
        return static::active()
            ->applicableTo($branchId, $serviceId)
            ->orderedByPriority()
            ->get();
    }
}
