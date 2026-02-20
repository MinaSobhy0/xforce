<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;
use XLinic\Framework\Core\Model\BaseModel;

class ProductCategory extends BaseModel
{
    use HasTranslations;

    protected $table = 'product_categories';

    public array $translatable = ['name', 'description'];

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'parent_id',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'id' => 'string',
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
     * Get the parent category.
     */
    public function parent()
    {
        return $this->belongsTo(ProductCategory::class, 'parent_id');
    }

    /**
     * Get the child categories.
     */
    public function children(): HasMany
    {
        return $this->hasMany(ProductCategory::class, 'parent_id');
    }

    /**
     * Get the products in this category.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    /**
     * Scope to active categories only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get nested category path.
     */
    public function getPathAttribute(): string
    {
        $path = [$this->name];
        $parent = $this->parent;

        while ($parent) {
            array_unshift($path, $parent->name);
            $parent = $parent->parent;
        }

        return implode(' > ', $path);
    }
}
