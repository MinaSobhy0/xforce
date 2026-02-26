<?php

namespace Modules\Billing\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use XLinic\Framework\Core\Model\Traits\HasActivity;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Auth\Models\User;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Models\JournalEntry;
use Modules\Patients\Models\Patient;
use Modules\Core\Models\Branch;
use Modules\TreatmentPlans\Models\TreatmentPlan;
use Modules\Booking\Models\Appointment;
use Modules\Inventory\Models\VendorBill;
use Modules\Inventory\Models\Supplier;

class Payment extends BaseModel
{
    use HasTenancy, HasActivity;

    protected $fillable = [
        'tenant_id',
        'code',
        'type',
        'invoice_id',
        'vendor_bill_id',
        'supplier_id',
        'patient_id',
        'branch_id',
        'treatment_plan_id',
        'appointment_id',
        'status',
        'journal_id',
        'amount_minor',
        'reference_number',
        'gateway_transaction_id',
        'gift_card_id',
        'received_by_user_id',
        'notes',
        'paid_at',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'paid_at' => 'datetime',
    ];

    // Status constants
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_PENDING = 'pending';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_COMPLETED => 'Completed',
        self::STATUS_PENDING => 'Pending',
        self::STATUS_REFUNDED => 'Refunded',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    public const STATUS_COLORS = [
        self::STATUS_COMPLETED => 'success',
        self::STATUS_PENDING => 'warning',
        self::STATUS_REFUNDED => 'danger',
        self::STATUS_CANCELLED => 'gray',
    ];

    // Payment type constants
    public const TYPE_RECEIVE = 'receive';
    public const TYPE_SEND = 'send';

    public const TYPES = [
        self::TYPE_RECEIVE => 'Receive (Money In)',
        self::TYPE_SEND => 'Send (Money Out)',
    ];

