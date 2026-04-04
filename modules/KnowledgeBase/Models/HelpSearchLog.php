<?php

namespace Modules\KnowledgeBase\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Help Search Log model - lives in public schema.
 * Tracks search queries for analytics.
 */
class HelpSearchLog extends Model
{
    protected $table = 'public.help_search_logs';
    protected $connection = 'central';

    protected $fillable = [
        'query',
        'locale',
        'results_count',
        'tenant_id',
        'user_id',
        'session_id',
        'panel',
        'screen_key',
        'clicked_article_id',
        'ip_address',
    ];

    protected $casts = [
        'results_count' => 'integer',
        'tenant_id' => 'integer',
        'user_id' => 'integer',
        'clicked_article_id' => 'integer',
    ];

    /**
     * Get the clicked article if any.
     */
    public function clickedArticle(): BelongsTo
    {
        return $this->belongsTo(HelpArticle::class, 'clicked_article_id');
    }

    /**
     * Scope: Searches with no results.
     */
    public function scopeNoResults($query)
    {
        return $query->where('results_count', 0);
    }

    /**
     * Scope: Searches with results.
     */
    public function scopeWithResults($query)
    {
        return $query->where('results_count', '>', 0);
    }

    /**
     * Scope: Filter by tenant.
     */
    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope: Filter by panel.
     */
    public function scopeForPanel($query, string $panel)
    {
        return $query->where('panel', $panel);
    }

    /**
     * Get the most common search queries.
     */
    public static function getTopQueries(int $limit = 20, ?int $tenantId = null): array
    {
        $query = static::query()
            ->selectRaw('LOWER(query) as normalized_query, COUNT(*) as search_count, AVG(results_count) as avg_results')
            ->groupBy('normalized_query')
            ->orderByDesc('search_count')
            ->limit($limit);

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        return $query->get()->toArray();
    }

    /**
     * Get queries with no results (content gap analysis).
     */
    public static function getQueriesWithNoResults(int $limit = 20, ?int $tenantId = null): array
    {
        $query = static::query()
            ->selectRaw('LOWER(query) as normalized_query, COUNT(*) as search_count')
            ->where('results_count', 0)
            ->groupBy('normalized_query')
            ->orderByDesc('search_count')
            ->limit($limit);

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        return $query->get()->toArray();
    }
}
