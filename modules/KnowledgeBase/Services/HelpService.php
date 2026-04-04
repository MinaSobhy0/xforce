<?php

namespace Modules\KnowledgeBase\Services;

use Illuminate\Support\Facades\Cache;
use Modules\KnowledgeBase\Models\HelpArticle;
use Modules\KnowledgeBase\Models\HelpCategory;
use Modules\KnowledgeBase\Models\HelpScreenGuide;
use Modules\KnowledgeBase\Models\HelpUserProgress;
use Modules\KnowledgeBase\Models\HelpArticleFeedback;

class HelpService
{
    protected string $cachePrefix;
    protected int $cacheTtl;

    public function __construct()
    {
        $this->cachePrefix = config('knowledgebase.cache_prefix', 'kb_');
        $this->cacheTtl = config('knowledgebase.cache_ttl', 3600);
    }

    /**
     * Get articles for a specific screen.
     */
    public function getArticlesForScreen(string $screenKey, string $panel = 'tenant', string $locale = 'en', int $limit = 5): array
    {
        $cacheKey = "{$this->cachePrefix}articles_{$screenKey}_{$panel}_{$locale}_{$limit}";

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($screenKey, $panel, $locale, $limit) {
            return HelpArticle::active()
                ->forScreen($screenKey)
                ->forPanel($panel)
                ->ordered()
                ->limit($limit)
                ->get()
                ->map(fn ($article) => [
                    'id' => $article->id,
                    'title' => $article->getTranslation('title', $locale),
                    'excerpt' => $article->getTranslation('excerpt', $locale),
                    'slug' => $article->slug,
                    'category' => $article->category?->getTranslation('name', $locale),
                ])
                ->toArray();
        });
    }

    /**
     * Get a guide for a specific screen.
     */
    public function getGuideForScreen(string $screenKey, string $panel = 'tenant', string $locale = 'en'): ?array
    {
        $cacheKey = "{$this->cachePrefix}guide_{$screenKey}_{$panel}_{$locale}";

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($screenKey, $panel, $locale) {
            $guide = HelpScreenGuide::active()
                ->forScreen($screenKey)
                ->forPanel($panel)
                ->with('steps')
                ->first();

            if (!$guide) {
                return null;
            }

            return $guide->toGuideFormat($locale);
        });
    }

    /**
     * Check if user should see guide on first visit.
     */
    public function shouldShowGuide(int $userId, string $screenKey, string $panel = 'tenant'): bool
    {
        // Check if guide exists for this screen
        $guide = HelpScreenGuide::active()
            ->forScreen($screenKey)
            ->forPanel($panel)
            ->first();

        if (!$guide || !$guide->show_on_first_visit) {
            return false;
        }

        // Check if user has already seen this guide
        return !HelpUserProgress::hasSeen($userId, $guide->id);
    }

    /**
     * Get article by ID or slug.
     */
    public function getArticle(int|string $idOrSlug, string $locale = 'en'): ?array
    {
        $article = is_numeric($idOrSlug)
            ? HelpArticle::active()->find($idOrSlug)
            : HelpArticle::active()->where('slug', $idOrSlug)->first();

        if (!$article) {
            return null;
        }

        // Increment view count
        $article->incrementViewCount();

        return [
            'id' => $article->id,
            'title' => $article->getTranslation('title', $locale),
            'content' => $article->getTranslation('content', $locale),
            'excerpt' => $article->getTranslation('excerpt', $locale),
            'slug' => $article->slug,
            'category' => $article->category ? [
                'id' => $article->category->id,
                'name' => $article->category->getTranslation('name', $locale),
                'slug' => $article->category->slug,
            ] : null,
            'tags' => $article->tags ?? [],
            'view_count' => $article->view_count,
            'helpful_count' => $article->helpful_count,
            'not_helpful_count' => $article->not_helpful_count,
            'helpfulness_percentage' => $article->helpfulness_percentage,
            'related' => $article->getRelatedArticles(3)->map(fn ($a) => [
                'id' => $a->id,
                'title' => $a->getTranslation('title', $locale),
                'slug' => $a->slug,
            ])->toArray(),
        ];
    }

    /**
     * Get all categories with article counts.
     */
    public function getCategories(string $locale = 'en', bool $includeEmpty = false): array
    {
        $cacheKey = "{$this->cachePrefix}categories_{$locale}_" . ($includeEmpty ? '1' : '0');

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($locale, $includeEmpty) {
            $categories = HelpCategory::active()
                ->root()
                ->with(['children' => fn ($q) => $q->active()->ordered()])
                ->ordered()
                ->get();

            return $categories
                ->filter(fn ($cat) => $includeEmpty || $cat->total_articles_count > 0)
                ->map(fn ($category) => $this->formatCategory($category, $locale, $includeEmpty))
                ->values()
                ->toArray();
        });
    }

    /**
     * Format a category for output.
     */
    protected function formatCategory(HelpCategory $category, string $locale, bool $includeEmpty = false): array
    {
        return [
            'id' => $category->id,
            'name' => $category->getTranslation('name', $locale),
            'description' => $category->getTranslation('description', $locale),
            'slug' => $category->slug,
            'icon' => $category->icon,
            'articles_count' => $category->total_articles_count,
            'children' => $category->children
                ->filter(fn ($cat) => $includeEmpty || $cat->total_articles_count > 0)
                ->map(fn ($child) => $this->formatCategory($child, $locale, $includeEmpty))
                ->values()
                ->toArray(),
        ];
    }

    /**
     * Get articles in a category.
     */
    public function getArticlesInCategory(int|string $categoryIdOrSlug, string $locale = 'en', int $limit = 20): array
    {
        $category = is_numeric($categoryIdOrSlug)
            ? HelpCategory::active()->find($categoryIdOrSlug)
            : HelpCategory::active()->where('slug', $categoryIdOrSlug)->first();

        if (!$category) {
            return [];
        }

        return HelpArticle::active()
            ->where('category_id', $category->id)
            ->ordered()
            ->limit($limit)
            ->get()
            ->map(fn ($article) => [
                'id' => $article->id,
                'title' => $article->getTranslation('title', $locale),
                'excerpt' => $article->getTranslation('excerpt', $locale),
                'slug' => $article->slug,
                'is_featured' => $article->is_featured,
            ])
            ->toArray();
    }

    /**
     * Get featured articles.
     */
    public function getFeaturedArticles(string $locale = 'en', int $limit = 6): array
    {
        $cacheKey = "{$this->cachePrefix}featured_{$locale}_{$limit}";

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($locale, $limit) {
            return HelpArticle::active()
                ->featured()
                ->ordered()
                ->limit($limit)
                ->get()
                ->map(fn ($article) => [
                    'id' => $article->id,
                    'title' => $article->getTranslation('title', $locale),
                    'excerpt' => $article->getTranslation('excerpt', $locale),
                    'slug' => $article->slug,
                    'category' => $article->category?->getTranslation('name', $locale),
                ])
                ->toArray();
        });
    }

    /**
     * Submit feedback for an article.
     */
    public function submitFeedback(
        int $articleId,
        bool $isHelpful,
        ?int $tenantId = null,
        ?int $userId = null,
        ?string $comment = null,
        ?string $sessionId = null,
        ?string $ipAddress = null
    ): bool {
        // Check for duplicate feedback
        $existing = HelpArticleFeedback::where('article_id', $articleId)
            ->where(function ($q) use ($tenantId, $userId, $sessionId) {
                if ($userId) {
                    $q->where('tenant_id', $tenantId)->where('user_id', $userId);
                } elseif ($sessionId) {
                    $q->where('session_id', $sessionId);
                }
            })
            ->first();

        if ($existing) {
            // Update existing feedback
            $existing->update([
                'is_helpful' => $isHelpful,
                'comment' => $comment,
            ]);
        } else {
            // Create new feedback
            HelpArticleFeedback::create([
                'article_id' => $articleId,
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'is_helpful' => $isHelpful,
                'comment' => $comment,
                'session_id' => $sessionId,
                'ip_address' => $ipAddress,
            ]);
        }

        return true;
    }

    /**
     * Get user's feedback for an article.
     */
    public function getUserFeedback(int $articleId, ?int $tenantId, ?int $userId, ?string $sessionId = null): ?bool
    {
        $feedback = HelpArticleFeedback::where('article_id', $articleId)
            ->where(function ($q) use ($tenantId, $userId, $sessionId) {
                if ($userId) {
                    $q->where('tenant_id', $tenantId)->where('user_id', $userId);
                } elseif ($sessionId) {
                    $q->where('session_id', $sessionId);
                }
            })
            ->first();

        return $feedback?->is_helpful;
    }

    /**
     * Clear cache for a specific screen or all cache.
     */
    public function clearCache(?string $screenKey = null): void
    {
        if ($screenKey) {
            // Clear specific screen cache
            Cache::forget("{$this->cachePrefix}articles_{$screenKey}_*");
            Cache::forget("{$this->cachePrefix}guide_{$screenKey}_*");
        } else {
            // Clear all knowledge base cache
            // Note: This is a simplified approach. In production, use cache tags.
            Cache::flush();
        }
    }
}
