<?php

namespace Modules\Staff\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Booking\Models\Appointment;
use XLinic\Framework\Core\Model\BaseModel;

class StaffCommissionRecord extends BaseModel
{
    protected $table = 'staff_commission_records';

    protected $fillable = [
        'tenant_id',
        'staff_profile_id',
        'appointment_id',
        'payroll_line_id',
        'amount_minor',
        'revenue_minor',
        'commission_type',
        'commission_rate',
        'status',
        'approved_by',
        'approved_at',
        'paid_at',
        'notes',
    ];

    protected $casts = [
        'id' => 'string',
        'amount_minor' => 'integer',
        'revenue_minor' => 'integer',
        'commission_rate' => 'decimal:2',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
        'amount_minor' => 0,
        'revenue_minor' => 0,
    ];

    // Status constants
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING => 'Pending',
        self::STATUS_APPROVED => 'Approved',
        self::STATUS_PAID => 'Paid',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    public const STATUS_COLORS = [
        self::STATUS_PENDING => 'warning',
        self::STATUS_APPROVED => 'success',
        self::STATUS_PAID => 'info',
        self::STATUS_CANCELLED => 'danger',
    ];

    // State transitions
    public const TRANSITIONS = [
        self::STATUS_PENDING => [self::STATUS_APPROVED, self::STATUS_CANCELLED],
        self::STATUS_APPROVED => [self::STATUS_PAID, self::STATUS_CANCELLED],
        self::STATUS_PAID => [],
        self::STATUS_CANCELLED => [],
    ];

    /**
     * Get the staff profile.
     */
    public function staffProfile(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class);
    }

    /**
     * Get the appointment.
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * Get the approver.
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(\Modules\Auth\Models\User::class, 'approved_by');
    }

    /**
     * Check if status transition is allowed.
     */
    public function canTransitionTo(string $newStatus): bool
    {
        return in_array($newStatus, self::TRANSITIONS[$this->status] ?? []);
    }

    /**
     * Approve the commission.
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
     * Mark as paid.
     */
    public function markAsPaid(): bool
    {
        if (!$this->canTransitionTo(self::STATUS_PAID)) {
            return false;
        }

        $this->status = self::STATUS_PAID;
        $this->paid_at = now();

        return $this->save();
    }

    /**
     * Cancel the commission.
     */
    public function cancel(?string $notes = null): bool
    {
        if (!$this->canTransitionTo(self::STATUS_CANCELLED)) {
            return false;
        }

        $this->status = self::STATUS_CANCELLED;
        if ($notes) {
            $this->notes = $notes;
        }

        return $this->save();
    }

    /**
     * Scope to pending records.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope to approved records.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Scope to unpaid records (pending + approved).
     */
    public function scopeUnpaid($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_APPROVED]);
    }

    /**
     * Get amount in major units.
     */
    public function getAmountAttribute(): float
    {
        return $this->amount_minor / 100;
    }

    /**
     * Get revenue in major units.
     */
    public function getRevenueAttribute(): float
    {
        return $this->revenue_minor / 100;
    }
}
