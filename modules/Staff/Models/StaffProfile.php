<?php

namespace Modules\Staff\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Auth\Models\User;
use Modules\Booking\Models\PractitionerScheduleAssignment;
use Modules\Booking\Models\WorkSchedule;
use Modules\Core\Models\Branch;
use Modules\Payroll\Models\EmployeeSalaryComponent;
use Modules\Payroll\Models\EmployeeSalaryStructure;
use Spatie\Translatable\HasTranslations;
use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasActivity;

class StaffProfile extends BaseModel
{
    use HasTranslations;
    use HasActivity;

    protected $table = 'staff_profiles';

    public array $translatable = ['bio', 'specializations'];

    protected $fillable = [
        'tenant_id',
        'user_id',
        'branch_id',
        'commission_plan_id',
        'employee_number',
        'job_title',
        'bio',
        'specializations',
        'commission_type', // Deprecated - use commission_plan_id
        'commission_percentage', // Deprecated - use commission_plan_id
        'base_salary_minor',
        'hire_date',
        'contract_end_date',
        'is_active',
    ];

    protected $casts = [
        'bio' => 'array',
        'specializations' => 'array',
        'commission_percentage' => 'decimal:2',
        'base_salary_minor' => 'integer',
        'hire_date' => 'date',
        'contract_end_date' => 'date',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'commission_type' => self::COMMISSION_PERCENTAGE,
        'commission_percentage' => 10.00,
        'base_salary_minor' => 0,
        'is_active' => true,
    ];

    // Commission types
    public const COMMISSION_FLAT = 'flat';
    public const COMMISSION_PERCENTAGE = 'percentage';
    public const COMMISSION_TIERED = 'tiered';

    public const COMMISSION_TYPES = [
        self::COMMISSION_FLAT => 'Flat Amount',
        self::COMMISSION_PERCENTAGE => 'Percentage',
        self::COMMISSION_TIERED => 'Tiered',
    ];

    /**
     * Get the user.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the branch.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get the assigned commission plan.
     */
    public function commissionPlan(): BelongsTo
    {
        return $this->belongsTo(CommissionPlan::class, 'commission_plan_id');
    }

    /**
     * Get commission rules (deprecated - use commissionPlan instead).
     * @deprecated Use commissionPlan()->serviceRules() instead
     */
    public function commissionRules(): HasMany
    {
        return $this->hasMany(StaffCommission::class);
    }

    /**
     * Get commission records.
     */
    public function commissionRecords(): HasMany
    {
        return $this->hasMany(StaffCommissionRecord::class);
    }

    /**
     * Get schedule assignments for this staff member.
     */
    public function scheduleAssignments(): HasMany
    {
        return $this->hasMany(PractitionerScheduleAssignment::class, 'staff_profile_id');
    }

    /**
     * Get work schedules for this staff member.
     */
    public function workSchedules(): BelongsToMany
    {
        return $this->belongsToMany(WorkSchedule::class, 'practitioner_schedule_assignments', 'staff_profile_id', 'work_schedule_id')
            ->withPivot(['effective_from', 'effective_until', 'day_overrides', 'is_primary', 'is_active', 'notes'])
            ->withTimestamps();
    }

