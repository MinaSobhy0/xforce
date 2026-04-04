<?php

namespace Modules\KnowledgeBase\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

/**
 * Help Guide Step model - lives in public schema.
 * Individual steps within a screen guide.
 */
class HelpGuideStep extends Model
{
    use HasTranslations;

    protected $table = 'public.help_guide_steps';
    protected $connection = 'central';

    public array $translatable = ['title', 'content'];

    protected $fillable = [
        'guide_id',
        'title',
        'content',
        'target_selector',
        'placement',
        'sort_order',
        'action_type',
        'action_selector',
        'options',
        'is_required',
    ];

    protected $casts = [
        'title' => 'array',
        'content' => 'array',
        'options' => 'array',
        'sort_order' => 'integer',
        'is_required' => 'boolean',
    ];

    /**
     * Valid placement options.
     */
    public const PLACEMENTS = [
        'top' => 'Top',
        'top-start' => 'Top Start',
        'top-end' => 'Top End',
        'bottom' => 'Bottom',
        'bottom-start' => 'Bottom Start',
        'bottom-end' => 'Bottom End',
        'left' => 'Left',
        'left-start' => 'Left Start',
        'left-end' => 'Left End',
        'right' => 'Right',
        'right-start' => 'Right Start',
        'right-end' => 'Right End',
        'auto' => 'Auto',
    ];

    /**
     * Valid action types.
     */
    public const ACTION_TYPES = [
        'click' => 'Click',
        'input' => 'Input',
        'hover' => 'Hover',
        'focus' => 'Focus',
        'none' => 'Info Only',
    ];

    /**
     * Get the guide this step belongs to.
     */
    public function guide(): BelongsTo
    {
        return $this->belongsTo(HelpScreenGuide::class, 'guide_id');
    }

    /**
     * Scope: Order by sort order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    /**
     * Get the step data formatted for the frontend.
     */
    public function toStepFormat(string $locale = 'en'): array
    {
        return [
            'id' => $this->id,
            'title' => $this->getTranslation('title', $locale),
            'content' => $this->getTranslation('content', $locale),
            'target' => $this->target_selector,
            'placement' => $this->placement,
            'action_type' => $this->action_type,
            'action_selector' => $this->action_selector,
            'options' => $this->options ?? [],
            'is_required' => $this->is_required,
            'order' => $this->sort_order,
        ];
    }
}
