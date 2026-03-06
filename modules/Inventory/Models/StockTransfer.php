<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Auth\Models\User;
use Modules\Core\Models\Branch;
use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasSequence;
use XLinic\Framework\Core\Model\Traits\HasActivity;

class StockTransfer extends BaseModel
{
    use HasSequence, HasActivity;

    protected $table = 'stock_transfers';

    protected string $sequenceCode = 'TRF';

    protected string $sequenceColumn = 'transfer_number';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'transfer_number',
        'status',
        'source_location_id',
        'destination_location_id',
        'scheduled_date',
        'effective_date',
        'notes',
        'created_by',
        'confirmed_by',
    ];

    protected $casts = [
        'scheduled_date' => 'datetime',
        'effective_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => 'draft',
    ];

    // Status constants
    public const STATUS_DRAFT = 'draft';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_DONE = 'done';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_CONFIRMED => 'Confirmed',
        self::STATUS_DONE => 'Done',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    public const STATUS_COLORS = [
        self::STATUS_DRAFT => 'gray',
        self::STATUS_CONFIRMED => 'warning',
        self::STATUS_DONE => 'success',
        self::STATUS_CANCELLED => 'danger',
    ];

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (self $transfer) {
            if (!$transfer->created_by && auth()->check()) {
                $transfer->created_by = auth()->id();
            }
            if (!$transfer->scheduled_date) {
                $transfer->scheduled_date = now();
            }
        });
    }

    /**
     * Get the branch.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get the source location.
     */
    public function sourceLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'source_location_id');
    }

    /**
     * Get the destination location.
     */
    public function destinationLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'destination_location_id');
    }

    /**
     * Get the creator.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the confirmer.
     */
    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /**
     * Get the transfer lines.
     */
    public function lines(): HasMany
    {
        return $this->hasMany(StockTransferLine::class);
    }

    /**
     * Scope to draft transfers.
     */
    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    /**
     * Scope to confirmed transfers.
     */
    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CONFIRMED);
    }

    /**
     * Scope to done transfers.
     */
    public function scopeDone(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DONE);
    }

    /**
     * Check if the transfer can be edited.
     */
    public function canEdit(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Check if the transfer can be confirmed.
     */
    public function canConfirm(): bool
    {
        return $this->status === self::STATUS_DRAFT && $this->lines()->count() > 0;
    }

    /**
     * Check if the transfer can be processed (done).
     */
    public function canProcess(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    /**
     * Check if the transfer can be cancelled.
     */
    public function canCancel(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_CONFIRMED]);
    }

    /**
     * Confirm the transfer.
     */
    public function confirm(): bool
    {
        if (!$this->canConfirm()) {
            return false;
        }

        $this->status = self::STATUS_CONFIRMED;
        $this->confirmed_by = auth()->id();
        return $this->save();
    }

    /**
     * Process the transfer (move the stock).
     */
    public function process(): bool
    {
        if (!$this->canProcess()) {
            return false;
        }

        return DB::transaction(function () {
            foreach ($this->lines as $line) {
                if ($line->quantity_planned <= 0) {
                    continue;
                }

                // Default quantity_done to quantity_planned if not set
                $quantityToTransfer = $line->quantity_done > 0
                    ? $line->quantity_done
                    : $line->quantity_planned;

                if ($quantityToTransfer <= 0) {
                    continue;
                }

                // Update quantity_done
                $line->quantity_done = $quantityToTransfer;
                $line->save();

                // Create stock movements for location transfer
                StockMovement::createLocationTransfer(
                    $line->product_id,
                    $this->source_location_id,
                    $this->destination_location_id,
                    $quantityToTransfer,
                    "Transfer #{$this->transfer_number}"
                );
            }

            $this->status = self::STATUS_DONE;
            $this->effective_date = now();
            return $this->save();
        });
    }

    /**
     * Cancel the transfer.
     */
    public function cancel(): bool
    {
        if (!$this->canCancel()) {
            return false;
        }

        $this->status = self::STATUS_CANCELLED;
        return $this->save();
    }

    /**
     * Reset to draft.
     */
    public function resetToDraft(): bool
    {
        if ($this->status !== self::STATUS_CONFIRMED) {
            return false;
        }

        $this->status = self::STATUS_DRAFT;
        $this->confirmed_by = null;
        return $this->save();
    }

    /**
     * Get total planned quantity.
     */
    public function getTotalPlannedAttribute(): int
    {
        return $this->lines()->sum('quantity_planned');
    }

    /**
     * Get total done quantity.
     */
    public function getTotalDoneAttribute(): int
    {
        return $this->lines()->sum('quantity_done');
    }

    /**
     * Get lines count.
     */
    public function getLinesCountAttribute(): int
    {
        return $this->lines()->count();
    }
}
