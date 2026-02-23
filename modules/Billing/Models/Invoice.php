<?php

namespace Modules\Billing\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use XLinic\Framework\Core\Model\Traits\HasActivity;
use XLinic\Framework\Core\Model\Traits\HasSequence;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Auth\Models\User;
use Modules\Core\Models\Branch;
use Modules\Patients\Models\Patient;
use Modules\Booking\Models\Appointment;
use Modules\Accounting\Models\JournalEntry;
use Modules\TreatmentPlans\Models\TreatmentPlan;

class Invoice extends BaseModel
{
    use HasTenancy, HasActivity, HasSequence, SoftDeletes;

    protected string $sequenceCode = 'invoice';
    protected string $sequenceColumn = 'code';

    protected $fillable = [
        'tenant_id',
        'code',
        'patient_id',
        'branch_id',
        'appointment_id',
        'treatment_plan_id',
        'type',
        'status',
        'subtotal_minor',
        'discount_minor',
        'discount_type',
        'tax_minor',
        'total_minor',
        'paid_minor',
        'deposits_applied_minor',
        'notes',
        'internal_notes',
        'due_date',
        'issued_at',
        'paid_at',
        'cancelled_at',
        'cancellation_reason',
        'created_by_user_id',
    ];

    protected $casts = [
        'subtotal_minor' => 'integer',
        'discount_minor' => 'integer',
        'tax_minor' => 'integer',
        'total_minor' => 'integer',
        'paid_minor' => 'integer',
        'deposits_applied_minor' => 'integer',
        'due_date' => 'date',
        'issued_at' => 'datetime',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    // Type constants
    public const TYPE_STANDARD = 'standard';
    public const TYPE_CREDIT_NOTE = 'credit_note';
    public const TYPE_PROFORMA = 'proforma';

    public const TYPES = [
        self::TYPE_STANDARD => 'Standard Invoice',
        self::TYPE_CREDIT_NOTE => 'Credit Note',
        self::TYPE_PROFORMA => 'Proforma Invoice',
    ];

    // Status constants
    public const STATUS_DRAFT = 'draft';
    public const STATUS_ISSUED = 'issued';
    public const STATUS_PARTIALLY_PAID = 'partially_paid';
    public const STATUS_PAID = 'paid';
    public const STATUS_OVERDUE = 'overdue';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REFUNDED = 'refunded';

    public const STATUSES = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_ISSUED => 'Issued',
        self::STATUS_PARTIALLY_PAID => 'Partially Paid',
        self::STATUS_PAID => 'Paid',
        self::STATUS_OVERDUE => 'Overdue',
        self::STATUS_CANCELLED => 'Cancelled',
        self::STATUS_REFUNDED => 'Refunded',
    ];

    public const STATUS_COLORS = [
        self::STATUS_DRAFT => 'gray',
        self::STATUS_ISSUED => 'info',
        self::STATUS_PARTIALLY_PAID => 'warning',
        self::STATUS_PAID => 'success',
        self::STATUS_OVERDUE => 'danger',
        self::STATUS_CANCELLED => 'gray',
        self::STATUS_REFUNDED => 'danger',
    ];