    /**
     * Get active work schedules for this staff member.
     */
    public function activeWorkSchedules(): BelongsToMany
    {
        return $this->workSchedules()
            ->wherePivot('is_active', true)
            ->where(function ($query) {
                $query->whereNull('practitioner_schedule_assignments.effective_from')
                    ->orWhere('practitioner_schedule_assignments.effective_from', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('practitioner_schedule_assignments.effective_until')
                    ->orWhere('practitioner_schedule_assignments.effective_until', '>=', now());
            });
    }

    /**
     * Get the primary work schedule for this staff member.
     */
    public function primaryWorkSchedule(): ?WorkSchedule
    {
        return $this->activeWorkSchedules()
            ->wherePivot('is_primary', true)
            ->first();
    }

    /**
     * Get all salary structure assignments.
     */
    public function salaryStructures(): HasMany
    {
        return $this->hasMany(EmployeeSalaryStructure::class, 'staff_profile_id');
    }

    /**
     * Get the current salary structure assignment.
     */
    public function currentSalaryStructure(): HasOne
    {
        return $this->hasOne(EmployeeSalaryStructure::class, 'staff_profile_id')
            ->where('is_current', true);
    }

    /**
     * Get all salary components for this employee.
     */
    public function salaryComponents(): HasMany
    {
        return $this->hasMany(EmployeeSalaryComponent::class, 'staff_profile_id');
    }

    /**
     * Get active and effective salary components.
     */
    public function activeSalaryComponents(): HasMany
    {
        return $this->salaryComponents()->active()->effective();
    }

    /**
     * Scope to active staff only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get total earnings for a period.
     */
    public function getEarningsForPeriod(string $startDate, string $endDate): int
    {
        return $this->commissionRecords()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', StaffCommissionRecord::STATUS_APPROVED)
            ->sum('amount_minor');
    }

    /**
     * Get pending earnings.
     */
    public function getPendingEarningsAttribute(): int
    {
        return $this->commissionRecords()
            ->where('status', StaffCommissionRecord::STATUS_PENDING)
            ->sum('amount_minor');
    }

    /**
     * Get total paid earnings.
     */
    public function getTotalPaidAttribute(): int
    {
        return $this->commissionRecords()
            ->where('status', StaffCommissionRecord::STATUS_PAID)
            ->sum('amount_minor');
    }

    /**
     * Get base salary in major units.
     */
    public function getBaseSalaryAttribute(): float
    {
        return $this->base_salary_minor / 100;
    }

    /**
     * Calculate commission for an amount.
     *
     * @param int $amountMinor Revenue amount in minor units
     * @param string|null $serviceId Optional service ID for specific rules
     * @param string|null $categoryId Optional category ID for fallback rules
     * @return int Commission amount in minor units
     */
    public function calculateCommission(int $amountMinor, ?string $serviceId = null, ?string $categoryId = null): int
    {
        // Use commission plan if assigned
        if ($this->commission_plan_id && $this->commissionPlan) {
            return $this->commissionPlan->calculateCommission($amountMinor, $serviceId, $categoryId);
        }

        // Fallback to legacy commission settings (deprecated)
        return $this->calculateLegacyCommission($amountMinor, $serviceId);
    }

    /**
     * Calculate commission using legacy per-staff settings.
     * @deprecated This method is for backward compatibility only.
     */
    protected function calculateLegacyCommission(int $amountMinor, ?string $serviceId = null): int
    {
        // Check if there's a specific commission rule for this service
        if ($serviceId) {
            $rule = $this->commissionRules()
                ->where('service_id', $serviceId)
                ->where('is_active', true)
                ->first();

            if ($rule) {
                return $rule->calculateAmount($amountMinor);
            }
        }

        // Use default commission settings
        switch ($this->commission_type) {
            case self::COMMISSION_FLAT:
                return (int) ($this->commission_percentage * 100);

            case self::COMMISSION_PERCENTAGE:
                return (int) ($amountMinor * $this->commission_percentage / 100);

            case self::COMMISSION_TIERED:
                $tierRule = $this->commissionRules()
                    ->where('commission_type', 'tiered')
                    ->where('tier_from_minor', '<=', $amountMinor)
                    ->where(function ($q) use ($amountMinor) {
                        $q->whereNull('tier_to_minor')
                          ->orWhere('tier_to_minor', '>=', $amountMinor);
                    })
                    ->where('is_active', true)
                    ->first();

                if ($tierRule) {
                    return $tierRule->calculateAmount($amountMinor);
                }

                return (int) ($amountMinor * $this->commission_percentage / 100);

            default:
                return 0;
        }
    }

    /**
     * Check if staff has a commission plan assigned.
     */
    public function hasCommissionPlan(): bool
    {
        return $this->commission_plan_id !== null;
    }
}
