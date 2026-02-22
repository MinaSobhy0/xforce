<?php

namespace Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Payroll\Events\PayrollPaid;
use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasSequence;

class PayrollRun extends BaseModel
{
    use HasSequence;

    protected $table = 'payroll_runs';

    protected string $sequenceCode = 'PAY';

    protected string $sequenceColumn = 'run_number';

    protected $fillable = [
        'tenant_id',
        'run_number',
        'period_year',
        'period_month',
        'status',
        'total_base_salary_minor',
        'total_commissions_minor',
        'total_bonuses_minor',
        'total_deductions_minor',
        'total_net_salary_minor',
        'employee_count',
        'approved_by',
        'approved_at',
        'paid_by',
        'paid_at',
        'notes',
    ];

    protected $casts = [
        'id' => 'string',
        'period_year' => 'integer',
        'period_month' => 'integer',
        'total_base_salary_minor' => 'integer',
        'total_commissions_minor' => 'integer',
        'total_bonuses_minor' => 'integer',
        'total_deductions_minor' => 'integer',
        'total_net_salary_minor' => 'integer',
        'employee_count' => 'integer',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
        'total_base_salary_minor' => 0,
        'total_commissions_minor' => 0,
        'total_bonuses_minor' => 0,
        'total_deductions_minor' => 0,
        'total_net_salary_minor' => 0,
        'employee_count' => 0,
    ];

    // Status constants
    public const STATUS_DRAFT = 'draft';
    public const STATUS_CALCULATING = 'calculating';
    public const STATUS_REVIEW = 'review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_CALCULATING => 'Calculating',
        self::STATUS_REVIEW => 'Under Review',
        self::STATUS_APPROVED => 'Approved',
        self::STATUS_PROCESSING => 'Processing',
        self::STATUS_PAID => 'Paid',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    public const STATUS_COLORS = [
        self::STATUS_DRAFT => 'gray',
        self::STATUS_CALCULATING => 'info',
        self::STATUS_REVIEW => 'warning',
        self::STATUS_APPROVED => 'success',
        self::STATUS_PROCESSING => 'info',
        self::STATUS_PAID => 'success',
        self::STATUS_CANCELLED => 'danger',
    ];

    // State transitions
    public const TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_CALCULATING, self::STATUS_CANCELLED],
        self::STATUS_CALCULATING => [self::STATUS_REVIEW, self::STATUS_DRAFT],
        self::STATUS_REVIEW => [self::STATUS_APPROVED, self::STATUS_CALCULATING, self::STATUS_CANCELLED],
        self::STATUS_APPROVED => [self::STATUS_PROCESSING, self::STATUS_REVIEW, self::STATUS_CANCELLED],
        self::STATUS_PROCESSING => [self::STATUS_PAID],
        self::STATUS_PAID => [],
        self::STATUS_CANCELLED => [self::STATUS_DRAFT],
    ];

    /**
     * Get payroll lines.
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PayrollLine::class);
    }

    /**
     * Get the approver.
     */
    public function approvedBy()
    {
        return $this->belongsTo(\Modules\Auth\Models\User::class, 'approved_by');
    }

    /**
     * Get who paid.
     */
    public function paidBy()
    {
        return $this->belongsTo(\Modules\Auth\Models\User::class, 'paid_by');
    }

    /**
     * Check if status transition is allowed.
     */
    public function canTransitionTo(string $newStatus): bool
    {
        return in_array($newStatus, self::TRANSITIONS[$this->status] ?? []);
    }

    /**
     * Get period label.
     */
    public function getPeriodLabelAttribute(): string
    {
        return date('F Y', mktime(0, 0, 0, $this->period_month, 1, $this->period_year));
    }

    /**
     * Approve the payroll run.
     */
    public function approve(?string $userId = null): bool
    {
        if (!$this->canTransitionTo(self::STATUS_APPROVED)) {
            return false;
        }

        $this->status = self::STATUS_APPROVED;
        $this->approved_by = $userId ?? auth()->id();
        $this->approved_at = now();

        return $this->save();
    }

    /**
     * Mark as paid (handles both direct payment and processing → paid).
     */
    public function markAsPaid(?string $userId = null): bool
    {
        // Allow direct transition from approved or processing
        if (!in_array($this->status, [self::STATUS_APPROVED, self::STATUS_PROCESSING])) {
            return false;
        }

        $this->status = self::STATUS_PAID;
        $this->paid_by = $userId ?? auth()->id();
        $this->paid_at = now();
        $this->save();

        // Mark all commission records as paid
        foreach ($this->lines as $line) {
            $line->markCommissionsAsPaid();
        }

        // Fire event for journal entry creation
        event(new PayrollPaid($this));

        return true;
    }

    /**
     * Cancel the payroll run.
     */
    public function cancel(): bool
    {
        if (!$this->canTransitionTo(self::STATUS_CANCELLED)) {
            return false;
        }

        $this->status = self::STATUS_CANCELLED;
        return $this->save();
    }

    /**
     * Start calculation process.
     */
    public function startCalculation(): bool
    {
        if (!$this->canTransitionTo(self::STATUS_CALCULATING)) {
            return false;
        }

        $this->status = self::STATUS_CALCULATING;
        return $this->save();
    }

    /**
     * Mark as ready for review.
     */
    public function markAsReview(): bool
    {
        if (!$this->canTransitionTo(self::STATUS_REVIEW)) {
            return false;
        }

        $this->status = self::STATUS_REVIEW;
        return $this->save();
    }

    /**
     * Start payment processing.
     */
    public function startProcessing(): bool
    {
        if (!$this->canTransitionTo(self::STATUS_PROCESSING)) {
            return false;
        }

        $this->status = self::STATUS_PROCESSING;
        return $this->save();
    }

    /**
     * Reset to draft for recalculation.
     */
    public function resetToDraft(): bool
    {
        if (!$this->canTransitionTo(self::STATUS_DRAFT)) {
            return false;
        }

        $this->status = self::STATUS_DRAFT;
        return $this->save();
    }

    /**
     * Recalculate totals from lines.
     */
    public function recalculateTotals(): void
    {
        $this->total_base_salary_minor = $this->lines()->sum('base_salary_minor');
        $this->total_commissions_minor = $this->lines()->sum('commissions_minor');
        $this->total_bonuses_minor = $this->lines()->sum('bonuses_minor');
        $this->total_deductions_minor = $this->lines()->sum('deductions_minor');
        $this->total_net_salary_minor = $this->lines()->sum('net_salary_minor');
        $this->employee_count = $this->lines()->count();

        $this->save();
    }

    /**
     * Check if editable.
     */
    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_REVIEW]);
    }

    /**
     * Check if can be calculated.
     */
    public function canCalculate(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_REVIEW]);
    }

    /**
     * Check if can be recalculated.
     */
    public function canRecalculate(): bool
    {
        return $this->status === self::STATUS_REVIEW;
    }

    /**
     * Get total net salary in major units.
     */
    public function getTotalNetSalaryAttribute(): float
    {
        return $this->total_net_salary_minor / 100;
    }

    /**
     * Get total base salary in major units.
     */
    public function getTotalBaseSalaryAttribute(): float
    {
        return $this->total_base_salary_minor / 100;
    }

    /**
     * Get total commissions in major units.
     */
    public function getTotalCommissionsAttribute(): float
    {
        return $this->total_commissions_minor / 100;
    }

    /**
     * Check if can be deleted (only if draft/review and empty).
     */
    public function isDeletable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_REVIEW, self::STATUS_CANCELLED])
            && $this->lines()->count() === 0;
    }
}
