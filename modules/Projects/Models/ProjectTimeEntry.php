<?php

namespace Modules\Projects\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasActivity;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Auth\Models\User;

class ProjectTimeEntry extends BaseModel
{
    use HasActivity;

    protected $table = 'project_time_entries';

    // Disable auto branch assignment for time entries
    protected bool $autoSetBranchId = false;

    protected $fillable = [
        'tenant_id',
        'project_id',
        'task_id',
        'user_id',
        'date',
        'hours',
        'description',
        'is_billable',
        'hourly_rate_minor',
        'timer_started_at',
        'odoo_id',
        'odoo_synced_at',
    ];

    protected $casts = [
        'description' => 'array',
        'date' => 'date',
        'hours' => 'decimal:2',
        'is_billable' => 'boolean',
        'hourly_rate_minor' => 'integer',
        'timer_started_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (ProjectTimeEntry $entry) {
            if (empty($entry->user_id)) {
                $entry->user_id = auth()->id();
            }
            if (empty($entry->date)) {
                $entry->date = now()->toDateString();
            }
        });

        // Update task actual hours when time entry is saved
        static::saved(function (ProjectTimeEntry $entry) {
            if ($entry->task_id) {
                $entry->task->update([
                    'actual_hours' => $entry->task->timeEntries()->sum('hours'),
                ]);
            }
        });

        static::deleted(function (ProjectTimeEntry $entry) {
            if ($entry->task_id) {
                $entry->task->update([
                    'actual_hours' => $entry->task->timeEntries()->sum('hours'),
                ]);
            }
        });
    }

    // Relationships
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class, 'task_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Computed attributes
    public function getDisplayDescriptionAttribute(): string
    {
        if (empty($this->description)) {
            return '';
        }
        $locale = app()->getLocale();
        return $this->description[$locale] ?? $this->description['en'] ?? '';
    }

    public function getIsTimerRunningAttribute(): bool
    {
        return $this->timer_started_at !== null;
    }

    public function getTimerDurationAttribute(): ?int
    {
        if (!$this->is_timer_running) {
            return null;
        }
        return now()->diffInMinutes($this->timer_started_at);
    }

    public function getBillableAmountMinorAttribute(): int
    {
        if (!$this->is_billable) {
            return 0;
        }
        return (int) round($this->hours * $this->hourly_rate_minor);
    }

    // Timer methods
    public function startTimer(): bool
    {
        if ($this->is_timer_running) {
            return false;
        }

        $this->timer_started_at = now();
        $this->hours = 0;
        return $this->save();
    }

    public function stopTimer(): bool
    {
        if (!$this->is_timer_running) {
            return false;
        }

        $durationMinutes = $this->timer_duration;
        $this->hours = round($durationMinutes / 60, 2);
        $this->timer_started_at = null;
        return $this->save();
    }

    // Scopes
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForProject($query, int $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    public function scopeForTask($query, int $taskId)
    {
        return $query->where('task_id', $taskId);
    }

    public function scopeBillable($query)
    {
        return $query->where('is_billable', true);
    }

    public function scopeNonBillable($query)
    {
        return $query->where('is_billable', false);
    }

    public function scopeRunningTimers($query)
    {
        return $query->whereNotNull('timer_started_at');
    }

    public function scopeForDate($query, $date)
    {
        return $query->where('date', $date);
    }

    public function scopeForDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('date', [
            now()->startOfWeek(),
            now()->endOfWeek(),
        ]);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereBetween('date', [
            now()->startOfMonth(),
            now()->endOfMonth(),
        ]);
    }
}
