<?php

namespace Modules\Projects\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectTaskDependency extends Model
{
    protected $table = 'project_task_dependencies';

    protected $fillable = [
        'task_id',
        'depends_on_task_id',
        'dependency_type',
        'lag_days',
    ];

    protected $casts = [
        'lag_days' => 'integer',
    ];

    // Dependency type constants
    public const TYPE_FINISH_TO_START = 'finish_to_start';
    public const TYPE_START_TO_START = 'start_to_start';
    public const TYPE_FINISH_TO_FINISH = 'finish_to_finish';
    public const TYPE_START_TO_FINISH = 'start_to_finish';

    public const DEPENDENCY_TYPES = [
        self::TYPE_FINISH_TO_START => 'Finish to Start (FS)',
        self::TYPE_START_TO_START => 'Start to Start (SS)',
        self::TYPE_FINISH_TO_FINISH => 'Finish to Finish (FF)',
        self::TYPE_START_TO_FINISH => 'Start to Finish (SF)',
    ];

    // Relationships
    public function task(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class, 'task_id');
    }

    public function dependsOnTask(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class, 'depends_on_task_id');
    }

    // Computed attributes
    public function getDependencyTypeLabelAttribute(): string
    {
        return self::DEPENDENCY_TYPES[$this->dependency_type] ?? $this->dependency_type;
    }

    // Check if dependency is satisfied
    public function isSatisfied(): bool
    {
        $dependsOnTask = $this->dependsOnTask;

        switch ($this->dependency_type) {
            case self::TYPE_FINISH_TO_START:
                // Task can start after dependency finishes
                return $dependsOnTask->is_completed;

            case self::TYPE_START_TO_START:
                // Task can start after dependency starts
                return $dependsOnTask->planned_start_date !== null;

            case self::TYPE_FINISH_TO_FINISH:
                // Task can finish after dependency finishes
                return $dependsOnTask->is_completed;

            case self::TYPE_START_TO_FINISH:
                // Task can finish after dependency starts
                return $dependsOnTask->planned_start_date !== null;

            default:
                return true;
        }
    }

    // Get the earliest start date based on dependency
    public function getEarliestStartDate(): ?\Carbon\Carbon
    {
        $dependsOnTask = $this->dependsOnTask;

        switch ($this->dependency_type) {
            case self::TYPE_FINISH_TO_START:
                $date = $dependsOnTask->completed_date ?? $dependsOnTask->planned_end_date;
                break;

            case self::TYPE_START_TO_START:
                $date = $dependsOnTask->planned_start_date;
                break;

            default:
                return null;
        }

        if ($date && $this->lag_days > 0) {
            return $date->addDays($this->lag_days);
        }

        return $date;
    }

    // Scopes
    public function scopeByType($query, string $type)
    {
        return $query->where('dependency_type', $type);
    }

    public function scopeUnsatisfied($query)
    {
        return $query->whereHas('dependsOnTask', function ($q) {
            $q->whereNull('completed_date');
        });
    }
}
