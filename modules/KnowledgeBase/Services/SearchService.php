<?php

namespace Modules\KnowledgeBase\Services;

use Modules\KnowledgeBase\Models\HelpArticle;
use Modules\KnowledgeBase\Models\HelpSearchLog;

class SearchService
{
    protected int $minLength;
    protected int $resultsLimit;
    protected bool $enableLogging;

    public function __construct()
    {
        $this->minLength = config('knowledgebase.search_min_length', 2);
        $this->resultsLimit = config('knowledgebase.search_results_limit', 20);
        $this->enableLogging = config('knowledgebase.enable_search_logging', true);
    }

    /**
     * Search articles.
     */
    public function search(
        string $query,
        string $locale = 'en',
        ?string $panel = null,
        ?int $categoryId = null,
        int $limit = 0,
        ?int $tenantId = null,
        ?int $userId = null,
        ?string $sessionId = null,
        ?string $screenKey = null
    ): array {
        $query = trim($query);

        // Validate query length
        if (strlen($query) < $this->minLength) {
            return [
                'results' => [],
                'total' => 0,
                'query' => $query,
                'error' => "Search query must be at least {$this->minLength} characters",
            ];
        }

        $searchQuery = HelpArticle::active()
            ->search($query, $locale);

        // Filter by panel
        if ($panel) {
            $searchQuery->forPanel($panel);
        }

        // Filter by category
        if ($categoryId) {
            $searchQuery->where('category_id', $categoryId);
        }

        // Get total count before limiting
        $total = $searchQuery->count();

        // Apply limit
        $effectiveLimit = $limit > 0 ? $limit : $this->resultsLimit;
        $results = $searchQuery
            ->ordered()
            ->limit($effectiveLimit)
            ->get()
            ->map(fn ($article) => [
                'id' => $article->id,
                'title' => $article->getTranslation('title', $locale),
                'excerpt' => $article->getTranslation('excerpt', $locale) ?: $this->generateExcerpt($article->getTranslation('content', $locale), $query),
                'slug' => $article->slug,
                'category' => $article->category?->getTranslation('name', $locale),
                'category_id' => $article->category_id,
                'is_featured' => $article->is_featured,
                'relevance_score' => $this->calculateRelevance($article, $query, $locale),
            ])
            ->sortByDesc('relevance_score')
            ->values()
            ->toArray();

        // Log the search
        if ($this->enableLogging) {
            $this->logSearch($query, $locale, count($results), $tenantId, $userId, $sessionId, $panel, $screenKey);
        }

        return [
            'results' => $results,
            'total' => $total,
            'query' => $query,
            'limit' => $effectiveLimit,
        ];
    }

    /**
     * Calculate relevance score for an article.
     */
    protected function calculateRelevance(HelpArticle $article, string $query, string $locale): int
    {
        $score = 0;
        $queryLower = strtolower($query);

        // Title match (highest weight)
        $title = strtolower($article->getTranslation('title', $locale) ?? '');
        if (str_contains($title, $queryLower)) {
            $score += 100;
            if (str_starts_with($title, $queryLower)) {
                $score += 50;
            }
        }

        // Exact word match in title
        if (preg_match('/\b' . preg_quote($queryLower, '/') . '\b/', $title)) {
            $score += 30;
        }

        // Excerpt match
        $excerpt = strtolower($article->getTranslation('excerpt', $locale) ?? '');
        if (str_contains($excerpt, $queryLower)) {
            $score += 40;
        }

        // Content match
        $content = strtolower($article->getTranslation('content', $locale) ?? '');
        if (str_contains($content, $queryLower)) {
            $score += 20;
            // Count occurrences (max 5 points per occurrence, capped at 25)
            $occurrences = substr_count($content, $queryLower);
            $score += min($occurrences * 5, 25);
        }

        // Tag match
        $tags = $article->tags ?? [];
        foreach ($tags as $tag) {
            if (str_contains(strtolower($tag), $queryLower)) {
                $score += 30;
                break;
            }
        }

        // Featured articles get a boost
        if ($article->is_featured) {
            $score += 15;
        }

        // Popular articles get a boost
        if ($article->view_count > 100) {
            $score += 10;
        }

        // Helpful articles get a boost
        if ($article->helpfulness_percentage && $article->helpfulness_percentage > 70) {
            $score += 10;
        }

        return $score;
    }

    /**
     * Generate excerpt from content around the search query.
     */
    protected function generateExcerpt(string $content, string $query, int $length = 150): string
    {
        // Strip HTML tags
        $content = strip_tags($content);

        // Find position of query in content
        $position = stripos($content, $query);

        if ($position === false) {
            // Query not found, return beginning of content
            return Str::limit($content, $length);
        }

        // Calculate start position (try to center the query in the excerpt)
        $start = max(0, $position - ($length / 2));

        // Adjust to word boundary
        if ($start > 0) {
            $start = strpos($content, ' ', $start) ?: $start;
        }

        $excerpt = substr($content, $start, $length);

        // Add ellipsis if needed
        if ($start > 0) {
            $excerpt = '...' . ltrim($excerpt);
        }
        if (strlen($content) > $start + $length) {
            $excerpt = rtrim($excerpt) . '...';
        }

        return $excerpt;
    }

    /**
     * Log a search query.
     */
    protected function logSearch(
        string $query,
        string $locale,
        int $resultsCount,
        ?int $tenantId,
        ?int $userId,
        ?string $sessionId,
        ?string $panel,
        ?string $screenKey
    ): void {
        HelpSearchLog::create([
            'query' => $query,
            'locale' => $locale,
            'results_count' => $resultsCount,
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'session_id' => $sessionId,
            'panel' => $panel,
            'screen_key' => $screenKey,
            'ip_address' => request()->ip(),
        ]);
    }

    /**
     * Record that a user clicked on a search result.
     */
    public function recordClick(int $searchLogId, int $articleId): void
    {
        HelpSearchLog::where('id', $searchLogId)->update([
            'clicked_article_id' => $articleId,
        ]);
    }

    /**
     * Get search suggestions based on popular queries.
     */
    public function getSuggestions(string $prefix, int $limit = 5): array
    {
        if (strlen($prefix) < 2) {
            return [];
        }

        return HelpSearchLog::query()
            ->selectRaw('LOWER(query) as suggestion, COUNT(*) as count')
            ->whereRaw('LOWER(query) LIKE ?', [strtolower($prefix) . '%'])
            ->where('results_count', '>', 0)
            ->groupBy('suggestion')
            ->orderByDesc('count')
            ->limit($limit)
            ->pluck('suggestion')
            ->toArray();
    }

    /**
     * Get analytics for search.
     */
    public function getAnalytics(?int $days = 30, ?int $tenantId = null): array
    {
        $since = now()->subDays($days);

        $baseQuery = HelpSearchLog::where('created_at', '>=', $since);

        if ($tenantId) {
            $baseQuery->where('tenant_id', $tenantId);
        }

        return [
            'total_searches' => (clone $baseQuery)->count(),
            'searches_with_results' => (clone $baseQuery)->where('results_count', '>', 0)->count(),
            'searches_without_results' => (clone $baseQuery)->where('results_count', 0)->count(),
            'searches_with_clicks' => (clone $baseQuery)->whereNotNull('clicked_article_id')->count(),
            'top_queries' => HelpSearchLog::getTopQueries(10, $tenantId),
            'queries_without_results' => HelpSearchLog::getQueriesWithNoResults(10, $tenantId),
        ];
    }
}

// Import the Str helper
use Illuminate\Support\Str;
