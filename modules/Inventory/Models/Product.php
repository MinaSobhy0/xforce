<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;
use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasSequence;

class Product extends BaseModel
{
    use HasTranslations, HasSequence;

    protected $table = 'products';

    public array $translatable = ['name', 'description'];

    protected static string $sequenceCode = 'PRD';

    protected static string $sequenceField = 'sku';

    protected $fillable = [
        'tenant_id',
        'category_id',
        'sku',
        'name',
        'description',
        'unit',
        'cost_price_minor',
        'sell_price_minor',
        'reorder_point',
        'reorder_quantity',
        'lead_time_days',
        'is_consumable',
        'is_active',
        'barcode',
        'image_url',
    ];

    protected $casts = [
        'id' => 'string',
        'name' => 'array',
        'description' => 'array',
        'cost_price_minor' => 'integer',
        'sell_price_minor' => 'integer',
        'reorder_point' => 'integer',
        'reorder_quantity' => 'integer',
        'lead_time_days' => 'integer',
        'is_consumable' => 'boolean',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'unit' => 'pcs',
        'cost_price_minor' => 0,
        'sell_price_minor' => 0,
        'reorder_point' => 10,
        'reorder_quantity' => 50,
        'lead_time_days' => 7,
        'is_consumable' => true,
        'is_active' => true,
    ];

    // Common units
    public const UNIT_PCS = 'pcs';
    public const UNIT_BOX = 'box';
    public const UNIT_ML = 'ml';
    public const UNIT_L = 'l';
    public const UNIT_G = 'g';
    public const UNIT_KG = 'kg';

    public const UNITS = [
        self::UNIT_PCS => 'Pieces',
        self::UNIT_BOX => 'Box',
        self::UNIT_ML => 'Milliliters',
        self::UNIT_L => 'Liters',
        self::UNIT_G => 'Grams',
        self::UNIT_KG => 'Kilograms',
    ];

    /**
     * Get the category.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    /**
     * Get stock levels across all branches.
     */
    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    /**
     * Get stock movements.
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Get purchase order lines.
     */
    public function purchaseOrderLines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }

    /**
     * Scope to active products only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to consumable products only.
     */
    public function scopeConsumable($query)
    {
        return $query->where('is_consumable', true);
    }

    /**
     * Get total stock across all branches.
     */
    public function getTotalStockAttribute(): int
    {
        return $this->stockLevels->sum('quantity_on_hand');
    }

    /**
     * Get stock level for a specific branch.
     */
    public function getStockForBranch(string $branchId): ?StockLevel
    {
        return $this->stockLevels()->where('branch_id', $branchId)->first();
    }

    /**
     * Check if product is low on stock for any branch.
     */
    public function isLowStock(): bool
    {
        return $this->stockLevels()
            ->whereRaw('quantity_on_hand <= ?', [$this->reorder_point])
            ->exists();
    }

    /**
     * Get branches with low stock.
     */
    public function getLowStockBranches()
    {
        return $this->stockLevels()
            ->whereRaw('quantity_on_hand <= ?', [$this->reorder_point])
            ->with('branch')
            ->get();
    }

    /**
     * Get cost price in major units (for display).
     */
    public function getCostPriceAttribute(): float
    {
        return $this->cost_price_minor / 100;
    }

    /**
     * Get sell price in major units (for display).
     */
    public function getSellPriceAttribute(): float
    {
        return $this->sell_price_minor / 100;
    }
}
