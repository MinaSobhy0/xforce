<?php

namespace Modules\Projects\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasActivity;
use XLinic\Framework\Core\Model\Traits\HasSequence;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Auth\Models\User;

class ProjectTask extends BaseModel
{
    use HasActivity, HasSequence, SoftDeletes;

    protected $table = 'project_tasks';

    // Disable auto branch assignment for tasks
    protected bool $autoSetBranchId = false;

    // Configure sequence
    protected string $sequenceCode = 'project_task';
    protected string $sequenceColumn = 'code';

    protected $fillable = [
        'tenant_id',
        'code',
        'project_id',
        'stage_id',
        'parent_task_id',
        'milestone_id',
        'name',
        'description',
        'assigned_to_id',
        'created_by_id',
        'priority',
        'planned_start_date',
        'planned_end_date',
        'deadline',
        'completed_date',
        'estimated_hours',
        'actual_hours',
        'progress_percent',
        'sort_order',
        'custom_fields',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'custom_fields' => 'array',
        'planned_start_date' => 'date',
        'planned_end_date' => 'date',
        'deadline' => 'date',
        'completed_date' => 'date',
        'estimated_hours' => 'integer',
        'actual_hours' => 'integer',
        'progress_percent' => 'integer',
        'sort_order' => 'integer',
    ];

    // Priority constants
    public const PRIORITY_LOW = 'low';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_URGENT = 'urgent';

    public const PRIORITIES = [
        self::PRIORITY_LOW => 'Low',
        self::PRIORITY_MEDIUM => 'Medium',
        self::PRIORITY_HIGH => 'High',
        self::PRIORITY_URGENT => 'Urgent',
    ];

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (ProjectTask $task) {
            if (empty($task->priority)) {
                $task->priority = self::PRIORITY_MEDIUM;
            }
            if (empty($task->created_by_id)) {
                $task->created_by_id = auth()->id();
            }
        });

        static::updated(function (ProjectTask $task) {
            // When task moves to a final stage, set completed_date
            if ($task->isDirty('stage_id') && $task->stage && $task->stage->is_final) {
                if (!$task->completed_date) {
                    $task->completed_date = now();
                    $task->progress_percent = 100;
                    $task->saveQuietly();
                }
            }
        });
    }

    // Relationships
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(ProjectStage::class, 'stage_id');
    }

    public function parentTask(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class, 'parent_task_id');
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class, 'parent_task_id');
    }

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(ProjectMilestone::class, 'milestone_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(ProjectTag::class, 'project_task_tag', 'task_id', 'tag_id')
            ->withTimestamps();
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_task_assignees', 'task_id', 'user_id')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_task_followers', 'task_id', 'user_id')
            ->withTimestamps();
    }

    public function dependencies(): HasMany
    {
        return $this->hasMany(ProjectTaskDependency::class, 'task_id');
    }

    public function dependents(): HasMany
    {
        return $this->hasMany(ProjectTaskDependency::class, 'depends_on_task_id');
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(ProjectTimeEntry::class, 'task_id');
    }

    // Computed attributes
    public function getDisplayNameAttribute(): string
    {
        $locale = app()->getLocale();
        return $this->name[$locale] ?? $this->name['en'] ?? '';
    }

    public function getPriorityLabelAttribute(): string
    {
        return self::PRIORITIES[$this->priority] ?? $this->priority;
    }

    public function getIsCompletedAttribute(): bool
    {
        return $this->completed_date !== null || ($this->stage && $this->stage->is_final);
    }

    public function getIsOverdueAttribute(): bool
    {
        if ($this->is_completed) {
            return false;
        }
        return $this->deadline && $this->deadline->isPast();
    }

    public function getSubtaskCountAttribute(): int
    {
        return $this->subtasks()->count();
    }

    public function getCompletedSubtaskCountAttribute(): int
    {
        return $this->subtasks()
            ->whereHas('stage', fn ($q) => $q->where('is_final', true))
            ->count();
    }

    public function getLoggedHoursAttribute(): float
    {
        return $this->timeEntries()->sum('hours') ?? 0;
    }

    // Check if task can start (dependencies are met)
    public function canStart(): bool
    {
        foreach ($this->dependencies as $dependency) {
            $dependsOnTask = $dependency->dependsOnTask;

            switch ($dependency->dependency_type) {
                case 'finish_to_start':
                    if (!$dependsOnTask->is_completed) {
                        return false;
                    }
                    break;
                case 'start_to_start':
                    if (!$dependsOnTask->planned_start_date || $dependsOnTask->planned_start_date->isFuture()) {
                        return false;
                    }
                    break;
                case 'finish_to_finish':
                case 'start_to_finish':
                    // These are less restrictive
                    break;
            }
        }
        return true;
    }

    // Get blocking tasks
    public function getBlockingTasks()
    {
        $blocking = collect();

        foreach ($this->dependencies as $dependency) {
            $dependsOnTask = $dependency->dependsOnTask;

            if ($dependency->dependency_type === 'finish_to_start' && !$dependsOnTask->is_completed) {
                $blocking->push($dependsOnTask);
            }
        }

        return $blocking;
    }

    // Move to stage
    public function moveToStage(int $stageId, int $sortOrder = null): bool
    {
        $this->stage_id = $stageId;

        if ($sortOrder !== null) {
            $this->sort_order = $sortOrder;
        }

        return $this->save();
    }

    // Update progress based on subtasks
    public function updateProgressFromSubtasks(): void
    {
        $subtaskCount = $this->subtask_count;

        if ($subtaskCount === 0) {
            return;
        }

        $completedCount = $this->completed_subtask_count;
        $this->progress_percent = (int) round(($completedCount / $subtaskCount) * 100);
        $this->save();
    }

    // Scopes
    public function scopeCompleted($query)
    {
        return $query->whereNotNull('completed_date');
    }

    public function scopeIncomplete($query)
    {
        return $query->whereNull('completed_date');
    }

    public function scopeOverdue($query)
    {
        return $query->whereNull('completed_date')
            ->whereNotNull('deadline')
            ->where('deadline', '<', now());
    }

    public function scopeAssignedTo($query, int $userId)
    {
        return $query->where('assigned_to_id', $userId);
    }

    public function scopeByPriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeRootTasks($query)
    {
        return $query->whereNull('parent_task_id');
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('code', 'ilike', "%{$term}%")
                ->orWhereRaw("name->>'en' ILIKE ?", ["%{$term}%"])
                ->orWhereRaw("name->>'ar' ILIKE ?", ["%{$term}%"]);
        });
    }
}