    public const TYPE_COLORS = [
        self::TYPE_RECEIVE => 'success',
        self::TYPE_SEND => 'danger',
    ];

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (Payment $payment) {
            if (empty($payment->paid_at)) {
                $payment->paid_at = now();
            }
            if (empty($payment->status)) {
                $payment->status = self::STATUS_COMPLETED;
            }
            if (empty($payment->type)) {
                // Default type based on whether it's an invoice or vendor bill payment
                $payment->type = $payment->vendor_bill_id ? self::TYPE_SEND : self::TYPE_RECEIVE;
            }
            // Generate code from journal's sequence
            if (empty($payment->code) && $payment->journal_id && $payment->journal) {
                $payment->code = $payment->journal->getNextSequence();
            }
            // For invoice payments, ensure patient_id is set from invoice
            if (!$payment->patient_id && $payment->invoice_id) {
                $payment->patient_id = $payment->invoice?->patient_id;
            }
            if (!$payment->branch_id && $payment->invoice_id) {
                $payment->branch_id = $payment->invoice?->branch_id;
            }
            // For vendor bill payments, set supplier_id and branch_id
            if (!$payment->supplier_id && $payment->vendor_bill_id) {
                $payment->supplier_id = $payment->vendorBill?->supplier_id;
            }
            if (!$payment->branch_id && $payment->vendor_bill_id) {
                $payment->branch_id = $payment->vendorBill?->branch_id;
            }
        });

        static::created(function (Payment $payment) {
            if ($payment->status !== self::STATUS_COMPLETED) {
                return;
            }

            // Handle invoice payment (receive)
            if ($payment->invoice) {
                $payment->invoice->recordPayment($payment->amount_minor);
                // Create journal entry for invoice payment
                app(\Modules\Billing\Services\AccountingIntegrationService::class)
                    ->createPaymentJournalEntry($payment);
            }

            // Handle vendor bill payment (send)
            if ($payment->vendorBill) {
                $payment->vendorBill->recordPayment($payment->amount_minor);
                // Create journal entry for vendor bill payment
                app(\Modules\Inventory\Services\InventoryAccountingService::class)
                    ->createVendorPaymentJournalEntry($payment->vendorBill, $payment->amount_minor, $payment->journal?->type ?? 'cash');
            }
        });
    }

    // Relationships
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function treatmentPlan(): BelongsTo
    {
        return $this->belongsTo(TreatmentPlan::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function vendorBill(): BelongsTo
    {
        return $this->belongsTo(VendorBill::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }

    public function journalEntries()
    {
        return $this->morphMany(JournalEntry::class, 'source');
    }

    // Check if this is an unassigned payment (deposit)
    public function isUnassigned(): bool
    {
        return is_null($this->invoice_id);
    }

    public function isAssigned(): bool
    {
        return !is_null($this->invoice_id);
    }

    /**
     * Assign this payment to an invoice
     */
    public function assignToInvoice(Invoice $invoice): bool
    {
        if (!$this->isUnassigned()) {
            return false;
        }

        $this->invoice_id = $invoice->id;
        $result = $this->save();

        if ($result && $this->status === self::STATUS_COMPLETED) {
            $invoice->recordPayment($this->amount_minor);
        }

        return $result;
    }

    // Status checks
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isRefunded(): bool
    {
        return $this->status === self::STATUS_REFUNDED;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute(): string
    {
        return self::STATUS_COLORS[$this->status] ?? 'gray';
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function getTypeColorAttribute(): string
    {
        return self::TYPE_COLORS[$this->type] ?? 'gray';
    }

    public function isReceive(): bool
    {
        return $this->type === self::TYPE_RECEIVE;
    }

    public function isSend(): bool
    {
        return $this->type === self::TYPE_SEND;
    }

    // Accessors
    public function getMethodAttribute(): string
    {
        return $this->journal?->type ?? 'cash';
    }

    public function getMethodLabelAttribute(): string
    {
        return $this->journal?->name ?? 'Cash';
    }

    public function getMethodIconAttribute(): string
    {
        $icons = [
            'cash' => 'heroicon-o-banknotes',
            'bank' => 'heroicon-o-building-library',
            'sales' => 'heroicon-o-credit-card',
            'purchase' => 'heroicon-o-shopping-cart',
            'general' => 'heroicon-o-currency-dollar',
            'gift_card' => 'heroicon-o-gift',
        ];

        return $icons[$this->journal?->type] ?? 'heroicon-o-currency-dollar';
    }

    public function giftCard(): BelongsTo
    {
        return $this->belongsTo(\Modules\GiftCards\Models\GiftCard::class);
    }

    // Scopes
    public function scopeByJournal($query, string $journalId)
    {
        return $query->where('journal_id', $journalId);
    }

    public function scopeByJournalType($query, string $type)
    {
        return $query->whereHas('journal', fn ($q) => $q->where('type', $type));
    }

    public function scopeForDateRange($query, $start, $end)
    {
        return $query->whereBetween('paid_at', [$start, $end]);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('paid_at', today());
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year);
    }

    public function scopeUnassigned($query)
    {
        return $query->whereNull('invoice_id')
            ->where('status', self::STATUS_COMPLETED);
    }

    public function scopeAssigned($query)
    {
        return $query->whereNotNull('invoice_id');
    }

    public function scopeForPatient($query, string $patientId)
    {
        return $query->where('patient_id', $patientId);
    }

    public function scopeForTreatmentPlan($query, string $planId)
    {
        return $query->where('treatment_plan_id', $planId);
    }

    public function scopeForAppointment($query, string $appointmentId)
    {
        return $query->where('appointment_id', $appointmentId);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeReceived($query)
    {
        return $query->where('type', self::TYPE_RECEIVE);
    }

    public function scopeSent($query)
    {
        return $query->where('type', self::TYPE_SEND);
    }

    public function scopeForVendorBill($query, string $vendorBillId)
    {
        return $query->where('vendor_bill_id', $vendorBillId);
    }

    public function scopeForSupplier($query, string $supplierId)
    {
        return $query->where('supplier_id', $supplierId);
    }
}
