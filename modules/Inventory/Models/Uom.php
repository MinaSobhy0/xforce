<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;
use Spatie\Translatable\HasTranslations;
use XLinic\Framework\Core\Model\BaseModel;

class Uom extends BaseModel
{
    use HasTranslations;

    protected $table = 'uoms';

    public array $translatable = ['name'];

    // UoM Types
    public const TYPE_BIGGER = 'bigger';
    public const TYPE_REFERENCE = 'reference';
    public const TYPE_SMALLER = 'smaller';

    public const TYPES = [
        self::TYPE_BIGGER => 'Bigger than reference',
        self::TYPE_REFERENCE => 'Reference Unit',
        self::TYPE_SMALLER => 'Smaller than reference',
    ];

    public const TYPE_COLORS = [
        self::TYPE_BIGGER => 'info',
        self::TYPE_REFERENCE => 'success',
        self::TYPE_SMALLER => 'warning',
    ];

    protected $fillable = [
        'tenant_id',
        'category_id',
        'name',
        'abbreviation',
        'uom_type',
        'ratio',
        'is_reference',
        'is_active',
        'rounding_precision',
    ];

    protected $casts = [
        'name' => 'array',
        'ratio' => 'decimal:10',
        'is_reference' => 'boolean',
        'is_active' => 'boolean',
        'rounding_precision' => 'decimal:6',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'uom_type' => 'reference',
        'ratio' => 1.0,
        'is_reference' => false,
        'is_active' => true,
        'rounding_precision' => 0.01,
    ];

    /**
     * Get the category this UoM belongs to.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(UomCategory::class, 'category_id');
    }

    /**
     * Get products that use this as sales UoM.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'sales_uom_id');
    }

    /**
     * Get products that use this as purchase UoM.
     */
    public function productsPurchaseUom(): HasMany
    {
        return $this->hasMany(Product::class, 'purchase_uom_id');
    }

    /**
     * Scope to active UoMs only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to reference UoMs only.
     */
    public function scopeReference($query)
    {
        return $query->where('is_reference', true);
    }

    /**
     * Scope to filter by category.
     */
    public function scopeInCategory($query, int $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    /**
     * Convert a quantity from this UoM to the reference unit.
     *
     * @param float $quantity The quantity in this UoM
     * @return float The quantity in the reference unit
     */
    public function toReference(float $quantity): float
    {
        $result = $quantity * (float) $this->ratio;
        return $this->applyRounding($result);
    }

    /**
     * Convert a quantity from the reference unit to this UoM.
     *
     * @param float $quantity The quantity in the reference unit
     * @return float The quantity in this UoM
     */
    public function fromReference(float $quantity): float
    {
        if ((float) $this->ratio === 0.0) {
            throw new InvalidArgumentException('Cannot divide by zero ratio');
        }
        $result = $quantity / (float) $this->ratio;
        return $this->applyRounding($result);
    }

    /**
     * Convert a quantity from this UoM to another UoM.
     *
     * @param float $quantity The quantity in this UoM
     * @param Uom $targetUom The target UoM to convert to
     * @return float The quantity in the target UoM
     * @throws InvalidArgumentException If UoMs are from different categories
     */
    public function convertTo(float $quantity, Uom $targetUom): float
    {
        // Verify same category
        if ($this->category_id !== $targetUom->category_id) {
            throw new InvalidArgumentException(
                'Cannot convert between UoMs from different categories'
            );
        }

        // If same UoM, return as-is
        if ($this->id === $targetUom->id) {
            return $quantity;
        }

        // Convert: this -> reference -> target
        $referenceQuantity = $this->toReference($quantity);
        return $targetUom->fromReference($referenceQuantity);
    }

    /**
     * Apply rounding precision to a value.
     */
    protected function applyRounding(float $value): float
    {
        $precision = (float) $this->rounding_precision;
        if ($precision <= 0) {
            return $value;
        }
        return round($value / $precision) * $precision;
    }

    /**
     * Get the display name with abbreviation.
     */
    public function getDisplayNameAttribute(): string
    {
        $name = $this->getTranslation('name', app()->getLocale());
        return "{$name} ({$this->abbreviation})";
    }

    /**
     * Get the type label.
     */
    public function getTypeLabelAttribute(): string
    {
        return __('inventory::inventory.uom_types.' . $this->uom_type);
    }

    /**
     * Get the type color for badges.
     */
    public function getTypeColorAttribute(): string
    {
        return self::TYPE_COLORS[$this->uom_type] ?? 'gray';
    }

    /**
     * Check if this is a bigger unit (e.g., dozen, box).
     */
    public function isBigger(): bool
    {
        return $this->uom_type === self::TYPE_BIGGER;
    }

    /**
     * Check if this is the reference unit.
     */
    public function isReference(): bool
    {
        return $this->is_reference || $this->uom_type === self::TYPE_REFERENCE;
    }

    /**
     * Check if this is a smaller unit (e.g., gram from kg).
     */
    public function isSmaller(): bool
    {
        return $this->uom_type === self::TYPE_SMALLER;
    }
}
