<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Accounting\Models\JournalEntry;
use Modules\Auth\Models\User;
use Modules\Core\Models\Branch;
use XLinic\Framework\Core\Model\BaseModel;

class InventoryAdjustment extends BaseModel
{
    protected $table = 'inventory_adjustments';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'location_id',
        'reference',
        'adjustment_type',
        'adjustment_date',
        'status',
        'reason',
        'notes',
        'journal_entry_id',
        'total_value_adjustment_minor',
        'validated_by',
        'validated_at',
        'created_by',
        'cancelled_by',
        'cancelled_at',
    ];

    protected $casts = [
        'adjustment_date' => 'date',
        'validated_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'total_value_adjustment_minor' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Adjustment Types
    public const TYPE_COUNT = 'count';
    public const TYPE_LOSS = 'loss';
    public const TYPE_DAMAGE = 'damage';
    public const TYPE_CORRECTION = 'correction';
    public const TYPE_INITIAL = 'initial';

    public const TYPES = [
        self::TYPE_COUNT => 'Physical Count',
        self::TYPE_LOSS => 'Loss',
        self::TYPE_DAMAGE => 'Damage',
        self::TYPE_CORRECTION => 'Correction',
        self::TYPE_INITIAL => 'Initial Stock',
    ];

    public const TYPE_COLORS = [
        self::TYPE_COUNT => 'info',
        self::TYPE_LOSS => 'danger',
        self::TYPE_DAMAGE => 'warning',
        self::TYPE_CORRECTION => 'gray',
        self::TYPE_INITIAL => 'success',
    ];

    // Status
    public const STATUS_DRAFT = 'draft';
    public const STATUS_VALIDATED = 'validated';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_VALIDATED => 'Validated',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    public const STATUS_COLORS = [
        self::STATUS_DRAFT => 'gray',
        self::STATUS_VALIDATED => 'success',
        self::STATUS_CANCELLED => 'danger',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'location_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InventoryAdjustmentLine::class, 'inventory_adjustment_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    public function getTotalValueAdjustmentAttribute(): float
    {
        return $this->total_value_adjustment_minor / 100;
    }

    /*
    |--------------------------------------------------------------------------
    | Methods
    |--------------------------------------------------------------------------
    */

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isValidated(): bool
    {
        return $this->status === self::STATUS_VALIDATED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function canValidate(): bool
    {
        if (!$this->isDraft()) {
            return false;
        }

        // Must have at least one line with a difference
        return $this->lines()
            ->whereColumn('counted_qty', '!=', 'theoretical_qty')
            ->exists();
    }

    public function canCancel(): bool
    {
        return $this->isDraft();
    }

    /**
     * Recalculate total value adjustment from lines.
     */
    public function recalculateTotals(): void
    {
        $this->total_value_adjustment_minor = $this->lines()->sum('value_adjustment_minor');
        $this->save();
    }

    /**
     * Validate the adjustment - apply stock changes and create journal entry.
     */
    public function validate(): bool
    {
        if (!$this->canValidate()) {
            return false;
        }

        return \DB::transaction(function () {
            // Recalculate totals first to ensure value_adjustment is correct
            $this->recalculateTotals();
            $this->refresh();

            // Apply stock changes
            foreach ($this->lines as $line) {
                $line->applyStockChange();
            }

            // Create journal entry
            $this->createJournalEntry();

            // Update status
            $this->status = self::STATUS_VALIDATED;
            $this->validated_by = auth()->id();
            $this->validated_at = now();
            $this->save();

            return true;
        });
    }

    /**
     * Cancel the adjustment.
     */
    public function cancel(): bool
    {
        if (!$this->canCancel()) {
            return false;
        }

        $this->status = self::STATUS_CANCELLED;
        $this->cancelled_by = auth()->id();
        $this->cancelled_at = now();
        $this->save();

        return true;
    }

    /**
     * Create journal entry for inventory adjustment.
     */
    protected function createJournalEntry(): void
    {
        try {
            $accountingService = app(\Modules\Inventory\Services\InventoryAccountingService::class);

            $journalEntry = $accountingService->createAdjustmentJournalEntry($this);

            if ($journalEntry) {
                $this->journal_entry_id = $journalEntry->id;
                $this->saveQuietly(); // Use saveQuietly to avoid triggering events

                \Log::info('InventoryAdjustment: Journal entry linked', [
                    'adjustment_id' => $this->id,
                    'journal_entry_id' => $journalEntry->id,
                ]);
            } else {
                \Log::warning('InventoryAdjustment: No journal entry created', [
                    'adjustment_id' => $this->id,
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('InventoryAdjustment: Failed to create journal entry', [
                'adjustment_id' => $this->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Load products for counting from current stock levels.
     * Loads ALL active storable products in the branch/location with their current stock.
     */
    public function loadProductsFromStock(): void
    {
        if (!$this->isDraft()) {
            return;
        }

        // Get all active storable products
        $products = Product::where('is_active', true)
            ->storable()
            ->get();

        foreach ($products as $product) {
            // Check if line already exists
            $existingLine = $this->lines()
                ->where('product_id', $product->id)
                ->first();

            if (!$existingLine) {
                // Get stock level for this branch and location
                $query = StockLevel::where('product_id', $product->id)
                    ->where('branch_id', $this->branch_id);

                // If location is specified, filter by it
                if ($this->location_id) {
                    $query->where('location_id', $this->location_id);
                }

                $stockLevel = $query->first();
                $theoreticalQty = $stockLevel?->quantity_on_hand ?? 0;

                InventoryAdjustmentLine::create([
                    'tenant_id' => $this->tenant_id,
                    'inventory_adjustment_id' => $this->id,
                    'product_id' => $product->id,
                    'theoretical_qty' => $theoreticalQty,
                    'counted_qty' => $theoreticalQty, // Default to theoretical
                    'difference_qty' => 0,
                    'unit_cost_minor' => $product->cost_price_minor ?? 0,
                    'value_adjustment_minor' => 0,
                ]);
            }
        }
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
            if (empty($model->reference)) {
                $model->reference = $model->nextSequence('ADJ');
            }
            if (empty($model->created_by)) {
                $model->created_by = auth()->id();
            }
        });
    }
}
