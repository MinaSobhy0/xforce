<?php

namespace Modules\Billing\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstallmentSchedule extends BaseModel
{
    use HasTenancy;

    protected $table = 'installment_schedules';

    protected $fillable = [
        'tenant_id',
        'installment_plan_id',
        'installment_number',
        'amount_minor',
        'due_date',
        'paid_at',
        'payment_id',
        'status',
    ];

    protected $casts = [
        'installment_number' => 'integer',
        'amount_minor' => 'integer',
        'due_date' => 'date',
        'paid_at' => 'datetime',
    ];

    // Status constants
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_OVERDUE = 'overdue';

    public const STATUSES = [
        self::STATUS_PENDING => 'Pending',
        self::STATUS_PAID => 'Paid',
        self::STATUS_OVERDUE => 'Overdue',
    ];

    public const STATUS_COLORS = [
        self::STATUS_PENDING => 'warning',
        self::STATUS_PAID => 'success',
        self::STATUS_OVERDUE => 'danger',
    ];

    // Relationships
    public function plan(): BelongsTo
    {
        return $this->belongsTo(InstallmentPlan::class, 'installment_plan_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    // Mark as paid
    public function markPaid(Payment $payment): bool
    {
        $this->payment_id = $payment->id;
        $this->paid_at = now();
        $this->status = self::STATUS_PAID;
        $result = $this->save();

        // Check if plan is complete
        $this->plan?->checkCompletion();

        return $result;
    }

    // Check if overdue
    public function getIsOverdueAttribute(): bool
    {
        return $this->status === self::STATUS_PENDING && $this->due_date->isPast();
    }

    public function getStatusLabelAttribute(): string
    {
        // Return overdue if actually overdue
        if ($this->is_overdue) {
            return self::STATUSES[self::STATUS_OVERDUE];
        }
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute(): string
    {
        if ($this->is_overdue) {
            return self::STATUS_COLORS[self::STATUS_OVERDUE];
        }
        return self::STATUS_COLORS[$this->status] ?? 'gray';
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopePaid($query)
    {
        return $query->where('status', self::STATUS_PAID);
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', self::STATUS_PENDING)
            ->where('due_date', '<', today());
    }

    public function scopeDueThisWeek($query)
    {
        return $query->where('status', self::STATUS_PENDING)
            ->whereBetween('due_date', [today(), today()->addWeek()]);
    }
}
