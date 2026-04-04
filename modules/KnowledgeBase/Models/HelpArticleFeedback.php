<?php

namespace Modules\KnowledgeBase\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Help Article Feedback model - lives in public schema.
 * Tracks helpful/not helpful votes on articles.
 */
class HelpArticleFeedback extends Model
{
    protected $table = 'public.help_article_feedback';
    protected $connection = 'central';

    protected $fillable = [
        'article_id',
        'tenant_id',
        'user_id',
        'is_helpful',
        'comment',
        'session_id',
        'ip_address',
    ];

    protected $casts = [
        'is_helpful' => 'boolean',
        'tenant_id' => 'integer',
        'user_id' => 'integer',
    ];

    /**
     * Get the article this feedback belongs to.
     */
    public function article(): BelongsTo
    {
        return $this->belongsTo(HelpArticle::class, 'article_id');
    }

    /**
     * Scope: Helpful feedback only.
     */
    public function scopeHelpful($query)
    {
        return $query->where('is_helpful', true);
    }

    /**
     * Scope: Not helpful feedback only.
     */
    public function scopeNotHelpful($query)
    {
        return $query->where('is_helpful', false);
    }

    /**
     * Scope: Filter by tenant.
     */
    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Boot method to update article counts when feedback is saved.
     */
    protected static function booted(): void
    {
        static::saved(function (HelpArticleFeedback $feedback) {
            $feedback->article->updateFeedbackCounts();
        });

        static::deleted(function (HelpArticleFeedback $feedback) {
            $feedback->article->updateFeedbackCounts();
        });
    }
}
