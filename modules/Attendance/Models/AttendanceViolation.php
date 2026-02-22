<?php

namespace Modules\Attendance\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Auth\Models\User;
use Modules\Staff\Models\StaffProfile;
use XLinic\Framework\Core\Model\BaseModel;

class AttendanceViolation extends BaseModel
{
    use SoftDeletes;

    protected $table = 'attendance_violations';

    protected $fillable = [
        'tenant_id',
        'attendance_id',
        'staff_profile_id',
        'attendance_rule_id',
        'attendance_rule_action_id',
        'violation_type',
        'violation_date',
        'scheduled_time',
        'actual_time',
        'grace_period_minutes',
        'violation_minutes',
        'penalty_amount_minor',
        'penalty_type',
        'penalty_calculation_details',
        'status',
        'reason',
        'employee_notes',
        'manager_notes',
        'approved_by',
        'approved_at',
        'waived_by',
        'waived_reason',
        'waived_at',
        'dispute_reason',
        'disputed_at',
        'payroll_line_id',
        'applied_at',
    ];

    protected $casts = [
        'id' => 'string',
        'violation_date' => 'date',
        'scheduled_time' => 'datetime:H:i:s',
        'actual_time' => 'datetime:H:i:s',
        'grace_period_minutes' => 'integer',
        'violation_minutes' => 'integer',
        'penalty_amount_minor' => 'integer',
        'penalty_calculation_details' => 'array',
        'approved_at' => 'datetime',
        'waived_at' => 'datetime',
        'disputed_at' => 'datetime',
        'applied_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $attributes = [
        'grace_period_minutes' => 0,
        'violation_minutes' => 0,
        'penalty_amount_minor' => 0,
        'status' => self::STATUS_PENDING,
    ];

    // Violation Types (same as AttendanceRule categories)
    public const TYPE_LATE_CHECKIN = 'late_checkin';
    public const TYPE_EARLY_CHECKOUT = 'early_checkout';
    public const TYPE_MISSED_CHECKIN = 'missed_checkin';
    public const TYPE_MISSED_CHECKOUT = 'missed_checkout';
    public const TYPE_OVERSTAY = 'overstay';
    public const TYPE_UNAUTHORIZED_ABSENCE = 'unauthorized_absence';

    public const TYPES = [
        self::TYPE_LATE_CHECKIN => 'Late Check-In',
        self::TYPE_EARLY_CHECKOUT => 'Early Check-Out',
        self::TYPE_MISSED_CHECKIN => 'Missed Check-In',
        self::TYPE_MISSED_CHECKOUT => 'Missed Check-Out',
        self::TYPE_OVERSTAY => 'Overstay',
        self::TYPE_UNAUTHORIZED_ABSENCE => 'Unauthorized Absence',
    ];

    public const TYPE_COLORS = [
        self::TYPE_LATE_CHECKIN => 'warning',
        self::TYPE_EARLY_CHECKOUT => 'danger',
        self::TYPE_MISSED_CHECKIN => 'danger',
        self::TYPE_MISSED_CHECKOUT => 'warning',
        self::TYPE_OVERSTAY => 'info',
        self::TYPE_UNAUTHORIZED_ABSENCE => 'danger',
    ];

