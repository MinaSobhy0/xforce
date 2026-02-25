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

    // ==========================================
    // SCOPE LEVELS
    // ==========================================
    public const SCOPE_TENANT = 'tenant';
    public const SCOPE_BRANCH = 'branch';
    public const SCOPE_SERVICE = 'service';
    public const SCOPE_PRACTITIONER = 'practitioner';
    public const SCOPE_SERVICE_CATEGORY = 'service_category';

    public const SCOPE_LEVELS = [
        self::SCOPE_TENANT => 'Tenant-wide (All)',
        self::SCOPE_BRANCH => 'Branch-specific',
        self::SCOPE_SERVICE => 'Service-specific',
        self::SCOPE_PRACTITIONER => 'Practitioner-specific',
        self::SCOPE_SERVICE_CATEGORY => 'Service Category',
    ];

    // ==========================================
    // RULE TYPES - Comprehensive Categories
    // ==========================================

    // Time & Duration Configuration Rules
    public const TYPE_SLOT_DURATION = 'slot_duration';
    public const TYPE_SLOT_INTERVAL = 'slot_interval';
    public const TYPE_SLOT_BUFFER = 'slot_buffer';
    public const TYPE_WORKING_HOURS = 'working_hours';
    public const TYPE_BREAK_TIME = 'break_time';
    public const TYPE_TIME_RESTRICTION = 'time_restriction';
    public const TYPE_SPECIAL_HOURS = 'special_hours';

    // Capacity Rules
    public const TYPE_CAPACITY_DAILY = 'capacity_daily';
    public const TYPE_CAPACITY_HOURLY = 'capacity_hourly';
    public const TYPE_CAPACITY_PRACTITIONER = 'capacity_practitioner';
    public const TYPE_CAPACITY_SERVICE = 'capacity_service';
    public const TYPE_OVERBOOKING = 'overbooking';

    // Advance Booking Rules
    public const TYPE_MIN_ADVANCE = 'min_advance';
    public const TYPE_MAX_ADVANCE = 'max_advance';
    public const TYPE_SAME_DAY = 'same_day';

    // Online Booking Rules
    public const TYPE_ONLINE_ENABLED = 'online_enabled';
    public const TYPE_ONLINE_SERVICES = 'online_services';
    public const TYPE_ONLINE_HOURS = 'online_hours';
    public const TYPE_ONLINE_PRACTITIONER = 'online_practitioner';

    // Patient Rules
    public const TYPE_PATIENT_NEW = 'patient_new';
    public const TYPE_PATIENT_RETURNING = 'patient_returning';
    public const TYPE_PATIENT_AGE = 'patient_age';
    public const TYPE_PATIENT_GENDER = 'patient_gender';

    // Resource Rules
    public const TYPE_ROOM_PREFERENCE = 'room_preference';
    public const TYPE_EQUIPMENT_REQUIRED = 'equipment_required';
    public const TYPE_PRACTITIONER_REQUIRED = 'practitioner_required';

    // Confirmation Rules
    public const TYPE_AUTO_CONFIRM = 'auto_confirm';
    public const TYPE_REQUIRE_DEPOSIT = 'require_deposit';
    public const TYPE_REQUIRE_APPROVAL = 'require_approval';

    // Rule type categories for UI organization
    public const RULE_CATEGORIES = [
        'time_config' => [
            'label' => 'Time & Duration',
            'icon' => 'heroicon-o-clock',
            'color' => 'info',
            'types' => [
                self::TYPE_SLOT_DURATION,
                self::TYPE_SLOT_INTERVAL,
                self::TYPE_SLOT_BUFFER,
                self::TYPE_WORKING_HOURS,
                self::TYPE_BREAK_TIME,
                self::TYPE_TIME_RESTRICTION,
                self::TYPE_SPECIAL_HOURS,
            ],
        ],
        'capacity' => [
            'label' => 'Capacity Management',
            'icon' => 'heroicon-o-users',
            'color' => 'warning',
            'types' => [
                self::TYPE_CAPACITY_DAILY,
                self::TYPE_CAPACITY_HOURLY,
                self::TYPE_CAPACITY_PRACTITIONER,
                self::TYPE_CAPACITY_SERVICE,
                self::TYPE_OVERBOOKING,
            ],
        ],
        'advance_booking' => [
            'label' => 'Advance Booking',
            'icon' => 'heroicon-o-calendar-days',
            'color' => 'success',
            'types' => [
                self::TYPE_MIN_ADVANCE,
                self::TYPE_MAX_ADVANCE,
                self::TYPE_SAME_DAY,
            ],
        ],
        'online_booking' => [
            'label' => 'Online Booking',
            'icon' => 'heroicon-o-globe-alt',
            'color' => 'cyan',
            'types' => [
                self::TYPE_ONLINE_ENABLED,
                self::TYPE_ONLINE_SERVICES,
                self::TYPE_ONLINE_HOURS,
                self::TYPE_ONLINE_PRACTITIONER,
            ],
        ],
        'patient_rules' => [
            'label' => 'Patient Rules',
            'icon' => 'heroicon-o-user-group',
            'color' => 'violet',
            'types' => [
                self::TYPE_PATIENT_NEW,
                self::TYPE_PATIENT_RETURNING,
                self::TYPE_PATIENT_AGE,
                self::TYPE_PATIENT_GENDER,
            ],
        ],
        'resources' => [
            'label' => 'Resource Allocation',
            'icon' => 'heroicon-o-cube',
            'color' => 'amber',
            'types' => [
                self::TYPE_ROOM_PREFERENCE,
                self::TYPE_EQUIPMENT_REQUIRED,
                self::TYPE_PRACTITIONER_REQUIRED,
            ],
        ],
        'confirmation' => [
            'label' => 'Confirmation & Deposit',
            'icon' => 'heroicon-o-check-badge',
            'color' => 'lime',
            'types' => [
                self::TYPE_AUTO_CONFIRM,
                self::TYPE_REQUIRE_DEPOSIT,
                self::TYPE_REQUIRE_APPROVAL,
            ],
        ],
    ];

    public const RULE_TYPES = [
        // Time & Duration
        self::TYPE_SLOT_DURATION => 'Slot Duration',
        self::TYPE_SLOT_INTERVAL => 'Slot Interval',
        self::TYPE_SLOT_BUFFER => 'Buffer Time',
        self::TYPE_WORKING_HOURS => 'Working Hours',
        self::TYPE_BREAK_TIME => 'Break Time',
        self::TYPE_TIME_RESTRICTION => 'Time Restriction',
        self::TYPE_SPECIAL_HOURS => 'Special Hours',

        // Capacity
        self::TYPE_CAPACITY_DAILY => 'Daily Capacity Limit',
        self::TYPE_CAPACITY_HOURLY => 'Hourly Capacity Limit',
        self::TYPE_CAPACITY_PRACTITIONER => 'Practitioner Capacity',
        self::TYPE_CAPACITY_SERVICE => 'Service Capacity',
        self::TYPE_OVERBOOKING => 'Overbooking Allowance',

        // Advance Booking
        self::TYPE_MIN_ADVANCE => 'Minimum Advance Booking',
        self::TYPE_MAX_ADVANCE => 'Maximum Advance Booking',
        self::TYPE_SAME_DAY => 'Same-Day Booking',

        // Online Booking
        self::TYPE_ONLINE_ENABLED => 'Online Booking Enabled',
        self::TYPE_ONLINE_SERVICES => 'Online Services Restriction',
        self::TYPE_ONLINE_HOURS => 'Online Booking Hours',
        self::TYPE_ONLINE_PRACTITIONER => 'Online Practitioner Selection',

        // Patient Rules
        self::TYPE_PATIENT_NEW => 'New Patient Rules',
        self::TYPE_PATIENT_RETURNING => 'Returning Patient Rules',
        self::TYPE_PATIENT_AGE => 'Patient Age Restriction',
        self::TYPE_PATIENT_GENDER => 'Patient Gender Restriction',

        // Resources
        self::TYPE_ROOM_PREFERENCE => 'Room Preference',
        self::TYPE_EQUIPMENT_REQUIRED => 'Equipment Requirement',
        self::TYPE_PRACTITIONER_REQUIRED => 'Required Practitioner',

        // Confirmation
        self::TYPE_AUTO_CONFIRM => 'Auto-Confirmation',
        self::TYPE_REQUIRE_DEPOSIT => 'Require Deposit',
        self::TYPE_REQUIRE_APPROVAL => 'Require Approval',
    ];

    public const RULE_TYPE_DESCRIPTIONS = [
        // Time & Duration
        self::TYPE_SLOT_DURATION => 'Default duration for appointment slots',
        self::TYPE_SLOT_INTERVAL => 'Interval between slot start times',
        self::TYPE_SLOT_BUFFER => 'Buffer time between appointments',
        self::TYPE_WORKING_HOURS => 'Define available booking hours',
        self::TYPE_BREAK_TIME => 'Define break periods (e.g., lunch break)',
        self::TYPE_TIME_RESTRICTION => 'Restrict booking to specific time windows',
        self::TYPE_SPECIAL_HOURS => 'Define special operating hours (holidays, events)',

        // Capacity
        self::TYPE_CAPACITY_DAILY => 'Maximum appointments per day',
        self::TYPE_CAPACITY_HOURLY => 'Maximum appointments per hour',
        self::TYPE_CAPACITY_PRACTITIONER => 'Maximum appointments per practitioner per day',
        self::TYPE_CAPACITY_SERVICE => 'Maximum daily bookings for a specific service',
        self::TYPE_OVERBOOKING => 'Allow booking beyond normal capacity',

        // Advance Booking
        self::TYPE_MIN_ADVANCE => 'Minimum hours/days required to book in advance',
        self::TYPE_MAX_ADVANCE => 'Maximum days allowed to book in advance',
        self::TYPE_SAME_DAY => 'Allow or restrict same-day bookings',

        // Online Booking
        self::TYPE_ONLINE_ENABLED => 'Enable or disable online booking',
        self::TYPE_ONLINE_SERVICES => 'Restrict which services are available online',
        self::TYPE_ONLINE_HOURS => 'Different hours for online vs in-person booking',
        self::TYPE_ONLINE_PRACTITIONER => 'Allow patients to select practitioner online',

        // Patient Rules
        self::TYPE_PATIENT_NEW => 'Special rules for new patients',
        self::TYPE_PATIENT_RETURNING => 'Special rules for returning patients',
        self::TYPE_PATIENT_AGE => 'Age restrictions for services',
        self::TYPE_PATIENT_GENDER => 'Gender restrictions for services',

        // Resources
        self::TYPE_ROOM_PREFERENCE => 'Preferred room assignment',
        self::TYPE_EQUIPMENT_REQUIRED => 'Required equipment for booking',
        self::TYPE_PRACTITIONER_REQUIRED => 'Require specific practitioner qualification',

        // Confirmation
        self::TYPE_AUTO_CONFIRM => 'Automatically confirm appointments',
        self::TYPE_REQUIRE_DEPOSIT => 'Require deposit for booking',
        self::TYPE_REQUIRE_APPROVAL => 'Require manual approval before confirmation',
    ];

    public const RULE_TYPE_COLORS = [
        // Time & Duration - Info
        self::TYPE_SLOT_DURATION => 'info',
        self::TYPE_SLOT_INTERVAL => 'info',
        self::TYPE_SLOT_BUFFER => 'info',
        self::TYPE_WORKING_HOURS => 'info',
        self::TYPE_BREAK_TIME => 'info',
        self::TYPE_TIME_RESTRICTION => 'warning',
        self::TYPE_SPECIAL_HOURS => 'info',

        // Capacity - Warning
        self::TYPE_CAPACITY_DAILY => 'warning',
        self::TYPE_CAPACITY_HOURLY => 'warning',
        self::TYPE_CAPACITY_PRACTITIONER => 'warning',
        self::TYPE_CAPACITY_SERVICE => 'warning',
        self::TYPE_OVERBOOKING => 'danger',

        // Advance Booking - Success
        self::TYPE_MIN_ADVANCE => 'success',
        self::TYPE_MAX_ADVANCE => 'success',
        self::TYPE_SAME_DAY => 'success',

        // Online Booking - Gray
        self::TYPE_ONLINE_ENABLED => 'gray',
        self::TYPE_ONLINE_SERVICES => 'gray',
        self::TYPE_ONLINE_HOURS => 'gray',
        self::TYPE_ONLINE_PRACTITIONER => 'gray',

        // Patient Rules - Violet
        self::TYPE_PATIENT_NEW => 'violet',
        self::TYPE_PATIENT_RETURNING => 'violet',
        self::TYPE_PATIENT_AGE => 'violet',
        self::TYPE_PATIENT_GENDER => 'violet',

        // Resources - Amber
        self::TYPE_ROOM_PREFERENCE => 'amber',
        self::TYPE_EQUIPMENT_REQUIRED => 'amber',
        self::TYPE_PRACTITIONER_REQUIRED => 'amber',

        // Confirmation - Lime
        self::TYPE_AUTO_CONFIRM => 'lime',
        self::TYPE_REQUIRE_DEPOSIT => 'lime',
        self::TYPE_REQUIRE_APPROVAL => 'lime',
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

    // Time units
    public const TIME_UNITS = [
        'minutes' => 'Minutes',
        'hours' => 'Hours',
        'days' => 'Days',
    ];

    // Booking sources for conditions
    public const BOOKING_SOURCES = [
        'online' => 'Online Booking',
        'phone' => 'Phone Booking',
        'walkin' => 'Walk-in',
        'staff' => 'Staff Booking',
        'app' => 'Mobile App',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

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

    // ==========================================
    // ACCESSORS
    // ==========================================

    public function getRuleTypeLabelAttribute(): string
    {
        return self::RULE_TYPES[$this->rule_type] ?? $this->rule_type;
    }

    public function getRuleTypeDescriptionAttribute(): string
    {
        return self::RULE_TYPE_DESCRIPTIONS[$this->rule_type] ?? '';
    }

    public function getRuleTypeColorAttribute(): string
    {
        return self::RULE_TYPE_COLORS[$this->rule_type] ?? 'gray';
    }

    public function getRuleCategoryAttribute(): ?string
    {
        foreach (self::RULE_CATEGORIES as $category => $config) {
            if (in_array($this->rule_type, $config['types'])) {
                return $category;
            }
        }
        return null;
    }

    public function getRuleCategoryLabelAttribute(): string
    {
        $category = $this->rule_category;
        return $category ? (self::RULE_CATEGORIES[$category]['label'] ?? $category) : 'Other';
    }

    public function getRuleCategoryColorAttribute(): string
    {
        $category = $this->rule_category;
        return $category ? (self::RULE_CATEGORIES[$category]['color'] ?? 'gray') : 'gray';
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
            self::SCOPE_PRACTITIONER => 'Practitioner',
            self::SCOPE_SERVICE_CATEGORY => 'Category',
            default => 'All',
        };
    }

    // ==========================================
    // CONDITION HELPERS
    // ==========================================

    public function getCondition(string $key, $default = null)
    {
        return $this->conditions[$key] ?? $default;
    }

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

    public function getServiceCategoriesCondition(): array
    {
        return $this->conditions['service_categories'] ?? [];
    }

    public function getPractitionersCondition(): array
    {
        return $this->conditions['practitioners'] ?? [];
    }

    public function getRoomsCondition(): array
    {
        return $this->conditions['rooms'] ?? [];
    }

    public function getBookingSourceCondition(): array
    {
        return $this->conditions['booking_source'] ?? [];
    }

    public function getPatientTypeCondition(): ?string
    {
        return $this->conditions['patient_type'] ?? null;
    }

    // ==========================================
    // ACTION HELPERS
    // ==========================================

    public function getAction(string $key, $default = null)
    {
        return $this->actions[$key] ?? $default;
    }

    // Slot Generation Actions
    public function getDurationMinutes(): ?int
    {
        return $this->actions['duration_minutes'] ?? null;
    }

    public function getIntervalMinutes(): ?int
    {
        return $this->actions['interval_minutes'] ?? null;
    }

    public function getBufferMinutes(): ?int
    {
        return $this->actions['buffer_minutes'] ?? null;
    }

    public function getBlockReason(): ?string
    {
        return $this->actions['reason'] ?? null;
    }

    // Time Configuration Actions
    public function getStartTime(): ?string
    {
        return $this->actions['start_time'] ?? null;
    }

    public function getEndTime(): ?string
    {
        return $this->actions['end_time'] ?? null;
    }

    public function getAllowedStartTime(): ?string
    {
        return $this->actions['allowed_start'] ?? $this->actions['start_time'] ?? null;
    }

    public function getAllowedEndTime(): ?string
    {
        return $this->actions['allowed_end'] ?? $this->actions['end_time'] ?? null;
    }

    // Capacity Actions
    public function getMaxAppointments(): ?int
    {
        return $this->actions['max_appointments'] ?? null;
    }

    public function getMaxPerHour(): ?int
    {
        return $this->actions['max_per_hour'] ?? null;
    }

    public function getMaxPerPractitioner(): ?int
    {
        return $this->actions['max_per_practitioner'] ?? null;
    }

    public function getOverbookingLimit(): ?int
    {
        return $this->actions['overbooking_limit'] ?? null;
    }

    // Advance Booking Actions
    public function getMinAdvanceHours(): ?int
    {
        return $this->actions['min_hours'] ?? null;
    }

    public function getMinAdvanceDays(): ?int
    {
        return $this->actions['min_days'] ?? null;
    }

    public function getMaxAdvanceDays(): ?int
    {
        return $this->actions['max_days'] ?? null;
    }

    public function getAllowSameDay(): ?bool
    {
        return $this->actions['allow_same_day'] ?? null;
    }

    // Online Booking Actions
    public function getOnlineEnabled(): ?bool
    {
        return $this->actions['enabled'] ?? null;
    }

    public function getAllowPractitionerSelection(): ?bool
    {
        return $this->actions['allow_practitioner_selection'] ?? null;
    }

    // Deposit Actions
    public function getDepositRequired(): ?bool
    {
        return $this->actions['deposit_required'] ?? null;
    }

    public function getDepositPercentage(): ?float
    {
        return $this->actions['deposit_percentage'] ?? null;
    }

    public function getDepositAmount(): ?int
    {
        return $this->actions['deposit_amount'] ?? null;
    }

    // ==========================================
    // RULE EVALUATION
    // ==========================================

    public function appliesTo(
        Carbon $date,
        ?string $time = null,
        ?string $branchId = null,
        ?string $serviceId = null,
        ?string $practitionerId = null,
        ?string $bookingSource = null,
        ?string $patientType = null
    ): bool {
        // Check if rule is active
        if (!$this->is_active) {
            return false;
        }

        // Check scope
        if (!$this->matchesScope($branchId, $serviceId, $practitionerId)) {
            return false;
        }

        // Check conditions
        if (!$this->matchesConditions($date, $time, $serviceId, $practitionerId, $bookingSource, $patientType)) {
            return false;
        }

        return true;
    }

    protected function matchesScope(?string $branchId, ?string $serviceId, ?string $practitionerId): bool
    {
        return match ($this->scope_level) {
            self::SCOPE_BRANCH => $this->branch_id === $branchId,
            self::SCOPE_SERVICE => $this->service_id === $serviceId,
            self::SCOPE_PRACTITIONER => $practitionerId && in_array($practitionerId, $this->getPractitionersCondition()),
            default => true, // Tenant-wide applies to all
        };
    }

    protected function matchesConditions(
        Carbon $date,
        ?string $time,
        ?string $serviceId,
        ?string $practitionerId,
        ?string $bookingSource,
        ?string $patientType
    ): bool {
        // Check day of week
        $daysCondition = $this->getDaysOfWeekCondition();
        if (!empty($daysCondition) && !in_array($date->dayOfWeek, $daysCondition)) {
            return false;
        }

        // Check date range
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

        // Check time range
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

        // Check booking source
        $bookingSourceCondition = $this->getBookingSourceCondition();
        if (!empty($bookingSourceCondition) && $bookingSource && !in_array($bookingSource, $bookingSourceCondition)) {
            return false;
        }

        // Check patient type
        $patientTypeCondition = $this->getPatientTypeCondition();
        if ($patientTypeCondition && $patientType && $patientTypeCondition !== $patientType) {
            return false;
        }

        return true;
    }

    // ==========================================
    // RULE TYPE CHECKERS
    // ==========================================

    public function isTimeConfigRule(): bool
    {
        return in_array($this->rule_type, [
            self::TYPE_SLOT_DURATION,
            self::TYPE_SLOT_INTERVAL,
            self::TYPE_SLOT_BUFFER,
            self::TYPE_WORKING_HOURS,
            self::TYPE_BREAK_TIME,
            self::TYPE_TIME_RESTRICTION,
            self::TYPE_SPECIAL_HOURS,
        ]);
    }

    public function isCapacityRule(): bool
    {
        return in_array($this->rule_type, [
            self::TYPE_CAPACITY_DAILY,
            self::TYPE_CAPACITY_HOURLY,
            self::TYPE_CAPACITY_PRACTITIONER,
            self::TYPE_CAPACITY_SERVICE,
            self::TYPE_OVERBOOKING,
        ]);
    }

    public function isAdvanceBookingRule(): bool
    {
        return in_array($this->rule_type, [
            self::TYPE_MIN_ADVANCE,
            self::TYPE_MAX_ADVANCE,
            self::TYPE_SAME_DAY,
        ]);
    }

    public function isOnlineBookingRule(): bool
    {
        return in_array($this->rule_type, [
            self::TYPE_ONLINE_ENABLED,
            self::TYPE_ONLINE_SERVICES,
            self::TYPE_ONLINE_HOURS,
            self::TYPE_ONLINE_PRACTITIONER,
        ]);
    }

    public function shouldBlockSlot(): bool
    {
        return $this->rule_type === self::TYPE_BREAK_TIME;
    }

    public function restrictsOnlineBooking(): bool
    {
        return $this->rule_type === self::TYPE_ONLINE_ENABLED && !($this->actions['enabled'] ?? true);
    }

    // ==========================================
    // SCOPES
    // ==========================================

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

    public function scopeByCategory($query, string $category)
    {
        $types = self::RULE_CATEGORIES[$category]['types'] ?? [];
        return $query->whereIn('rule_type', $types);
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

    // ==========================================
    // STATIC HELPERS
    // ==========================================

    public static function getActiveRulesFor(string $branchId, ?string $serviceId = null): \Illuminate\Support\Collection
    {
        return static::active()
            ->applicableTo($branchId, $serviceId)
            ->orderedByPriority()
            ->get();
    }

    public static function getRuleTypesGrouped(): array
    {
        $grouped = [];
        foreach (self::RULE_CATEGORIES as $category => $config) {
            $grouped[$config['label']] = [];
            foreach ($config['types'] as $type) {
                $grouped[$config['label']][$type] = self::RULE_TYPES[$type] ?? $type;
            }
        }
        return $grouped;
    }

    public static function getCategoryForType(string $type): ?string
    {
        foreach (self::RULE_CATEGORIES as $category => $config) {
            if (in_array($type, $config['types'])) {
                return $category;
            }
        }
        return null;
    }
}
