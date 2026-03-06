<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Translatable\HasTranslations;
use XLinic\Framework\Core\Model\BaseModel;

class UomCategory extends BaseModel
{
    use HasTranslations;

    protected $table = 'uom_categories';

    public array $translatable = ['name', 'description'];

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'is_active' => true,
        'sort_order' => 0,
    ];

    /**
     * Get all UoMs in this category.
     */
    public function uoms(): HasMany
    {
        return $this->hasMany(Uom::class, 'category_id');
    }

    /**
     * Get active UoMs in this category.
     */
    public function activeUoms(): HasMany
    {
        return $this->uoms()->where('is_active', true);
    }

    /**
     * Get the reference unit for this category.
     */
    public function referenceUom(): HasOne
    {
        return $this->hasOne(Uom::class, 'category_id')->where('is_reference', true);
    }

    /**
     * Get the reference unit (alias for convenience).
     */
    public function getReferenceUnit(): ?Uom
    {
        return $this->referenceUom;
    }

    /**
     * Scope to active categories only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to order by sort_order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Get the number of UoMs in this category.
     */
    public function getUomCountAttribute(): int
    {
        return $this->uoms()->count();
    }
}
