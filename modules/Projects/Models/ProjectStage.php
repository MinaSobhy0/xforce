<?php

namespace Modules\Projects\Models;

use XLinic\Framework\Core\Model\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectStage extends BaseModel
{
    protected $table = 'project_stages';

    // Disable auto branch assignment for stages
    protected bool $autoSetBranchId = false;

    protected $fillable = [
        'tenant_id',
        'project_id',
        'name',
        'status_type',
        'sort_order',
        'color',
        'fold_by_default',
        'is_final',
    ];

    protected $casts = [
        'name' => 'array',
        'sort_order' => 'integer',
        'fold_by_default' => 'boolean',
        'is_final' => 'boolean',
    ];

    // Status type constants
    public const TYPE_TODO = 'todo';
    public const TYPE_IN_PROGRESS = 'in_progress';
    public const TYPE_REVIEW = 'review';
    public const TYPE_DONE = 'done';

    public const STATUS_TYPES = [
        self::TYPE_TODO => 'To Do',
        self::TYPE_IN_PROGRESS => 'In Progress',
        self::TYPE_REVIEW => 'Review',
        self::TYPE_DONE => 'Done',
    ];

    // Relationships
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class, 'stage_id')->orderBy('sort_order');
    }

    // Computed attributes
    public function getDisplayNameAttribute(): string
    {
        $locale = app()->getLocale();
        return $this->name[$locale] ?? $this->name['en'] ?? '';
    }

    public function getTaskCountAttribute(): int
    {
        return $this->tasks()->count();
    }

    public function getStatusTypeLabelAttribute(): string
    {
        return self::STATUS_TYPES[$this->status_type] ?? $this->status_type;
    }

    // Scopes
    public function scopeFinal($query)
    {
        return $query->where('is_final', true);
    }

    public function scopeNotFinal($query)
    {
        return $query->where('is_final', false);
    }

    public function scopeByStatusType($query, string $type)
    {
        return $query->where('status_type', $type);
    }
}
