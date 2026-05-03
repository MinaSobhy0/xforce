<?php

namespace Modules\Booking\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Auth\Models\Role;
use Modules\Auth\Models\User;
use Spatie\Translatable\HasTranslations;
use XLinic\Framework\Core\Model\BaseModel;

class TimeOffType extends BaseModel
{
    use HasTranslations;

    protected $table = 'time_off_types';

    public array $translatable = ['name', 'description'];

    // Request unit constants
    public const REQUEST_UNIT_DAY = 'day';
    public const REQUEST_UNIT_HALF_DAY = 'half_day';
    public const REQUEST_UNIT_HOUR = 'hour';

    public const REQUEST_UNITS = [
        self::REQUEST_UNIT_DAY => 'Days',
        self::REQUEST_UNIT_HALF_DAY => 'Half Days',
        self::REQUEST_UNIT_HOUR => 'Hours',
    ];

    // Allocation period constants
    public const ALLOCATION_PERIOD_YEARLY = 'yearly';
    public const ALLOCATION_PERIOD_MONTHLY = 'monthly';

    public const ALLOCATION_PERIODS = [
        self::ALLOCATION_PERIOD_YEARLY => 'Yearly',
        self::ALLOCATION_PERIOD_MONTHLY => 'Monthly',
    ];

    // Approval type constants
    public const APPROVAL_TYPE_ANY = 'any';
    public const APPROVAL_TYPE_ROLES = 'roles';
    public const APPROVAL_TYPE_USERS = 'users';
    public const APPROVAL_TYPE_ROLES_OR_USERS = 'roles_or_users';

