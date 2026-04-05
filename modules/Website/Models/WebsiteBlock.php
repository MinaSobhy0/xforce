<?php

namespace Modules\Website\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use XLinic\Framework\Core\Model\BaseModel;

class WebsiteBlock extends BaseModel
{
    protected $table = 'website_blocks';

    protected $fillable = [
        'page_id',
        'type',
        'content',
        'settings',
        'sort_order',
        'is_visible',
    ];

    protected $casts = [
        'content' => 'array',
        'settings' => 'array',
        'sort_order' => 'integer',
        'is_visible' => 'boolean',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(WebsitePage::class, 'page_id');
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_visible', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Get translated content value.
     */
    public function getContentValue(string $key, ?string $locale = null): mixed
    {
        $locale = $locale ?? app()->getLocale();
        $content = $this->content[$key] ?? null;

        if (is_array($content) && isset($content[$locale])) {
            return $content[$locale];
        }

        if (is_array($content) && isset($content['en'])) {
            return $content['en'];
        }

        return $content;
    }

    /**
     * Get setting value.
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    /**
     * Get the view name for this block type.
     */
    public function getViewName(): string
    {
        return 'website::blocks.' . $this->type;
    }

    /**
     * Get available block types.
     */
    public static function getBlockTypes(): array
    {
        return config('website.block_types', []);
    }

    /**
     * Render the block.
     */
    public function render(): string
    {
        $viewName = $this->getViewName();

        if (!view()->exists($viewName)) {
            return "<!-- Block type '{$this->type}' view not found -->";
        }

        return view($viewName, [
            'block' => $this,
            'content' => $this->content ?? [],
            'settings' => $this->settings ?? [],
            'locale' => app()->getLocale(),
        ])->render();
    }
}
