<?php

namespace XLinic\Framework\Core\Model\Traits;

use Spatie\Tags\HasTags as SpatieHasTags;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

trait HasTags
{
    use SpatieHasTags;

    /**
     * Get tags for current tenant only.
     */
    public function getTenantTags(): Collection
    {
        return $this->tags()->currentTenant()->get();
    }

    /**
     * Sync tags for current model with tenant scoping.
     */
    public function syncTagsWithTenant(array $tags, ?string $type = null): self
    {
        $tenantManager = app(\XLinic\Framework\Core\Tenancy\TenantManager::class);
        $tenantId = $tenantManager->getCurrentTenantId();

        // Ensure all tags belong to current tenant
        $tagModels = collect($tags)->map(function ($tag) use ($type, $tenantId) {
            if (is_string($tag)) {
                return \Spatie\Tags\Tag::findOrCreate($tag, $type)
                    ->tap(function ($tagModel) use ($tenantId) {
                        if ($tenantId && !$tagModel->tenant_id) {
                            $tagModel->update(['tenant_id' => $tenantId]);
                        }
                    });
            }

            return $tag;
        });

        return $this->syncTags($tagModels);
    }

    /**
     * Attach tags with tenant scoping.
     */
    public function attachTagsWithTenant($tags, ?string $type = null): self
    {
        $tenantManager = app(\XLinic\Framework\Core\Tenancy\TenantManager::class);
        $tenantId = $tenantManager->getCurrentTenantId();

        $tagModels = collect($tags)->map(function ($tag) use ($type, $tenantId) {
            if (is_string($tag)) {
                return \Spatie\Tags\Tag::findOrCreate($tag, $type)
                    ->tap(function ($tagModel) use ($tenantId) {
                        if ($tenantId && !$tagModel->tenant_id) {
                            $tagModel->update(['tenant_id' => $tenantId]);
                        }
                    });
            }

            return $tag;
        });

        return $this->attachTags($tagModels);
    }

    /**
     * Get tag names for display.
     */
    public function getTagNames(?string $type = null): array
    {
        return $this->tags()
            ->when($type, fn($query) => $query->where('type', $type))
            ->pluck('name->'.app()->getLocale())
            ->filter()
            ->toArray();
    }

    /**
     * Get tag names as comma-separated string.
     */
    public function getTagNamesString(?string $type = null, string $separator = ', '): string
    {
        return implode($separator, $this->getTagNames($type));
    }

    /**
     * Check if model has specific tag.
     */
    public function hasTagWithName(string $name, ?string $type = null): bool
    {
        return $this->tags()
            ->where('name->'.app()->getLocale(), $name)
            ->when($type, fn($query) => $query->where('type', $type))
            ->exists();
    }

    /**
     * Get tags grouped by type.
     */
    public function getTagsByType(): Collection
    {
        return $this->tags->groupBy('type');
    }

    /**
     * Scope to models with any of the given tags.
     */
    public function scopeWithAnyTagsOfType(Builder $query, array $tags, ?string $type = null): Builder
    {
        return $query->withAnyTags($tags, $type);
    }

    /**
     * Scope to models with all of the given tags.
     */
    public function scopeWithAllTagsOfType(Builder $query, array $tags, ?string $type = null): Builder
    {
        return $query->withAllTags($tags, $type);
    }

    /**
     * Get popular tags for this model type.
     */
    public static function getPopularTags(int $limit = 10, ?string $type = null): Collection
    {
        $modelClass = static::class;

        return \Spatie\Tags\Tag::query()
            ->when($type, fn($query) => $query->where('type', $type))
            ->whereHas('taggables', function ($query) use ($modelClass) {
                $query->where('taggable_type', $modelClass);
            })
            ->withCount(['taggables' => function ($query) use ($modelClass) {
                $query->where('taggable_type', $modelClass);
            }])
            ->orderByDesc('taggables_count')
            ->limit($limit)
            ->get();
    }

    /**
     * Get suggested tags based on content.
     */
    public function getSuggestedTags(?string $type = null, int $limit = 5): Collection
    {
        // This is a placeholder - in a real implementation, you might use
        // machine learning or content analysis to suggest relevant tags
        return static::getPopularTags($limit, $type);
    }

    /**
     * Get tag cloud data for visualization.
     */
    public static function getTagCloud(?string $type = null): Collection
    {
        $modelClass = static::class;

        return \Spatie\Tags\Tag::query()
            ->when($type, fn($query) => $query->where('type', $type))
            ->whereHas('taggables', function ($query) use ($modelClass) {
                $query->where('taggable_type', $modelClass);
            })
            ->withCount(['taggables' => function ($query) use ($modelClass) {
                $query->where('taggable_type', $modelClass);
            }])
            ->get()
            ->map(function ($tag) {
                return [
                    'name' => $tag->name,
                    'count' => $tag->taggables_count,
                    'weight' => $this->calculateTagWeight($tag->taggables_count),
                ];
            });
    }

    /**
     * Calculate tag weight for visualization.
     */
    protected static function calculateTagWeight(int $count): int
    {
        // Simple weight calculation - can be made more sophisticated
        if ($count >= 20) return 5;
        if ($count >= 15) return 4;
        if ($count >= 10) return 3;
        if ($count >= 5) return 2;
        return 1;
    }

    /**
     * Auto-tag based on content analysis.
     */
    public function autoTag(array $fields = ['name', 'description'], ?string $type = null): self
    {
        $content = collect($fields)
            ->map(fn($field) => $this->getAttribute($field))
            ->filter()
            ->implode(' ');

        if (empty($content)) {
            return $this;
        }

        // Extract potential tags from content
        $suggestedTags = $this->extractTagsFromContent($content);

        if (!empty($suggestedTags)) {
            $this->attachTagsWithTenant($suggestedTags, $type);
        }

        return $this;
    }

    /**
     * Extract tags from content (basic implementation).
     */
    protected function extractTagsFromContent(string $content): array
    {
        // This is a basic implementation - you might want to use
        // more sophisticated NLP techniques
        $words = str_word_count(strtolower($content), 1);
        $commonWords = ['the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'from', 'up', 'about', 'into', 'through', 'during', 'before', 'after', 'above', 'below', 'between', 'among', 'is', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'should', 'could', 'can', 'may', 'might', 'must', 'shall', 'a', 'an'];

        return array_diff(
            array_unique(
                array_filter($words, fn($word) => strlen($word) > 3)
            ),
            $commonWords
        );
    }
}