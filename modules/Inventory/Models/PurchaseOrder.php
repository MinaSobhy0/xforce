<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Models\Branch;
use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasSequence;

class PurchaseOrder extends BaseModel
{
    use HasSequence;

    protected $table = 'purchase_orders';

    protected string $sequenceCode = 'PO';

    protected string $sequenceColumn = 'order_number';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'supplier_id',
        'order_number',
        'status',
        'vendor_bill_id',
        'order_date',
        'expected_date',
        'received_date',
        'subtotal_minor',
        'tax_amount_minor',
        'discount_amount_minor',
        'shipping_amount_minor',
        'total_amount_minor',
        'notes',
        'internal_notes',
        'created_by',
        'approved_by',
        'approved_at',
        'received_by',
    ];

    protected $casts = [
        'id' => 'string',
        'order_date' => 'date',
        'expected_date' => 'date',
        'received_date' => 'date',
        'subtotal_minor' => 'integer',
        'tax_amount_minor' => 'integer',
        'discount_amount_minor' => 'integer',
        'shipping_amount_minor' => 'integer',
        'total_amount_minor' => 'integer',
        'approved_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
        'subtotal_minor' => 0,
        'tax_amount_minor' => 0,
        'discount_amount_minor' => 0,
        'shipping_amount_minor' => 0,
        'total_amount_minor' => 0,
    ];

    // Status constants
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SENT = 'sent';
    public const STATUS_PARTIALLY_RECEIVED = 'partially_received';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_SENT => 'Sent',
        self::STATUS_PARTIALLY_RECEIVED => 'Partially Received',
        self::STATUS_RECEIVED => 'Received',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    public const STATUS_COLORS = [
        self::STATUS_DRAFT => 'gray',
        self::STATUS_SENT => 'info',
        self::STATUS_PARTIALLY_RECEIVED => 'warning',
        self::STATUS_RECEIVED => 'success',
        self::STATUS_CANCELLED => 'danger',
    ];

    // State transitions
    public const TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_SENT, self::STATUS_CANCELLED],
        self::STATUS_SENT => [self::STATUS_PARTIALLY_RECEIVED, self::STATUS_RECEIVED, self::STATUS_CANCELLED],
        self::STATUS_PARTIALLY_RECEIVED => [self::STATUS_RECEIVED], // No cancel - stock already affected
        self::STATUS_RECEIVED => [self::STATUS_SENT], // Can reopen
        self::STATUS_CANCELLED => [self::STATUS_DRAFT],
    ];

    /**
     * Get the supplier.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Get the branch.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get the order lines.
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }

    /**
     * Get the creator.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\Modules\Auth\Models\User::class, 'created_by');
    }

    /**
     * Get the approver.
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(\Modules\Auth\Models\User::class, 'approved_by');
    }

    /**
     * Get the receiver.
     */
    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(\Modules\Auth\Models\User::class, 'received_by');
    }

    /**
     * Get the vendor bill.
     */
    public function vendorBill(): BelongsTo
    {
        return $this->belongsTo(VendorBill::class);
    }

    /**
     * Check if the order status is received.
     */
    public function isReceived(): bool
    {
        return $this->status === self::STATUS_RECEIVED;
    }

    /**
     * Check if all items have been received (regardless of status).
     */
    public function areAllItemsReceived(): bool
    {
        $totalOrdered = $this->lines()->sum('quantity');
        $totalReceived = $this->lines()->sum('quantity_received');

        return $totalOrdered > 0 && $totalReceived >= $totalOrdered;
    }

    /**
     * Check if status transition is allowed.
     */
    public function canTransitionTo(string $newStatus): bool
    {
        return in_array($newStatus, self::TRANSITIONS[$this->status] ?? []);
    }

    /**
     * Transition to a new status.
     */
    public function transitionTo(string $newStatus): bool
    {
        if (!$this->canTransitionTo($newStatus)) {
            return false;
        }

        $this->status = $newStatus;
        return $this->save();
    }

    /**
     * Send the purchase order.
     */
    public function send(?string $userId = null): bool
    {
        if (!$this->canTransitionTo(self::STATUS_SENT)) {
            return false;
        }

        $this->status = self::STATUS_SENT;
        $this->approved_by = $userId ?? auth()->id();
        $this->approved_at = now();

        return $this->save();
    }

    /**
     * Receive the purchase order (full or partial).
     */
    public function receive(?string $userId = null): bool
    {
        $totalOrdered = $this->lines()->sum('quantity');
        $totalReceived = $this->lines()->sum('quantity_received');

        if ($totalReceived >= $totalOrdered) {
            $newStatus = self::STATUS_RECEIVED;
        } elseif ($totalReceived > 0) {
            $newStatus = self::STATUS_PARTIALLY_RECEIVED;
        } else {
            return false;
        }

        if (!$this->canTransitionTo($newStatus)) {
            return false;
        }

        $this->status = $newStatus;

        if ($newStatus === self::STATUS_RECEIVED) {
            $this->received_date = now();
            $this->received_by = $userId ?? auth()->id();
        }

        return $this->save();
    }

    /**
     * Cancel the purchase order.
     */
    public function cancel(): bool
    {
        if (!$this->canTransitionTo(self::STATUS_CANCELLED)) {
            return false;
        }

        // Extra safety: cannot cancel if any items have been received
        if ($this->hasReceivedItems()) {
            return false;
        }

        $this->status = self::STATUS_CANCELLED;
        return $this->save();
    }

    /**
     * Check if order has any received items.
     */
    public function hasReceivedItems(): bool
    {
        return $this->lines()->where('quantity_received', '>', 0)->exists();
    }

    /**
     * Reset cancelled order to draft.
     */
    public function resetToDraft(): bool
    {
        if (!$this->canTransitionTo(self::STATUS_DRAFT)) {
            return false;
        }

        $this->status = self::STATUS_DRAFT;
        $this->approved_by = null;
        $this->approved_at = null;
        return $this->save();
    }

    /**
     * Check if order can be reset to draft.
     */
    public function canResetToDraft(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Reopen a received order to allow more receiving or corrections.
     */
    public function reopenReceiving(): bool
    {
        if (!$this->canReopenReceiving()) {
            return false;
        }

        $this->status = self::STATUS_SENT;
        $this->received_date = null;
        $this->received_by = null;

        return $this->save();
    }

    /**
     * Check if order can be reopened for more receiving.
     */
    public function canReopenReceiving(): bool
    {
        return $this->status === self::STATUS_RECEIVED;
    }

    /**
     * Reverse all received items - decreases stock and creates reverse journal entries.
     */
    public function reverseReceiving(): bool
    {
        if (!$this->canReverseReceiving()) {
            return false;
        }

        return \DB::transaction(function () {
            foreach ($this->lines as $line) {
                $line->reverseReceiving();
            }

            // Reset status back to sent
            $this->status = self::STATUS_SENT;
            $this->received_date = null;
            $this->received_by = null;

            return $this->save();
        });
    }

    /**
     * Check if receiving can be reversed.
     */
    public function canReverseReceiving(): bool
    {
        // Can only reverse if received or partially received
        return in_array($this->status, [self::STATUS_RECEIVED, self::STATUS_PARTIALLY_RECEIVED]);
    }

    /**
     * Recalculate totals from lines.
     */
    public function recalculateTotals(): void
    {
        $subtotal = $this->lines()->sum(\DB::raw('quantity * unit_price_minor'));

        $this->subtotal_minor = $subtotal;
        $this->total_amount_minor = $subtotal
            + $this->tax_amount_minor
            - $this->discount_amount_minor
            + $this->shipping_amount_minor;

        $this->save();
    }

    /**
     * Check if order is editable.
     */
    public function isEditable(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Check if order can be received.
     */
    public function canReceive(): bool
    {
        // Must be in receivable status
        if (!in_array($this->status, [self::STATUS_SENT, self::STATUS_PARTIALLY_RECEIVED])) {
            return false;
        }

        // Must have items remaining to receive
        $totalOrdered = $this->lines()->sum('quantity');
        $totalReceived = $this->lines()->sum('quantity_received');

        return $totalReceived < $totalOrdered;
    }

    /**
     * Scope to pending orders.
     */
    public function scopePending($query)
    {
        return $query->whereIn('status', [self::STATUS_DRAFT, self::STATUS_SENT]);
    }

    /**
     * Scope to received orders.
     */
    public function scopeReceived($query)
    {
        return $query->where('status', self::STATUS_RECEIVED);
    }

    /**
     * Get total amount in major units.
     */
    public function getTotalAmountAttribute(): float
    {
        return $this->total_amount_minor / 100;
    }

    /**
     * Get subtotal in major units.
     */
    public function getSubtotalAttribute(): float
    {
        return $this->subtotal_minor / 100;
    }
}