    // Discount type constants
    public const DISCOUNT_FIXED = 'fixed';
    public const DISCOUNT_PERCENT = 'percent';

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (Invoice $invoice) {
            if (empty($invoice->status)) {
                $invoice->status = self::STATUS_DRAFT;
            }
            if (empty($invoice->type)) {
                $invoice->type = self::TYPE_STANDARD;
            }
            if (empty($invoice->paid_minor)) {
                $invoice->paid_minor = 0;
            }
        });
    }

    // Relationships
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function treatmentPlan(): BelongsTo
    {
        return $this->belongsTo(TreatmentPlan::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('sort_order');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('paid_at');
    }

    public function installmentPlan(): HasOne
    {
        return $this->hasOne(InstallmentPlan::class);
    }

    public function journalEntries()
    {
        return $this->morphMany(JournalEntry::class, 'source');
    }

    // Computed attributes
    public function getRemainingMinorAttribute(): int
    {
        return max(0, $this->total_minor - $this->paid_minor - ($this->deposits_applied_minor ?? 0));
    }

    public function getTotalPaidAttribute(): int
    {
        return ($this->paid_minor ?? 0) + ($this->deposits_applied_minor ?? 0);
    }

    public function getPaymentProgressAttribute(): float
    {
        if ($this->total_minor <= 0) {
            return 100;
        }
        return round(($this->paid_minor / $this->total_minor) * 100, 1);
    }

    public function getIsOverdueAttribute(): bool
    {
        if (!$this->due_date || $this->isPaid()) {
            return false;
        }
        return $this->due_date->isPast();
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute(): string
    {
        return self::STATUS_COLORS[$this->status] ?? 'gray';
    }

    // State machine
    public function canTransitionTo(string $status): bool
    {
        $transitions = [
            self::STATUS_DRAFT => [self::STATUS_ISSUED, self::STATUS_CANCELLED],
            self::STATUS_ISSUED => [self::STATUS_PARTIALLY_PAID, self::STATUS_PAID, self::STATUS_OVERDUE, self::STATUS_CANCELLED],
            self::STATUS_PARTIALLY_PAID => [self::STATUS_PAID, self::STATUS_OVERDUE, self::STATUS_CANCELLED],
            self::STATUS_PAID => [self::STATUS_REFUNDED],
            self::STATUS_OVERDUE => [self::STATUS_PARTIALLY_PAID, self::STATUS_PAID, self::STATUS_CANCELLED],
            self::STATUS_CANCELLED => [],
            self::STATUS_REFUNDED => [],
        ];

        return in_array($status, $transitions[$this->status] ?? []);
    }

    public function transitionTo(string $status): bool
    {
        if (!$this->canTransitionTo($status)) {
            return false;
        }

        $timestampField = match ($status) {
            self::STATUS_ISSUED => 'issued_at',
            self::STATUS_PAID => 'paid_at',
            self::STATUS_CANCELLED => 'cancelled_at',
            default => null,
        };

        $this->status = $status;
        if ($timestampField) {
            $this->{$timestampField} = now();
        }

        return $this->save();
    }

    // State actions
    public function issue(): bool
    {
        $result = $this->transitionTo(self::STATUS_ISSUED);

        if ($result) {
            // Create journal entry for issued invoice
            app(\Modules\Billing\Services\AccountingIntegrationService::class)
                ->createInvoiceJournalEntry($this);
        }

        return $result;
    }

    public function cancel(?string $reason = null): bool
    {
        if ($reason) {
            $this->cancellation_reason = $reason;
        }
        return $this->transitionTo(self::STATUS_CANCELLED);
    }

    public function recordPayment(int $amountMinor): void
    {
        $this->increment('paid_minor', $amountMinor);
        $this->refresh();

        if ($this->paid_minor >= $this->total_minor) {
            $this->transitionTo(self::STATUS_PAID);
        } elseif ($this->paid_minor > 0 && $this->status !== self::STATUS_PARTIALLY_PAID) {
            $this->transitionTo(self::STATUS_PARTIALLY_PAID);
        }
    }

    // State checks
    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isIssued(): bool
    {
        return $this->status === self::STATUS_ISSUED;
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isPartiallyPaid(): bool
    {
        return $this->status === self::STATUS_PARTIALLY_PAID;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isEditable(): bool
    {
        return $this->isDraft();
    }

    public function canRecordPayment(): bool
    {
        return in_array($this->status, [
            self::STATUS_ISSUED,
            self::STATUS_PARTIALLY_PAID,
            self::STATUS_OVERDUE,
        ]);
    }

    // Calculation methods
    public function recalculateTotals(): void
    {
        $subtotal = $this->lines()->sum('total_minor');
        $tax = $this->lines()->sum('tax_minor');

        $discountAmount = 0;
        if ($this->discount_minor > 0) {
            if ($this->discount_type === self::DISCOUNT_PERCENT) {
                $discountAmount = (int) round($subtotal * $this->discount_minor / 100);
            } else {
                $discountAmount = $this->discount_minor;
            }
        }

        $this->subtotal_minor = $subtotal;
        $this->tax_minor = $tax;
        $this->total_minor = max(0, $subtotal + $tax - $discountAmount);
        $this->save();
    }

    // Scopes
    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeIssued($query)
    {
        return $query->where('status', self::STATUS_ISSUED);
    }

    public function scopePaid($query)
    {
        return $query->where('status', self::STATUS_PAID);
    }

    public function scopeUnpaid($query)
    {
        return $query->whereIn('status', [
            self::STATUS_ISSUED,
            self::STATUS_PARTIALLY_PAID,
            self::STATUS_OVERDUE,
        ]);
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', self::STATUS_OVERDUE)
            ->orWhere(function ($q) {
                $q->whereIn('status', [self::STATUS_ISSUED, self::STATUS_PARTIALLY_PAID])
                    ->whereNotNull('due_date')
                    ->where('due_date', '<', today());
            });
    }

    public function scopeForPatient($query, string $patientId)
    {
        return $query->where('patient_id', $patientId);
    }

    public function scopeForBranch($query, string $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeForDateRange($query, $start, $end)
    {
        return $query->whereBetween('created_at', [$start, $end]);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('code', 'ilike', "%{$term}%")
                ->orWhereHas('patient', function ($pq) use ($term) {
                    $pq->where('first_name', 'ilike', "%{$term}%")
                        ->orWhere('last_name', 'ilike', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%");
                });
        });
    }

    public function scopeForTreatmentPlan($query, string $planId)
    {
        return $query->where('treatment_plan_id', $planId);
    }

    /**
     * Apply an unassigned payment (deposit) to this invoice
     */
    public function applyUnassignedPayment(Payment $payment): bool
    {
        if (!$payment->isUnassigned()) {
            return false;
        }

        if ($payment->patient_id !== $this->patient_id) {
            return false;
        }

        // Calculate how much to apply
        $remaining = $this->remaining_minor;
        $amountToApply = min($payment->amount_minor, $remaining);

        if ($amountToApply <= 0) {
            return false;
        }

        // Assign the payment to this invoice
        $payment->invoice_id = $this->id;
        $payment->save();

        // Update deposits applied
        $this->increment('deposits_applied_minor', $amountToApply);

        // Check if invoice is now fully paid
        $this->refresh();
        if ($this->remaining_minor <= 0 && $this->status !== self::STATUS_PAID) {
            $this->transitionTo(self::STATUS_PAID);
        } elseif ($this->total_paid > 0 && $this->status === self::STATUS_ISSUED) {
            $this->transitionTo(self::STATUS_PARTIALLY_PAID);
        }

        return true;
    }

    /**
     * Get available unassigned payments for this patient that can be applied
     */
    public function getAvailableDeposits()
    {
        return Payment::unassigned()
            ->forPatient($this->patient_id)
            ->where('tenant_id', $this->tenant_id)
            ->orderBy('paid_at')
            ->get();
    }
}
