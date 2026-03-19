<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Accounting\Models\ChartOfAccount;
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
        'allow_negative_stock',
        'stock_valuation_account_id',
        'stock_input_account_id',
        'stock_output_account_id',
        'expense_account_id',
        'sort_order',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'is_active' => 'boolean',
        'allow_negative_stock' => 'boolean',
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'is_active' => true,
        'allow_negative_stock' => false,
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
     * Get the stock valuation account.
     */
    public function stockValuationAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'stock_valuation_account_id');
    }

    /**
     * Get the stock input account.
     */
    public function stockInputAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'stock_input_account_id');
    }

    /**
     * Get the stock output account.
     */
    public function stockOutputAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'stock_output_account_id');
    }

    /**
     * Get the expense account.
     */
    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'expense_account_id');
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
