<?php

namespace Modules\Website\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTranslation;

class WebsitePage extends BaseModel
{
    use HasTranslation, SoftDeletes;

    protected $table = 'website_pages';

    protected $fillable = [
        'slug',
        'title',
        'meta_title',
        'meta_description',
        'is_homepage',
        'is_published',
        'sort_order',
    ];

    protected $casts = [
        'title' => 'array',
        'meta_title' => 'array',
        'meta_description' => 'array',
        'is_homepage' => 'boolean',
        'is_published' => 'boolean',
        'sort_order' => 'integer',
    ];

    public array $translatable = [
        'title',
        'meta_title',
        'meta_description',
    ];

    protected $appends = ['translated_title'];

    protected static function booted(): void
    {
        parent::booted();

        // Ensure only one homepage exists
        static::saving(function (WebsitePage $page) {
            if ($page->is_homepage) {
                static::where('is_homepage', true)
                    ->where('id', '!=', $page->id)
                    ->update(['is_homepage' => false]);
            }
        });
    }

    public function getTranslatedTitleAttribute(): string
    {
        return $this->getTranslation('title', app()->getLocale())
            ?? $this->getTranslation('title', 'en')
            ?? '';
    }

    public function getMetaTitleForLocaleAttribute(): string
    {
        return $this->getTranslation('meta_title', app()->getLocale())
            ?? $this->getTranslation('meta_title', 'en')
            ?? $this->translated_title;
    }

    public function getMetaDescriptionForLocaleAttribute(): string
    {
        return $this->getTranslation('meta_description', app()->getLocale())
            ?? $this->getTranslation('meta_description', 'en')
            ?? '';
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(WebsiteBlock::class, 'page_id');
    }

    public function visibleBlocks(): HasMany
    {
        return $this->hasMany(WebsiteBlock::class, 'page_id')
            ->where('is_visible', true)
            ->orderBy('sort_order');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function scopeHomepage(Builder $query): Builder
    {
        return $query->where('is_homepage', true);
    }

    public function getUrlAttribute(): string
    {
        if ($this->is_homepage) {
            return '/';
        }

        return '/' . $this->slug;
    }

    public static function findBySlug(string $slug): ?static
    {
        return static::where('slug', $slug)->published()->first();
    }

    public static function getHomepage(): ?static
    {
        return static::homepage()->published()->first();
    }

    public static function getNavigationPages(): \Illuminate\Database\Eloquent\Collection
    {
        return static::published()->ordered()->get();
    }
}
