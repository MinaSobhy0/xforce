<?php

namespace Modules\KnowledgeBase\Models;

use XLinic\Framework\Core\Model\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Help User Progress model - lives in tenant schema.
 * Tracks guide completion per user within each tenant.
 */
class HelpUserProgress extends BaseModel
{
    protected $table = 'help_user_progress';

    /**
     * Disable auto-setting branch_id since this is not branch-scoped.
     */
    protected bool $autoSetBranchId = false;

    protected $fillable = [
        'user_id',
        'guide_id',
        'screen_key',
        'is_completed',
        'is_skipped',
        'current_step',
        'total_steps',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'is_completed' => 'boolean',
        'is_skipped' => 'boolean',
        'current_step' => 'integer',
        'total_steps' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the user.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\Modules\Auth\Models\User::class);
    }

    /**
     * Get the guide from central schema.
     */
    public function getGuideAttribute(): ?HelpScreenGuide
    {
        return HelpScreenGuide::find($this->guide_id);
    }

    /**
     * Scope: Completed guides only.
     */
    public function scopeCompleted($query)
    {
        return $query->where('is_completed', true);
    }

    /**
     * Scope: Skipped guides only.
     */
    public function scopeSkipped($query)
    {
        return $query->where('is_skipped', true);
    }

    /**
     * Scope: In-progress guides.
     */
    public function scopeInProgress($query)
    {
        return $query->where('is_completed', false)
                     ->where('is_skipped', false)
                     ->where('current_step', '>', 0);
    }

    /**
     * Scope: Filter by user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope: Filter by screen key.
     */
    public function scopeForScreen($query, string $screenKey)
    {
        return $query->where('screen_key', $screenKey);
    }

    /**
     * Check if user has completed a specific guide.
     */
    public static function hasCompleted(int $userId, int $guideId): bool
    {
        return static::where('user_id', $userId)
            ->where('guide_id', $guideId)
            ->where('is_completed', true)
            ->exists();
    }

    /**
     * Check if user has skipped a specific guide.
     */
    public static function hasSkipped(int $userId, int $guideId): bool
    {
        return static::where('user_id', $userId)
            ->where('guide_id', $guideId)
            ->where('is_skipped', true)
            ->exists();
    }

    /**
     * Check if user has seen a specific guide (completed or skipped).
     */
    public static function hasSeen(int $userId, int $guideId): bool
    {
        return static::where('user_id', $userId)
            ->where('guide_id', $guideId)
            ->where(function ($q) {
                $q->where('is_completed', true)
                  ->orWhere('is_skipped', true);
            })
            ->exists();
    }

    /**
     * Start or continue a guide for a user.
     */
    public static function startGuide(int $userId, int $guideId, string $screenKey, int $totalSteps): self
    {
        return static::updateOrCreate(
            [
                'user_id' => $userId,
                'guide_id' => $guideId,
            ],
            [
                'screen_key' => $screenKey,
                'total_steps' => $totalSteps,
                'started_at' => now(),
                'is_completed' => false,
                'is_skipped' => false,
            ]
        );
    }

    /**
     * Update progress for a guide.
     */
    public function updateStep(int $step): self
    {
        $this->update(['current_step' => $step]);
        return $this;
    }

    /**
     * Mark guide as completed.
     */
    public function markCompleted(): self
    {
        $this->update([
            'is_completed' => true,
            'current_step' => $this->total_steps,
            'completed_at' => now(),
        ]);
        return $this;
    }

    /**
     * Mark guide as skipped.
     */
    public function markSkipped(): self
    {
        $this->update([
            'is_skipped' => true,
            'completed_at' => now(),
        ]);
        return $this;
    }

    /**
     * Get completion percentage.
     */
    public function getProgressPercentageAttribute(): float
    {
        if ($this->total_steps === 0) {
            return 0;
        }

        return round(($this->current_step / $this->total_steps) * 100, 1);
    }
}
