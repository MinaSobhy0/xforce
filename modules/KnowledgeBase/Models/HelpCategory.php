<?php

namespace Modules\KnowledgeBase\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

/**
 * Help Category model - lives in public schema.
 * Uses base Laravel Model to avoid tenant scoping.
 */
class HelpCategory extends Model
{
    use SoftDeletes;
    use HasTranslations;

    protected $table = 'public.help_categories';
    protected $connection = 'central';

    public array $translatable = ['name', 'description'];

    protected $fillable = [
        'parent_id',
        'name',
        'description',
        'slug',
        'icon',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Get the parent category.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(HelpCategory::class, 'parent_id');
    }

    /**
     * Get child categories.
     */
    public function children(): HasMany
    {
        return $this->hasMany(HelpCategory::class, 'parent_id')->ordered();
    }

    /**
     * Get articles in this category.
     */
    public function articles(): HasMany
    {
        return $this->hasMany(HelpArticle::class, 'category_id');
    }

    /**
     * Scope: Active categories only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Root categories only (no parent).
     */
    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope: Order by sort order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name->en');
    }

    /**
     * Get all ancestor categories.
     */
    public function getAncestorsAttribute(): array
    {
        $ancestors = [];
        $parent = $this->parent;

        while ($parent) {
            array_unshift($ancestors, $parent);
            $parent = $parent->parent;
        }

        return $ancestors;
    }

    /**
     * Get the full path (breadcrumb) for display.
     */
    public function getFullPathAttribute(): string
    {
        $path = collect($this->ancestors)->pluck('name')->toArray();
        $path[] = $this->name;

        return implode(' > ', $path);
    }

    /**
     * Get article count including children.
     */
    public function getTotalArticlesCountAttribute(): int
    {
        $count = $this->articles()->active()->count();

        foreach ($this->children as $child) {
            $count += $child->total_articles_count;
        }

        return $count;
    }
}
