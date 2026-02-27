<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Accounting\Models\JournalEntry;
use Modules\Auth\Models\User;
use Modules\Billing\Models\Payment;
use Modules\Core\Models\Branch;
use XLinic\Framework\Core\Model\BaseModel;

class VendorBill extends BaseModel
{
    use SoftDeletes;

    protected $table = 'vendor_bills';

    protected $fillable = [
        'tenant_id',
        'code',
        'supplier_id',
        'branch_id',
        'purchase_order_id',
        'vendor_reference',
        'status',
        'subtotal_minor',
        'discount_minor',
        'discount_type',
        'tax_minor',
        'total_minor',
        'paid_minor',
        'notes',
        'internal_notes',
        'bill_date',
        'due_date',
        'validated_at',
        'paid_at',
        'cancelled_at',
        'cancellation_reason',
        'journal_entry_id',
        'created_by',
        'validated_by',
    ];

    protected $casts = [
        'subtotal_minor' => 'integer',
        'discount_minor' => 'integer',
        'tax_minor' => 'integer',
        'total_minor' => 'integer',
        'paid_minor' => 'integer',
        'bill_date' => 'date',
        'due_date' => 'date',
        'validated_at' => 'datetime',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    // Status constants
    public const STATUS_DRAFT = 'draft';
    public const STATUS_VALIDATED = 'validated';
    public const STATUS_PARTIALLY_PAID = 'partially_paid';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_VALIDATED => 'Validated',
        self::STATUS_PARTIALLY_PAID => 'Partially Paid',
        self::STATUS_PAID => 'Paid',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    public const STATUS_COLORS = [
        self::STATUS_DRAFT => 'gray',
        self::STATUS_VALIDATED => 'info',
        self::STATUS_PARTIALLY_PAID => 'warning',
        self::STATUS_PAID => 'success',
        self::STATUS_CANCELLED => 'danger',
    ];

    // Discount type constants
    public const DISCOUNT_FIXED = 'fixed';
    public const DISCOUNT_PERCENT = 'percent';

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(VendorBillLine::class)->orderBy('sort_order');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('paid_at', 'desc');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    public function getSubtotalAttribute(): float
    {
        return $this->subtotal_minor / 100;
    }

    public function getTotalAttribute(): float
    {
        return $this->total_minor / 100;
    }

    public function getPaidAttribute(): float
    {
        return $this->paid_minor / 100;
    }

    public function getRemainingMinorAttribute(): int
    {
        return max(0, $this->total_minor - $this->paid_minor);
    }

    public function getRemainingAttribute(): float
    {
        return $this->remaining_minor / 100;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute(): string
    {
        return self::STATUS_COLORS[$this->status] ?? 'gray';
    }

    /*
    |--------------------------------------------------------------------------
    | State Checks
    |--------------------------------------------------------------------------
    */

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isValidated(): bool
    {
        return in_array($this->status, [
            self::STATUS_VALIDATED,
            self::STATUS_PARTIALLY_PAID,
            self::STATUS_PAID,
        ]);
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function canValidate(): bool
    {
        return $this->isDraft() && $this->lines()->exists();
    }

    public function canRecordPayment(): bool
    {
        return in_array($this->status, [
            self::STATUS_VALIDATED,
            self::STATUS_PARTIALLY_PAID,
        ]) && $this->remaining_minor > 0;
    }

    public function canCancel(): bool
    {
        return $this->isDraft();
    }

    /*
    |--------------------------------------------------------------------------
    | Actions
    |--------------------------------------------------------------------------
    */

    public function recalculateTotals(): void
    {
        // Sum line totals and taxes
        $lineTotals = $this->lines()->sum('total_minor');
        $lineTax = $this->lines()->sum('tax_minor');

        // Subtotal is line totals minus line taxes (discounted subtotal before tax)
        $subtotal = $lineTotals - $lineTax;

        $this->subtotal_minor = $subtotal;
        $this->tax_minor = $lineTax;
        $this->total_minor = $lineTotals; // total_minor already includes tax
        $this->save();
    }

    public function validate(): bool
    {
        if (!$this->canValidate()) {
            return false;
        }

        return \DB::transaction(function () {
            $this->recalculateTotals();
            $this->createJournalEntry();

            $this->status = self::STATUS_VALIDATED;
            $this->validated_at = now();
            $this->validated_by = auth()->id();
            $this->save();

            return true;
        });
    }

    public function recordPayment(int $amountMinor): void
    {
        $this->increment('paid_minor', $amountMinor);
        $this->refresh();

        if ($this->paid_minor >= $this->total_minor) {
            $this->status = self::STATUS_PAID;
            $this->paid_at = now();
            $this->save();
        } elseif ($this->paid_minor > 0 && $this->status === self::STATUS_VALIDATED) {
            $this->status = self::STATUS_PARTIALLY_PAID;
            $this->save();
        }
    }

    public function cancel(?string $reason = null): bool
    {
        if (!$this->canCancel()) {
            return false;
        }

        $this->status = self::STATUS_CANCELLED;
        $this->cancelled_at = now();
        $this->cancellation_reason = $reason;
        $this->save();

        return true;
    }

    /**
     * Create journal entry for vendor bill (Accounts Payable)
     */
    protected function createJournalEntry(): void
    {
        try {
            $accountingService = app(\Modules\Inventory\Services\InventoryAccountingService::class);
            $journalEntry = $accountingService->createVendorBillJournalEntry($this);

            if ($journalEntry) {
                $this->journal_entry_id = $journalEntry->id;
                $this->saveQuietly();
            }
        } catch (\Exception $e) {
            \Log::error('VendorBill: Failed to create journal entry', [
                'bill_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Create vendor bill from purchase order
     */
    public static function createFromPurchaseOrder(PurchaseOrder $po): self
    {
        $bill = self::create([
            'tenant_id' => $po->tenant_id,
            'supplier_id' => $po->supplier_id,
            'branch_id' => $po->branch_id,
            'purchase_order_id' => $po->id,
            'status' => self::STATUS_DRAFT,
            'bill_date' => now(),
            'created_by' => auth()->id(),
        ]);

        foreach ($po->lines as $poLine) {
            VendorBillLine::create([
                'tenant_id' => $po->tenant_id,
                'vendor_bill_id' => $bill->id,
                'product_id' => $poLine->product_id,
                'purchase_order_line_id' => $poLine->id,
                'description' => $poLine->product?->getTranslation('name', app()->getLocale()) ?? 'Product',
                'quantity' => $poLine->quantity_received ?: $poLine->quantity,
                'unit_price_minor' => $poLine->unit_price_minor,
                'discount_minor' => $poLine->discount_minor ?? 0,
                'discount_type' => $poLine->discount_type ?? 'fixed',
                'tax_rates' => $poLine->tax_rates ?? [],
            ]);
        }

        $bill->recalculateTotals();

        // Link bill to PO
        $po->vendor_bill_id = $bill->id;
        $po->save();

        return $bill;
    }

    /*
    |--------------------------------------------------------------------------
    | Boot
    |--------------------------------------------------------------------------
    */

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (self $model) {
            if (empty($model->code)) {
                $model->code = $model->nextSequence('BILL');
            }
            if (empty($model->created_by)) {
                $model->created_by = auth()->id();
            }
            if (empty($model->status)) {
                $model->status = self::STATUS_DRAFT;
            }
        });
    }
}
