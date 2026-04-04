<?php

namespace Modules\KnowledgeBase\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

/**
 * Help Article model - lives in public schema.
 * Uses base Laravel Model to avoid tenant scoping.
 */
class HelpArticle extends Model
{
    use SoftDeletes;
    use HasTranslations;

    protected $table = 'public.help_articles';
    protected $connection = 'central';

    public array $translatable = ['title', 'content', 'excerpt'];

    protected $fillable = [
        'category_id',
        'title',
        'content',
        'excerpt',
        'slug',
        'screen_key',
        'panel',
        'tags',
        'related_screens',
        'is_featured',
        'is_active',
        'sort_order',
        'view_count',
        'helpful_count',
        'not_helpful_count',
    ];

    protected $casts = [
        'title' => 'array',
        'content' => 'array',
        'excerpt' => 'array',
        'tags' => 'array',
        'related_screens' => 'array',
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'view_count' => 'integer',
        'helpful_count' => 'integer',
        'not_helpful_count' => 'integer',
    ];

    /**
     * Get the category.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(HelpCategory::class, 'category_id');
    }

    /**
     * Get feedback for this article.
     */
    public function feedback(): HasMany
    {
        return $this->hasMany(HelpArticleFeedback::class, 'article_id');
    }

    /**
     * Scope: Active articles only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Featured articles only.
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope: Order by sort order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('title->en');
    }

    /**
     * Scope: Filter by screen key.
     */
    public function scopeForScreen($query, string $screenKey)
    {
        return $query->where(function ($q) use ($screenKey) {
            $q->where('screen_key', $screenKey)
              ->orWhereJsonContains('related_screens', $screenKey);
        });
    }

    /**
     * Scope: Filter by panel.
     */
    public function scopeForPanel($query, string $panel)
    {
        return $query->where(function ($q) use ($panel) {
            $q->where('panel', $panel)
              ->orWhereNull('panel');
        });
    }

    /**
     * Scope: Search articles.
     */
    public function scopeSearch($query, string $search, string $locale = 'en')
    {
        $searchLower = strtolower($search);

        return $query->where(function ($q) use ($searchLower, $locale) {
            // Search in title
            $q->whereRaw("LOWER(title->>?) ILIKE ?", [$locale, "%{$searchLower}%"])
              // Search in content
              ->orWhereRaw("LOWER(content->>?) ILIKE ?", [$locale, "%{$searchLower}%"])
              // Search in excerpt
              ->orWhereRaw("LOWER(excerpt->>?) ILIKE ?", [$locale, "%{$searchLower}%"])
              // Search in tags
              ->orWhereRaw("EXISTS (SELECT 1 FROM jsonb_array_elements_text(tags) AS tag WHERE LOWER(tag) ILIKE ?)", ["%{$searchLower}%"]);
        });
    }

    /**
     * Increment view count.
     */
    public function incrementViewCount(): void
    {
        $this->increment('view_count');
    }

    /**
     * Update feedback counts.
     */
    public function updateFeedbackCounts(): void
    {
        $this->update([
            'helpful_count' => $this->feedback()->where('is_helpful', true)->count(),
            'not_helpful_count' => $this->feedback()->where('is_helpful', false)->count(),
        ]);
    }

    /**
     * Get helpfulness percentage.
     */
    public function getHelpfulnessPercentageAttribute(): ?float
    {
        $total = $this->helpful_count + $this->not_helpful_count;

        if ($total === 0) {
            return null;
        }

        return round(($this->helpful_count / $total) * 100, 1);
    }

    /**
     * Get related articles based on tags and category.
     */
    public function getRelatedArticles(int $limit = 5)
    {
        return static::active()
            ->where('id', '!=', $this->id)
            ->where(function ($query) {
                // Same category
                if ($this->category_id) {
                    $query->where('category_id', $this->category_id);
                }

                // Matching tags
                if (!empty($this->tags)) {
                    foreach ($this->tags as $tag) {
                        $query->orWhereJsonContains('tags', $tag);
                    }
                }
            })
            ->ordered()
            ->limit($limit)
            ->get();
    }
}
