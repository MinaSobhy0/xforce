<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\Branch;
use XLinic\Framework\Core\Model\BaseModel;

class StockMovement extends BaseModel
{
    protected $table = 'stock_movements';

    protected $fillable = [
        'tenant_id',
        'product_id',
        'branch_id',
        'movement_type',
        'quantity',
        'quantity_before',
        'quantity_after',
        'reference_type',
        'reference_id',
        'source_branch_id',
        'destination_branch_id',
        'source_location_id',
        'destination_location_id',
        'notes',
        'created_by',
        'journal_entry_id',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'quantity_before' => 'integer',
        'quantity_after' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Movement types
    public const TYPE_IN = 'in';
    public const TYPE_OUT = 'out';
    public const TYPE_TRANSFER_IN = 'transfer_in';
    public const TYPE_TRANSFER_OUT = 'transfer_out';
    public const TYPE_LOCATION_TRANSFER_IN = 'location_transfer_in';
    public const TYPE_LOCATION_TRANSFER_OUT = 'location_transfer_out';
    public const TYPE_ADJUSTMENT = 'adjustment';
    public const TYPE_PURCHASE_RECEIVE = 'purchase_receive';
    public const TYPE_APPOINTMENT_CONSUME = 'appointment_consume';
    public const TYPE_INVOICE_SALE = 'invoice_sale';
    public const TYPE_RETURN = 'return';
    public const TYPE_WASTE = 'waste';

    public const TYPES = [
        self::TYPE_IN => 'Stock In',
        self::TYPE_OUT => 'Stock Out',
        self::TYPE_TRANSFER_IN => 'Transfer In',
        self::TYPE_TRANSFER_OUT => 'Transfer Out',
        self::TYPE_LOCATION_TRANSFER_IN => 'Location Transfer In',
        self::TYPE_LOCATION_TRANSFER_OUT => 'Location Transfer Out',
        self::TYPE_ADJUSTMENT => 'Adjustment',
        self::TYPE_PURCHASE_RECEIVE => 'Purchase Receive',
        self::TYPE_APPOINTMENT_CONSUME => 'Appointment Consume',
        self::TYPE_INVOICE_SALE => 'Invoice Sale',
        self::TYPE_RETURN => 'Return',
        self::TYPE_WASTE => 'Waste',
    ];

    public const TYPE_COLORS = [
        self::TYPE_IN => 'success',
        self::TYPE_OUT => 'danger',
        self::TYPE_TRANSFER_IN => 'info',
        self::TYPE_TRANSFER_OUT => 'warning',
        self::TYPE_LOCATION_TRANSFER_IN => 'info',
        self::TYPE_LOCATION_TRANSFER_OUT => 'warning',
        self::TYPE_ADJUSTMENT => 'gray',
        self::TYPE_PURCHASE_RECEIVE => 'success',
        self::TYPE_APPOINTMENT_CONSUME => 'danger',
        self::TYPE_INVOICE_SALE => 'success',
        self::TYPE_RETURN => 'info',
        self::TYPE_WASTE => 'danger',
    ];

    /**
     * Get the product.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the branch.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get the source branch for transfers.
     */
    public function sourceBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'source_branch_id');
    }

    /**
     * Get the destination branch for transfers.
     */
    public function destinationBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'destination_branch_id');
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
        return $this->belongsTo(\Modules\Auth\Models\User::class, 'created_by');
    }

    /**
     * Get the linked journal entry.
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(\Modules\Accounting\Models\JournalEntry::class, 'journal_entry_id');
    }

    /**
     * Check if this is an incoming movement.
     */
    public function isIncoming(): bool
    {
        return in_array($this->movement_type, [
            self::TYPE_IN,
            self::TYPE_TRANSFER_IN,
            self::TYPE_LOCATION_TRANSFER_IN,
            self::TYPE_PURCHASE_RECEIVE,
            self::TYPE_RETURN,
        ]);
    }

    /**
     * Check if this is an outgoing movement.
     */
    public function isOutgoing(): bool
    {
        return in_array($this->movement_type, [
            self::TYPE_OUT,
            self::TYPE_TRANSFER_OUT,
            self::TYPE_LOCATION_TRANSFER_OUT,
            self::TYPE_APPOINTMENT_CONSUME,
            self::TYPE_INVOICE_SALE,
            self::TYPE_WASTE,
        ]);
    }

    /**
     * Scope to specific movement types.
     */
    public function scopeOfType($query, string|array $types)
    {
        $types = is_array($types) ? $types : [$types];
        return $query->whereIn('movement_type', $types);
    }

    /**
     * Scope to incoming movements.
     */
    public function scopeIncoming($query)
    {
        return $query->whereIn('movement_type', [
            self::TYPE_IN,
            self::TYPE_TRANSFER_IN,
            self::TYPE_LOCATION_TRANSFER_IN,
            self::TYPE_PURCHASE_RECEIVE,
            self::TYPE_RETURN,
        ]);
    }

    /**
     * Scope to outgoing movements.
     */
    public function scopeOutgoing($query)
    {
        return $query->whereIn('movement_type', [
            self::TYPE_OUT,
            self::TYPE_TRANSFER_OUT,
            self::TYPE_LOCATION_TRANSFER_OUT,
            self::TYPE_APPOINTMENT_CONSUME,
            self::TYPE_INVOICE_SALE,
            self::TYPE_WASTE,
        ]);
    }

    /**
     * Create a stock transfer between branches.
     */
    public static function createTransfer(
        string $productId,
        string $sourceBranchId,
        string $destinationBranchId,
        int $quantity,
        ?string $notes = null
    ): array {
        $product = Product::findOrFail($productId);
        $sourceStock = StockLevel::getOrCreate($productId, $sourceBranchId);
        $destinationStock = StockLevel::getOrCreate($productId, $destinationBranchId);

        // Decrease source
        $outMovement = $sourceStock->decrease(
            $quantity,
            self::TYPE_TRANSFER_OUT,
            'branch_transfer',
            $destinationBranchId,
            $notes ?? "Transfer to branch"
        );
        $outMovement->source_branch_id = $sourceBranchId;
        $outMovement->destination_branch_id = $destinationBranchId;
        $outMovement->save();

        // Increase destination
        $inMovement = $destinationStock->increase(
            $quantity,
            self::TYPE_TRANSFER_IN,
            'branch_transfer',
            $sourceBranchId,
            $notes ?? "Transfer from branch"
        );
        $inMovement->source_branch_id = $sourceBranchId;
        $inMovement->destination_branch_id = $destinationBranchId;
        $inMovement->save();

        return [$outMovement, $inMovement];
    }

    /**
     * Create a stock transfer between locations (within same branch).
     *
     * @param string $productId
     * @param string $sourceLocationId
     * @param string $destinationLocationId
     * @param int $quantity
     * @param string|null $notes
     * @return array [outMovement, inMovement]
     */
    public static function createLocationTransfer(
        string $productId,
        string $sourceLocationId,
        string $destinationLocationId,
        int $quantity,
        ?string $notes = null
    ): array {
        $sourceLocation = StockLocation::findOrFail($sourceLocationId);
        $destLocation = StockLocation::findOrFail($destinationLocationId);

        // Both locations must be in the same branch
        if ($sourceLocation->branch_id !== $destLocation->branch_id) {
            throw new \InvalidArgumentException('Location transfers must be within the same branch');
        }

        $branchId = $sourceLocation->branch_id;

        // Get or create stock levels for both locations
        $sourceStock = StockLevel::getOrCreate($productId, $branchId, $sourceLocationId);
        $destStock = StockLevel::getOrCreate($productId, $branchId, $destinationLocationId);

        // Decrease source location
        $outMovement = $sourceStock->decrease(
            $quantity,
            self::TYPE_LOCATION_TRANSFER_OUT,
            'location_transfer',
            $destinationLocationId,
            $notes ?? "Transfer to " . $destLocation->code
        );
        $outMovement->source_location_id = $sourceLocationId;
        $outMovement->destination_location_id = $destinationLocationId;
        $outMovement->save();

        // Increase destination location
        $inMovement = $destStock->increase(
            $quantity,
            self::TYPE_LOCATION_TRANSFER_IN,
            'location_transfer',
            $sourceLocationId,
            $notes ?? "Transfer from " . $sourceLocation->code
        );
        $inMovement->source_location_id = $sourceLocationId;
        $inMovement->destination_location_id = $destinationLocationId;
        $inMovement->save();

        return [$outMovement, $inMovement];
    }
}