    public const APPROVAL_TYPES = [
        self::APPROVAL_TYPE_ANY => 'Any Manager',
        self::APPROVAL_TYPE_ROLES => 'Specific Roles',
        self::APPROVAL_TYPE_USERS => 'Specific Users',
        self::APPROVAL_TYPE_ROLES_OR_USERS => 'Specific Roles or Users',
    ];

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'description',
        'color',
        'is_paid',
        'requires_approval',
        'approval_type',
        'approval_role_ids',
        'approval_user_ids',
        'default_days_per_year',
        'max_days_per_request',
        'min_days_notice',
        'allow_half_day',
        'allow_partial_day',
        'request_unit',
        'allocation_period',
        'hours_per_day',
        'default_allocation',
        'max_per_request',
        'is_active',
        'sort_order',
        'odoo_id',
        'odoo_synced_at',
    ];

    protected $casts = [
        'is_paid' => 'boolean',
        'requires_approval' => 'boolean',
        'approval_role_ids' => 'array',
        'approval_user_ids' => 'array',
        'default_days_per_year' => 'integer',
        'max_days_per_request' => 'integer',
        'min_days_notice' => 'integer',
        'allow_half_day' => 'boolean',
        'allow_partial_day' => 'boolean',
        'hours_per_day' => 'decimal:2',
        'default_allocation' => 'decimal:2',
        'max_per_request' => 'decimal:2',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $attributes = [
        'color' => 'gray',
        'is_paid' => true,
        'requires_approval' => true,
        'approval_type' => self::APPROVAL_TYPE_ANY,
        'default_days_per_year' => 0,
        'min_days_notice' => 0,
        'allow_half_day' => true,
        'allow_partial_day' => false,
        'request_unit' => self::REQUEST_UNIT_DAY,
        'allocation_period' => self::ALLOCATION_PERIOD_YEARLY,
        'hours_per_day' => 8.00,
        'is_active' => true,
        'sort_order' => 0,
    ];

    protected $appends = ['translated_name'];

    /**
     * Get the translated name for the current locale.
     */
    public function getTranslatedNameAttribute(): string
    {
        return $this->getTranslation('name', app()->getLocale())
            ?? $this->getTranslation('name', 'en')
            ?? '';
    }

    public const COLORS = [
        'gray' => 'Gray',
        'primary' => 'Primary',
        'success' => 'Green',
        'info' => 'Blue',
        'warning' => 'Yellow',
        'danger' => 'Red',
    ];

    /**
     * Get allocations for this type.
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(TimeOffAllocation::class);
    }

    /**
     * Get time off requests for this type.
     */
    public function timeOffRequests(): HasMany
    {
        return $this->hasMany(PractitionerTimeOff::class);
    }

    /**
     * Scope to active types only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to ordered types.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Check if a user can approve time off requests of this type.
     */
    public function canBeApprovedBy(User $user): bool
    {
        if (!$this->requires_approval) {
            return true;
        }

        switch ($this->approval_type) {
            case self::APPROVAL_TYPE_ANY:
                // Any user with approval permission can approve
                return $user->can('approve-time-off') || $user->hasRole(['super-admin', 'admin', 'manager']);

            case self::APPROVAL_TYPE_ROLES:
                // Only users with specific roles can approve
                $roleIds = $this->approval_role_ids ?? [];
                if (empty($roleIds)) {
                    return false;
                }
                return $user->roles()->whereIn('id', $roleIds)->exists();

            case self::APPROVAL_TYPE_USERS:
                // Only specific users can approve
                $userIds = $this->approval_user_ids ?? [];
                return in_array($user->id, $userIds);

            case self::APPROVAL_TYPE_ROLES_OR_USERS:
                // Either specific roles or specific users can approve
                $roleIds = $this->approval_role_ids ?? [];
                $userIds = $this->approval_user_ids ?? [];

                if (in_array($user->id, $userIds)) {
                    return true;
                }

                if (!empty($roleIds) && $user->roles()->whereIn('id', $roleIds)->exists()) {
                    return true;
                }

                return false;

            default:
                return false;
        }
    }

    /**
     * Get the approval roles.
     */
    public function getApprovalRoles()
    {
        $roleIds = $this->approval_role_ids ?? [];
        if (empty($roleIds)) {
            return collect();
        }

        return Role::whereIn('id', $roleIds)->get();
    }

    /**
     * Get the approval users.
     */
    public function getApprovalUsers()
    {
        $userIds = $this->approval_user_ids ?? [];
        if (empty($userIds)) {
            return collect();
        }

        return User::whereIn('id', $userIds)->get();
    }

    /**
     * Check if this type uses hours as the request unit.
     */
    public function isHourBased(): bool
    {
        return $this->request_unit === self::REQUEST_UNIT_HOUR;
    }

    /**
     * Check if this type uses half days as the request unit.
     */
    public function isHalfDayBased(): bool
    {
        return $this->request_unit === self::REQUEST_UNIT_HALF_DAY;
    }

    /**
     * Check if this type uses days as the request unit.
     */
    public function isDayBased(): bool
    {
        return $this->request_unit === self::REQUEST_UNIT_DAY;
    }

    /**
     * Check if allocation is monthly.
     */
    public function isMonthly(): bool
    {
        return $this->allocation_period === self::ALLOCATION_PERIOD_MONTHLY;
    }

    /**
     * Check if allocation is yearly.
     */
    public function isYearly(): bool
    {
        return $this->allocation_period === self::ALLOCATION_PERIOD_YEARLY;
    }

    /**
     * Get the unit label.
     *
     * Note: half-day types store values in days; the half_day request_unit only controls
     * request granularity (allowing 0.5 increments). So the displayed unit for both
     * `day` and `half_day` types is "Days". Only `hour` types are stored and displayed in hours.
     */
    public function getUnitLabel(): string
    {
        return match ($this->request_unit) {
            self::REQUEST_UNIT_HOUR => __('booking::time_off.request_units.hour'),
            default => __('booking::time_off.request_units.day'),
        };
    }

    /**
     * Get the unit label for a single item.
     */
    public function getSingularUnitLabel(): string
    {
        return match ($this->request_unit) {
            self::REQUEST_UNIT_HOUR => __('booking::time_off.request_units_singular.hour'),
            default => __('booking::time_off.request_units_singular.day'),
        };
    }

    /**
     * Convert a value to hours based on request unit.
     */
    public function convertToHours(float $value): float
    {
        return match ($this->request_unit) {
            self::REQUEST_UNIT_HOUR => $value,
            self::REQUEST_UNIT_HALF_DAY => $value * ($this->hours_per_day / 2),
            default => $value * $this->hours_per_day,
        };
    }

    /**
     * Convert hours to the request unit value.
     */
    public function convertFromHours(float $hours): float
    {
        return match ($this->request_unit) {
            self::REQUEST_UNIT_HOUR => $hours,
            self::REQUEST_UNIT_HALF_DAY => $hours / ($this->hours_per_day / 2),
            default => $hours / $this->hours_per_day,
        };
    }

    /**
     * Convert a value to days.
     */
    public function convertToDays(float $value): float
    {
        return match ($this->request_unit) {
            self::REQUEST_UNIT_HOUR => $value / $this->hours_per_day,
            self::REQUEST_UNIT_HALF_DAY => $value / 2,
            default => $value,
        };
    }

    /**
     * Get the effective default allocation based on request unit.
     * Returns default_allocation if set, otherwise converts default_days_per_year.
     */
    public function getEffectiveDefaultAllocation(): float
    {
        if ($this->default_allocation !== null) {
            return (float) $this->default_allocation;
        }

        // Convert legacy default_days_per_year to appropriate unit
        if ($this->isHourBased()) {
            return $this->default_days_per_year * $this->hours_per_day;
        }

        if ($this->isHalfDayBased()) {
            return $this->default_days_per_year * 2;
        }

        return (float) $this->default_days_per_year;
    }

    /**
     * Get the effective max per request based on request unit.
     */
    public function getEffectiveMaxPerRequest(): ?float
    {
        if ($this->max_per_request !== null) {
            return (float) $this->max_per_request;
        }

        if ($this->max_days_per_request === null) {
            return null;
        }

        // Convert legacy max_days_per_request to appropriate unit
        if ($this->isHourBased()) {
            return $this->max_days_per_request * $this->hours_per_day;
        }

        if ($this->isHalfDayBased()) {
            return $this->max_days_per_request * 2;
        }

        return (float) $this->max_days_per_request;
    }

    /**
     * Format a value with the appropriate unit label.
     */
    public function formatValue(float $value): string
    {
        $formatted = number_format($value, $this->isHourBased() ? 1 : 1);
        return $formatted . ' ' . $this->getUnitLabel();
    }
}
