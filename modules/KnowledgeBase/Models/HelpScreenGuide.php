<?php

namespace Modules\KnowledgeBase\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

/**
 * Help Screen Guide model - lives in public schema.
 * Defines interactive walkthrough guides for each screen.
 */
class HelpScreenGuide extends Model
{
    use SoftDeletes;
    use HasTranslations;

    protected $table = 'public.help_screen_guides';
    protected $connection = 'central';

    public array $translatable = ['title', 'description'];

    protected $fillable = [
        'screen_key',
        'panel',
        'title',
        'description',
        'is_active',
        'show_on_first_visit',
        'sort_order',
    ];

    protected $casts = [
        'title' => 'array',
        'description' => 'array',
        'is_active' => 'boolean',
        'show_on_first_visit' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Get the steps for this guide.
     */
    public function steps(): HasMany
    {
        return $this->hasMany(HelpGuideStep::class, 'guide_id')->ordered();
    }

    /**
     * Scope: Active guides only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
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
        return $query->where('screen_key', $screenKey);
    }

    /**
     * Scope: Filter by panel.
     */
    public function scopeForPanel($query, string $panel)
    {
        return $query->where('panel', $panel);
    }

    /**
     * Get the total number of steps.
     */
    public function getStepsCountAttribute(): int
    {
        return $this->steps()->count();
    }

    /**
     * Get the guide data formatted for the frontend.
     */
    public function toGuideFormat(string $locale = 'en'): array
    {
        return [
            'id' => $this->id,
            'screen_key' => $this->screen_key,
            'title' => $this->getTranslation('title', $locale),
            'description' => $this->getTranslation('description', $locale),
            'steps' => $this->steps->map(fn ($step) => $step->toStepFormat($locale))->toArray(),
            'total_steps' => $this->steps_count,
        ];
    }
}