    // Status
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_WAIVED = 'waived';
    public const STATUS_DISPUTED = 'disputed';
    public const STATUS_APPLIED = 'applied';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING => 'Pending Review',
        self::STATUS_APPROVED => 'Approved',
        self::STATUS_WAIVED => 'Waived',
        self::STATUS_DISPUTED => 'Disputed',
        self::STATUS_APPLIED => 'Applied to Payslip',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    public const STATUS_COLORS = [
        self::STATUS_PENDING => 'warning',
        self::STATUS_APPROVED => 'success',
        self::STATUS_WAIVED => 'gray',
        self::STATUS_DISPUTED => 'danger',
        self::STATUS_APPLIED => 'info',
        self::STATUS_CANCELLED => 'gray',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Get the attendance record.
     */
    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class, 'attendance_id');
    }

    /**
     * Get the staff profile.
     */
    public function staffProfile(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'staff_profile_id');
    }

    /**
     * Get the attendance rule.
     */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(AttendanceRule::class, 'attendance_rule_id');
    }

    /**
     * Get the rule action.
     */
    public function ruleAction(): BelongsTo
    {
        return $this->belongsTo(AttendanceRuleAction::class, 'attendance_rule_action_id');
    }

    /**
     * Get the approver.
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the waiver.
     */
    public function waivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waived_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Scope for pending violations.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for approved violations.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Scope for waived violations.
     */
    public function scopeWaived($query)
    {
        return $query->where('status', self::STATUS_WAIVED);
    }

    /**
     * Scope for disputed violations.
     */
    public function scopeDisputed($query)
    {
        return $query->where('status', self::STATUS_DISPUTED);
    }

    /**
     * Scope for applied violations.
     */
    public function scopeApplied($query)
    {
        return $query->where('status', self::STATUS_APPLIED);
    }

    /**
     * Scope for date range.
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('violation_date', [$startDate, $endDate]);
    }

    /**
     * Scope by staff.
     */
    public function scopeByStaff($query, $staffProfileId)
    {
        return $query->where('staff_profile_id', $staffProfileId);
    }

    /**
     * Scope by violation type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('violation_type', $type);
    }

    /**
     * Scope for unapplied violations (pending or approved but not yet deducted).
     */
    public function scopeUnapplied($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_APPROVED])
            ->whereNull('payroll_line_id');
    }

    /**
     * Scope for ready to apply (approved and not yet applied).
     */
    public function scopeReadyToApply($query)
    {
        return $query->where('status', self::STATUS_APPROVED)
            ->whereNull('payroll_line_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Check if violation can be waived.
     */
    public function canBeWaived(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING,
            self::STATUS_APPROVED,
            self::STATUS_DISPUTED,
        ]);
    }

    /**
     * Check if violation can be approved.
     */
    public function canBeApproved(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING,
            self::STATUS_DISPUTED,
        ]);
    }

    /**
     * Check if violation can be disputed.
     */
    public function canBeDisputed(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING,
            self::STATUS_APPROVED,
        ]);
    }

    /**
     * Check if violation can be applied to payroll.
     */
    public function canBeApplied(): bool
    {
        return $this->status === self::STATUS_APPROVED
            && $this->payroll_line_id === null
            && $this->penalty_amount_minor > 0;
    }

    /**
     * Approve the violation.
     */
    public function approve(User $approver, ?string $notes = null): bool
    {
        if (!$this->canBeApproved()) {
            return false;
        }

        $this->status = self::STATUS_APPROVED;
        $this->approved_by = $approver->id;
        $this->approved_at = now();

        if ($notes) {
            $this->manager_notes = $notes;
        }

        return $this->save();
    }

    /**
     * Waive the violation.
     */
    public function waive(User $waiver, string $reason): bool
    {
        if (!$this->canBeWaived()) {
            return false;
        }

        $this->status = self::STATUS_WAIVED;
        $this->waived_by = $waiver->id;
        $this->waived_reason = $reason;
        $this->waived_at = now();

        return $this->save();
    }

    /**
     * Dispute the violation.
     */
    public function dispute(string $reason): bool
    {
        if (!$this->canBeDisputed()) {
            return false;
        }

        $this->status = self::STATUS_DISPUTED;
        $this->dispute_reason = $reason;
        $this->disputed_at = now();

        return $this->save();
    }

    /**
     * Cancel the violation.
     */
    public function cancel(): bool
    {
        if ($this->status === self::STATUS_APPLIED) {
            return false;
        }

        $this->status = self::STATUS_CANCELLED;

        return $this->save();
    }

    /**
     * Mark as applied to payroll.
     */
    public function markAsApplied(string $payrollLineId): bool
    {
        if (!$this->canBeApplied()) {
            return false;
        }

        $this->status = self::STATUS_APPLIED;
        $this->payroll_line_id = $payrollLineId;
        $this->applied_at = now();

        return $this->save();
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    /**
     * Get formatted violation time.
     */
    public function getFormattedViolationTimeAttribute(): string
    {
        $scheduled = $this->scheduled_time
            ? Carbon::parse($this->scheduled_time)->format('h:i A')
            : '--:--';
        $actual = $this->actual_time
            ? Carbon::parse($this->actual_time)->format('h:i A')
            : '--:--';

        return "Scheduled: {$scheduled}, Actual: {$actual}";
    }

    /**
     * Get formatted violation duration.
     */
    public function getFormattedViolationDurationAttribute(): string
    {
        $hours = intdiv($this->violation_minutes, 60);
        $minutes = $this->violation_minutes % 60;

        if ($hours > 0) {
            return "{$hours}h {$minutes}m";
        }

        return "{$minutes}m";
    }

    /**
     * Get penalty amount in major units.
     */
    public function getPenaltyAmountAttribute(): float
    {
        return $this->penalty_amount_minor / 100;
    }

    /**
     * Get violation type label.
     */
    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->violation_type] ?? $this->violation_type;
    }

    /**
     * Get status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * Get formatted penalty.
     */
    public function getFormattedPenaltyAttribute(): string
    {
        if ($this->penalty_amount_minor === 0) {
            return 'No Deduction';
        }

        return number_format($this->penalty_amount, 2) . ' EGP';
    }

    /**
     * Check if violation is actionable (can be approved/waived/disputed).
     */
    public function getIsActionableAttribute(): bool
    {
        return $this->canBeApproved() || $this->canBeWaived() || $this->canBeDisputed();
    }
}
