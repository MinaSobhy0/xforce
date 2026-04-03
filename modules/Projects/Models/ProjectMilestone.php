<?php

namespace Modules\Projects\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasActivity;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectMilestone extends BaseModel
{
    use HasActivity;

    protected $table = 'project_milestones';

    // Disable auto branch assignment for milestones
    protected bool $autoSetBranchId = false;

    protected $fillable = [
        'tenant_id',
        'project_id',
        'name',
        'description',
        'target_date',
        'completed_date',
        'status',
        'sort_order',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'target_date' => 'date',
        'completed_date' => 'date',
        'sort_order' => 'integer',
    ];

    // Status constants
    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';

    public const STATUSES = [
        self::STATUS_PENDING => 'Pending',
        self::STATUS_COMPLETED => 'Completed',
    ];

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (ProjectMilestone $milestone) {
            if (empty($milestone->status)) {
                $milestone->status = self::STATUS_PENDING;
            }
        });
    }

    // Relationships
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class, 'milestone_id');
    }

    // Computed attributes
    public function getDisplayNameAttribute(): string
    {
        $locale = app()->getLocale();
        return $this->name[$locale] ?? $this->name['en'] ?? '';
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getIsCompletedAttribute(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function getIsOverdueAttribute(): bool
    {
        if ($this->is_completed) {
            return false;
        }
        return $this->target_date && $this->target_date->isPast();
    }

    public function getTaskCountAttribute(): int
    {
        return $this->tasks()->count();
    }

    public function getCompletedTaskCountAttribute(): int
    {
        return $this->tasks()
            ->whereHas('stage', fn ($q) => $q->where('is_final', true))
            ->count();
    }

    public function getProgressPercentAttribute(): int
    {
        $taskCount = $this->task_count;
        if ($taskCount === 0) {
            return 0;
        }

        return (int) round(($this->completed_task_count / $taskCount) * 100);
    }

    // Mark as complete
    public function markComplete(): bool
    {
        $this->status = self::STATUS_COMPLETED;
        $this->completed_date = now();
        return $this->save();
    }

    // Reopen
    public function reopen(): bool
    {
        $this->status = self::STATUS_PENDING;
        $this->completed_date = null;
        return $this->save();
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', self::STATUS_PENDING)
            ->whereNotNull('target_date')
            ->where('target_date', '<', now());
    }

    public function scopeUpcoming($query, int $days = 7)
    {
        return $query->where('status', self::STATUS_PENDING)
            ->whereNotNull('target_date')
            ->whereBetween('target_date', [now(), now()->addDays($days)]);
    }
}
