<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Assets\Models\AssetType;
use Modules\Inventory\Enums\ProductType;
use Spatie\Translatable\HasTranslations;
use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasActivity;
use XLinic\Framework\Core\Model\Traits\HasSequence;

class Product extends BaseModel
{
    use HasTranslations, HasSequence, HasActivity;

    protected $table = 'products';

    public array $translatable = ['name', 'description'];

    protected string $sequenceCode = 'PRD';

    protected string $sequenceColumn = 'sku';

    protected $fillable = [
        'tenant_id',
        'category_id',
        'asset_type_id',
        'sku',
        'name',
        'description',
        'unit',
        'sales_uom_id',
        'purchase_uom_id',
        'cost_price_minor',
        'sell_price_minor',
        'reorder_point',
        'reorder_quantity',
        'lead_time_days',
        'product_type',
        'is_consumable',
        'is_asset',
        'is_active',
        'barcode',
        'image_url',
        // Accounting fields
        'stock_input_account_id',
        'stock_output_account_id',
        'stock_valuation_account_id',
        'income_account_id',
        'expense_account_id',
        'valuation_method',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'cost_price_minor' => 'integer',
        'sell_price_minor' => 'integer',
        'reorder_point' => 'integer',
        'reorder_quantity' => 'integer',
        'lead_time_days' => 'integer',
        'product_type' => ProductType::class,
        'is_consumable' => 'boolean',
        'is_asset' => 'boolean',
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
        'product_type' => 'storable',
        'is_consumable' => true,
        'is_asset' => false,
        'is_active' => true,
        'valuation_method' => 'average',
    ];

    // Valuation Methods
    public const VALUATION_STANDARD = 'standard';
    public const VALUATION_FIFO = 'fifo';
    public const VALUATION_AVERAGE = 'average';

    public const VALUATION_METHODS = [
        self::VALUATION_STANDARD => 'Standard Price',
        self::VALUATION_FIFO => 'First In First Out (FIFO)',
        self::VALUATION_AVERAGE => 'AVCO (Average Cost)',
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
     * Get the asset type for this product.
     */
    public function assetType(): BelongsTo
    {
        return $this->belongsTo(AssetType::class, 'asset_type_id');
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
     * Get stock input account (used when receiving stock).
     */
    public function stockInputAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'stock_input_account_id');
    }

    /**
     * Get stock output account (used when consuming/selling stock).
     */
    public function stockOutputAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'stock_output_account_id');
    }

    /**
     * Get stock valuation account (inventory asset account).
     */
    public function stockValuationAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'stock_valuation_account_id');
    }

    /**
     * Get income account (for sales/invoices).
     */
    public function incomeAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'income_account_id');
    }

    /**
     * Get expense account (for purchases/vendor bills).
     */
    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'expense_account_id');
    }

    /**
     * Get the sales UoM (default unit of measure).
     */
    public function salesUom(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'sales_uom_id');
    }

    /**
     * Get the purchase UoM.
     */
    public function purchaseUom(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'purchase_uom_id');
    }

    /**
     * Scope to active products only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to storable products only (tracks inventory).
     */
    public function scopeStorable($query)
    {
        return $query->where('product_type', ProductType::STORABLE);
    }

    /**
     * Scope to consumable products only (no inventory tracking).
     */
    public function scopeConsumable($query)
    {
        return $query->where('product_type', ProductType::CONSUMABLE);
    }

    /**
     * Scope to products that track inventory.
     */
    public function scopeTracksInventory($query)
    {
        return $query->where('product_type', ProductType::STORABLE);
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

    /**
     * Check if this product tracks inventory levels.
     */
    public function tracksInventory(): bool
    {
        return $this->product_type === ProductType::STORABLE;
    }

    /**
     * Check if this product is storable.
     */
    public function isStorable(): bool
    {
        return $this->product_type === ProductType::STORABLE;
    }

    /**
     * Check if this product is consumable (no inventory tracking).
     */
    public function isConsumableType(): bool
    {
        return $this->product_type === ProductType::CONSUMABLE;
    }

    /**
     * Convert quantity from purchase UoM to sales UoM (stock UoM).
     *
     * @param float $purchaseQuantity Quantity in purchase UoM
     * @return float Quantity in sales UoM
     */
    public function convertPurchaseToStock(float $purchaseQuantity): float
    {
        $purchaseUom = $this->purchaseUom;
        $salesUom = $this->salesUom;

        // If no UoMs set or same UoM, return as-is
        if (!$purchaseUom || !$salesUom || $purchaseUom->id === $salesUom->id) {
            return $purchaseQuantity;
        }

        return $purchaseUom->convertTo($purchaseQuantity, $salesUom);
    }

    /**
     * Convert quantity from sales UoM (stock UoM) to purchase UoM.
     *
     * @param float $stockQuantity Quantity in sales UoM
     * @return float Quantity in purchase UoM
     */
    public function convertStockToPurchase(float $stockQuantity): float
    {
        $purchaseUom = $this->purchaseUom;
        $salesUom = $this->salesUom;

        // If no UoMs set or same UoM, return as-is
        if (!$purchaseUom || !$salesUom || $purchaseUom->id === $salesUom->id) {
            return $stockQuantity;
        }

        return $salesUom->convertTo($stockQuantity, $purchaseUom);
    }

    /**
     * Get the UoM category ID (from sales UoM).
     */
    public function getUomCategoryIdAttribute(): ?int
    {
        return $this->salesUom?->category_id;
    }
}
