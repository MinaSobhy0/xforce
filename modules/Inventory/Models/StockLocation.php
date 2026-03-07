<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Models\Branch;
use Spatie\Translatable\HasTranslations;
use XLinic\Framework\Core\Model\BaseModel;

class StockLocation extends BaseModel
{
    use HasTranslations;

    protected $table = 'stock_locations';

    public array $translatable = ['name'];

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'parent_id',
        'code',
        'name',
        'location_type',
        'parent_path',
        'level',
        'is_scrap_location',
        'is_return_location',
        'is_treatment_default',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'name' => 'array',
        'is_scrap_location' => 'boolean',
        'is_return_location' => 'boolean',
        'is_treatment_default' => 'boolean',
        'is_active' => 'boolean',
        'level' => 'integer',
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'location_type' => 'internal',
        'level' => 0,
        'is_scrap_location' => false,
        'is_return_location' => false,
        'is_treatment_default' => false,
        'is_active' => true,
        'sort_order' => 0,
    ];

    // Location type constants
    public const TYPE_INTERNAL = 'internal';    // Physical storage (warehouse, shelf, room)
    public const TYPE_VIEW = 'view';            // Virtual container for hierarchy
    public const TYPE_SUPPLIER = 'supplier';    // Virtual origin for purchases
    public const TYPE_CUSTOMER = 'customer';    // Virtual destination for sales
    public const TYPE_INVENTORY = 'inventory';  // Virtual for adjustments

    public const TYPES = [
        self::TYPE_INTERNAL => 'Internal',
        self::TYPE_VIEW => 'View',
        self::TYPE_SUPPLIER => 'Supplier',
        self::TYPE_CUSTOMER => 'Customer',
        self::TYPE_INVENTORY => 'Inventory',
    ];

    public const TYPE_COLORS = [
        self::TYPE_INTERNAL => 'success',
        self::TYPE_VIEW => 'gray',
        self::TYPE_SUPPLIER => 'info',
        self::TYPE_CUSTOMER => 'warning',
        self::TYPE_INVENTORY => 'primary',
    ];

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        parent::booted();

        // Update parent_path and level on save
        static::saving(function (self $location) {
            $location->updateHierarchy();

            // Ensure only one treatment default location per branch
            if ($location->is_treatment_default && $location->branch_id) {
                static::where('branch_id', $location->branch_id)
                    ->where('id', '!=', $location->id ?? 0)
                    ->where('is_treatment_default', true)
                    ->update(['is_treatment_default' => false]);
            }
        });

        // Update children's parent_path when parent changes
        static::saved(function (self $location) {
            if ($location->isDirty('parent_path') || $location->isDirty('code')) {
                $location->updateChildrenPaths();
            }
        });
    }

    /**
     * Update hierarchy information (parent_path and level).
     */
    protected function updateHierarchy(): void
    {
        if ($this->parent_id) {
            $parent = static::find($this->parent_id);
            if ($parent) {
                $this->parent_path = $parent->parent_path
                    ? $parent->parent_path . '/' . $parent->code
                    : $parent->code;
                $this->level = $parent->level + 1;
            }
        } else {
            $this->parent_path = null;
            $this->level = 0;
        }
    }

    /**
     * Update children's parent paths recursively.
     */
    protected function updateChildrenPaths(): void
    {
        $children = $this->children()->get();
        foreach ($children as $child) {
            $child->save(); // This triggers updateHierarchy for each child
        }
    }

    /**
     * Get the branch.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get the parent location.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Get child locations.
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * Get all descendants recursively.
     */
    public function descendants(): HasMany
    {
        return $this->children()->with('descendants');
    }

    /**
     * Get stock levels at this location.
     */
    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class, 'location_id');
    }

    /**
     * Get transfers from this location.
     */
    public function outgoingTransfers(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'source_location_id');
    }

    /**
     * Get transfers to this location.
     */
    public function incomingTransfers(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'destination_location_id');
    }

    /**
     * Scope to active locations only.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to specific location type.
     */
    public function scopeOfType(Builder $query, string|array $types): Builder
    {
        $types = is_array($types) ? $types : [$types];
        return $query->whereIn('location_type', $types);
    }

    /**
     * Scope to internal locations (holds physical stock).
     */
    public function scopeInternal(Builder $query): Builder
    {
        return $query->where('location_type', self::TYPE_INTERNAL);
    }

    /**
     * Scope to root locations (no parent).
     */
    public function scopeRoot(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope to locations that can hold stock.
     */
    public function scopeCanHoldStock(Builder $query): Builder
    {
        return $query->whereIn('location_type', [self::TYPE_INTERNAL]);
    }

    /**
     * Scope to a specific branch.
     */
    public function scopeForBranch(Builder $query, string $branchId): Builder
    {
        return $query->where('branch_id', $branchId);
    }

    /**
     * Check if this location can hold physical stock.
     */
    public function canHoldStock(): bool
    {
        return $this->location_type === self::TYPE_INTERNAL;
    }

    /**
     * Check if this is a view location (hierarchy container only).
     */
    public function isView(): bool
    {
        return $this->location_type === self::TYPE_VIEW;
    }

    /**
     * Check if this is a virtual location.
     */
    public function isVirtual(): bool
    {
        return in_array($this->location_type, [
            self::TYPE_VIEW,
            self::TYPE_SUPPLIER,
            self::TYPE_CUSTOMER,
            self::TYPE_INVENTORY,
        ]);
    }

    /**
     * Get the full path as a string.
     */
    public function getFullPathAttribute(): string
    {
        if ($this->parent_path) {
            return $this->parent_path . '/' . $this->code;
        }
        return $this->code;
    }

    /**
     * Get the full path with names (for display).
     */
    public function getFullPathNameAttribute(): string
    {
        $locale = app()->getLocale();

        if (!$this->parent_id) {
            return $this->getTranslation('name', $locale);
        }

        $names = [];
        $current = $this;

        while ($current) {
            array_unshift($names, $current->getTranslation('name', $locale));
            $current = $current->parent;
        }

        return implode(' / ', $names);
    }

    /**
     * Get all ancestor locations.
     */
    public function getAncestors(): array
    {
        $ancestors = [];
        $current = $this->parent;

        while ($current) {
            array_unshift($ancestors, $current);
            $current = $current->parent;
        }

        return $ancestors;
    }

    /**
     * Get indented name for select options.
     */
    public function getIndentedNameAttribute(): string
    {
        $indent = str_repeat('— ', $this->level);
        return $indent . $this->getTranslation('name', app()->getLocale());
    }

    /**
     * Get total stock quantity at this location.
     */
    public function getTotalStockAttribute(): int
    {
        return $this->stockLevels()->sum('quantity_on_hand');
    }

    /**
     * Get the default internal location for a branch.
     */
    public static function getDefaultLocation(string $branchId): ?self
    {
        return static::where('branch_id', $branchId)
            ->where('code', 'WH/STOCK')
            ->where('location_type', self::TYPE_INTERNAL)
            ->first();
    }

    /**
     * Get the default receiving location for a branch.
     */
    public static function getInputLocation(string $branchId): ?self
    {
        return static::where('branch_id', $branchId)
            ->where('code', 'WH/INPUT')
            ->where('location_type', self::TYPE_INTERNAL)
            ->first();
    }

    /**
     * Get the scrap location for a branch.
     */
    public static function getScrapLocation(string $branchId): ?self
    {
        return static::where('branch_id', $branchId)
            ->where('is_scrap_location', true)
            ->first();
    }

    /**
     * Get the default treatment location for a branch.
     * Used for auto-deducting consumables during treatment sessions.
     */
    public static function getTreatmentDefaultLocation(string $branchId): ?self
    {
        return static::where('branch_id', $branchId)
            ->where('is_treatment_default', true)
            ->where('is_active', true)
            ->first();
    }

    // =========================================================================
    // VIRTUAL LOCATION HELPERS (Odoo-like)
    // =========================================================================

    /**
     * Get the supplier virtual location for a branch.
     * Used as source location for purchase receipts.
     */
    public static function getSupplierLocation(string $branchId): ?self
    {
        return static::where('branch_id', $branchId)
            ->where('location_type', self::TYPE_SUPPLIER)
            ->first();
    }

    /**
     * Get the customer virtual location for a branch.
     * Used as destination for sales/consumption.
     */
    public static function getCustomerLocation(string $branchId): ?self
    {
        return static::where('branch_id', $branchId)
            ->where('location_type', self::TYPE_CUSTOMER)
            ->first();
    }

    /**
     * Get the inventory adjustment virtual location for a branch.
     * Used for inventory gains/losses.
     */
    public static function getInventoryAdjustmentLocation(string $branchId): ?self
    {
        return static::where('branch_id', $branchId)
            ->where('location_type', self::TYPE_INVENTORY)
            ->first();
    }

    /**
     * Check if this is a physical location (holds actual stock).
     */
    public function isPhysical(): bool
    {
        return $this->location_type === self::TYPE_INTERNAL;
    }

    /**
     * Scope to physical locations only (internal type).
     */
    public function scopePhysical(Builder $query): Builder
    {
        return $query->where('location_type', self::TYPE_INTERNAL);
    }

    /**
     * Scope to virtual locations only (non-internal types).
     */
    public function scopeVirtualLocations(Builder $query): Builder
    {
        return $query->whereIn('location_type', [
            self::TYPE_SUPPLIER,
            self::TYPE_CUSTOMER,
            self::TYPE_INVENTORY,
        ]);
    }

    /**
     * Get or create all required virtual locations for a branch.
     * Called during branch/tenant provisioning.
     */
    public static function ensureVirtualLocations(string $branchId, ?string $tenantId = null): void
    {
        $virtualLocations = [
            [
                'code' => 'Partner/Vendors',
                'name' => ['en' => 'Vendors', 'ar' => 'الموردين'],
                'location_type' => self::TYPE_SUPPLIER,
            ],
            [
                'code' => 'Partner/Customers',
                'name' => ['en' => 'Customers', 'ar' => 'العملاء'],
                'location_type' => self::TYPE_CUSTOMER,
            ],
            [
                'code' => 'Virtual/Adjustment',
                'name' => ['en' => 'Inventory Adjustment', 'ar' => 'تسوية المخزون'],
                'location_type' => self::TYPE_INVENTORY,
            ],
        ];

        foreach ($virtualLocations as $location) {
            static::firstOrCreate(
                [
                    'branch_id' => $branchId,
                    'code' => $location['code'],
                ],
                [
                    'tenant_id' => $tenantId,
                    'name' => $location['name'],
                    'location_type' => $location['location_type'],
                    'is_active' => true,
                ]
            );
        }
    }
}
