<?php

namespace Modules\Attendance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Modules\Staff\Models\StaffProfile;

class AttendanceRuleAction extends Model
{
    protected $table = 'attendance_rule_actions';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'attendance_rule_id',
        'occurrence_number',
        'action_type',
        'severity',
        'threshold_type',
        'threshold_value',
        'threshold_period',
        'penalty_type',
        'penalty_amount_minor',
        'penalty_percentage',
        'penalty_formula',
        'requires_approval',
        'notification_enabled',
        'notify_manager',
        'notify_hr',
        'message_template',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'id' => 'string',
        'occurrence_number' => 'integer',
        'threshold_value' => 'integer',
        'penalty_amount_minor' => 'integer',
        'penalty_percentage' => 'decimal:2',
        'requires_approval' => 'boolean',
        'notification_enabled' => 'boolean',
        'notify_manager' => 'boolean',
        'notify_hr' => 'boolean',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'severity' => self::SEVERITY_MODERATE,
        'penalty_amount_minor' => 0,
        'requires_approval' => false,
        'notification_enabled' => true,
        'notify_manager' => true,
        'notify_hr' => false,
        'is_active' => true,
    ];

    // Action Types
    public const ACTION_DEDUCTION = 'deduction';
    public const ACTION_WARNING = 'warning';
    public const ACTION_APPROVAL_REQUIRED = 'approval_required';
    public const ACTION_NOTIFICATION = 'notification';
    public const ACTION_BLOCK = 'block_attendance';

    public const ACTION_TYPES = [
        self::ACTION_DEDUCTION => 'Salary Deduction',
        self::ACTION_WARNING => 'Warning Notice',
        self::ACTION_APPROVAL_REQUIRED => 'Require Approval',
        self::ACTION_NOTIFICATION => 'Send Notification',
        self::ACTION_BLOCK => 'Block Attendance',
    ];

    public const ACTION_TYPE_COLORS = [
        self::ACTION_DEDUCTION => 'danger',
        self::ACTION_WARNING => 'warning',
        self::ACTION_APPROVAL_REQUIRED => 'info',
        self::ACTION_NOTIFICATION => 'primary',
        self::ACTION_BLOCK => 'danger',
    ];

    // Penalty Types
    public const PENALTY_FIXED = 'fixed';
    public const PENALTY_PERCENTAGE = 'percentage';
    public const PENALTY_HOURLY = 'hourly_rate';
    public const PENALTY_FORMULA = 'formula';

    public const PENALTY_TYPES = [
        self::PENALTY_FIXED => 'Fixed Amount',
        self::PENALTY_PERCENTAGE => 'Percentage of Daily Salary',
        self::PENALTY_HOURLY => 'Hourly Rate Deduction',
        self::PENALTY_FORMULA => 'Custom Formula',
    ];

    // Threshold Types
    public const THRESHOLD_TIME = 'time_based';
    public const THRESHOLD_OCCURRENCE = 'occurrence_based';

    public const THRESHOLD_TYPES = [
        self::THRESHOLD_TIME => 'Time Based (Minutes)',
        self::THRESHOLD_OCCURRENCE => 'Occurrence Based (Count)',
    ];

    // Threshold Periods
    public const PERIOD_DAY = 'day';
    public const PERIOD_WEEK = 'week';
    public const PERIOD_MONTH = 'month';
    public const PERIOD_YEAR = 'year';

    public const PERIODS = [
        self::PERIOD_DAY => 'Per Day',
        self::PERIOD_WEEK => 'Per Week',
        self::PERIOD_MONTH => 'Per Month',
        self::PERIOD_YEAR => 'Per Year',
    ];

    // Severity
    public const SEVERITY_MINOR = 'minor';
    public const SEVERITY_MODERATE = 'moderate';
    public const SEVERITY_SEVERE = 'severe';

    public const SEVERITIES = [
        self::SEVERITY_MINOR => 'Minor',
        self::SEVERITY_MODERATE => 'Moderate',
        self::SEVERITY_SEVERE => 'Severe',
    ];

    public const SEVERITY_COLORS = [
        self::SEVERITY_MINOR => 'gray',
        self::SEVERITY_MODERATE => 'warning',
        self::SEVERITY_SEVERE => 'danger',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Get the attendance rule.
     */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(AttendanceRule::class, 'attendance_rule_id');
    }

    /**
     * Get the violations using this action.
     */
    public function violations(): HasMany
    {
        return $this->hasMany(AttendanceViolation::class, 'attendance_rule_action_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Calculate penalty amount in minor units.
     */
    public function calculatePenalty(StaffProfile $staff, int $violationMinutes = 0): int
    {
        if ($this->action_type !== self::ACTION_DEDUCTION) {
            return 0;
        }

        switch ($this->penalty_type) {
            case self::PENALTY_FIXED:
                return $this->penalty_amount_minor;

            case self::PENALTY_PERCENTAGE:
                return $this->calculatePercentagePenalty($staff);

            case self::PENALTY_HOURLY:
                return $this->calculateHourlyPenalty($staff, $violationMinutes);

            case self::PENALTY_FORMULA:
                return $this->evaluateFormula($staff, $violationMinutes);

            default:
                return 0;
        }
    }

    /**
     * Calculate percentage-based penalty.
     */
    public function calculatePercentagePenalty(StaffProfile $staff): int
    {
        // Get staff's daily salary in minor units
        $dailySalary = $this->getStaffDailySalary($staff);

        if (!$dailySalary || !$this->penalty_percentage) {
            return 0;
        }

        return (int) round($dailySalary * ($this->penalty_percentage / 100));
    }

    /**
     * Calculate hourly rate penalty.
     */
    public function calculateHourlyPenalty(StaffProfile $staff, int $violationMinutes): int
    {
        $hourlySalary = $this->getStaffHourlySalary($staff);

        if (!$hourlySalary) {
            return 0;
        }

        $violationHours = $violationMinutes / 60;

        return (int) round($hourlySalary * $violationHours);
    }

    /**
     * Evaluate formula-based penalty.
     */
    public function evaluateFormula(StaffProfile $staff, int $violationMinutes): int
    {
        if (!$this->penalty_formula) {
            return 0;
        }

        // Build context for formula evaluation
        $context = [
            'violation_minutes' => $violationMinutes,
            'daily_salary' => $this->getStaffDailySalary($staff),
            'hourly_salary' => $this->getStaffHourlySalary($staff),
            'base_salary' => $this->getStaffBaseSalary($staff),
        ];

        // Try to evaluate using FormulaEvaluator from Payroll module if available
        try {
            $evaluator = app(\Modules\Payroll\Services\FormulaEvaluator::class);

            return (int) $evaluator->evaluate($this->penalty_formula, $context);
        } catch (\Exception $e) {
            // Fallback to fixed amount
            return $this->penalty_amount_minor;
        }
    }

    /**
     * Get staff daily salary in minor units.
     */
    protected function getStaffDailySalary(StaffProfile $staff): int
    {
        // Get from current salary structure
        $baseSalary = $this->getStaffBaseSalary($staff);

        // Assume monthly salary, divide by average working days (22)
        return (int) round($baseSalary / 22);
    }

    /**
     * Get staff hourly salary in minor units.
     */
    protected function getStaffHourlySalary(StaffProfile $staff): int
    {
        $dailySalary = $this->getStaffDailySalary($staff);

        // Assume 8 working hours per day
        return (int) round($dailySalary / 8);
    }

    /**
     * Get staff base salary in minor units.
     */
    protected function getStaffBaseSalary(StaffProfile $staff): int
    {
        // Get from EmployeeSalaryStructure
        $salaryStructure = $staff->salaryStructures()
            ->where('is_current', true)
            ->first();

        return $salaryStructure ? $salaryStructure->base_salary_minor : 0;
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    /**
     * Get threshold description.
     */
    public function getThresholdDescriptionAttribute(): string
    {
        if ($this->threshold_type === self::THRESHOLD_TIME) {
            $hours = intdiv($this->threshold_value, 60);
            $minutes = $this->threshold_value % 60;

            if ($hours > 0) {
                return "{$hours}h {$minutes}m late";
            }

            return "{$minutes} minutes late";
        }

        $period = self::PERIODS[$this->threshold_period] ?? '';

        return "{$this->threshold_value} occurrences {$period}";
    }

    /**
     * Get penalty description.
     */
    public function getPenaltyDescriptionAttribute(): string
    {
        if ($this->action_type !== self::ACTION_DEDUCTION) {
            return self::ACTION_TYPES[$this->action_type] ?? $this->action_type;
        }

        switch ($this->penalty_type) {
            case self::PENALTY_FIXED:
                $amount = $this->penalty_amount_minor / 100;

                return "Deduct {$amount} EGP";

            case self::PENALTY_PERCENTAGE:
                return "Deduct {$this->penalty_percentage}% of daily salary";

            case self::PENALTY_HOURLY:
                return 'Deduct hourly rate x violation hours';

            case self::PENALTY_FORMULA:
                return 'Custom formula calculation';

            default:
                return 'No penalty';
        }
    }

    /**
     * Get action type label.
     */
    public function getActionTypeLabelAttribute(): string
    {
        return self::ACTION_TYPES[$this->action_type] ?? $this->action_type;
    }

    /**
     * Get severity label.
     */
    public function getSeverityLabelAttribute(): string
    {
        return self::SEVERITIES[$this->severity] ?? $this->severity;
    }

    /**
     * Get penalty amount in major units.
     */
    public function getPenaltyAmountAttribute(): float
    {
        return $this->penalty_amount_minor / 100;
    }

    /*
    |--------------------------------------------------------------------------
    | Boot
    |--------------------------------------------------------------------------
    */

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }
}
