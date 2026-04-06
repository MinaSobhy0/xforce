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
        'timer_accumulated_seconds',
        'timer_running',
        'submission_id',
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
        'timer_accumulated_seconds' => 'integer',
        'timer_running' => 'boolean',
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

    public function submission(): BelongsTo
    {
        return $this->belongsTo(TimesheetSubmission::class, 'submission_id');
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
        return $this->timer_running || $this->timer_started_at !== null;
    }

    /**
     * Get total elapsed seconds including accumulated and current session.
     */
    public function getElapsedSecondsAttribute(): int
    {
        $total = $this->timer_accumulated_seconds ?? 0;

        if ($this->timer_running && $this->timer_started_at) {
            $total += now()->diffInSeconds($this->timer_started_at);
        }

        return $total;
    }

    public function getTimerDurationAttribute(): ?int
    {
        if (!$this->is_timer_running && $this->timer_accumulated_seconds == 0) {
            return null;
        }
        // Return duration in minutes
        return (int) round($this->elapsed_seconds / 60);
    }

    /**
     * Get formatted elapsed time as HH:MM:SS.
     */
    public function getFormattedElapsedTimeAttribute(): string
    {
        $seconds = $this->elapsed_seconds;
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
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
        $this->timer_running = true;
        $this->hours = 0;
        return $this->save();
    }

    /**
     * Pause the running timer.
     */
    public function pauseTimer(): bool
    {
        if (!$this->timer_running) {
            return false;
        }

        // Calculate elapsed seconds since timer_start and add to accumulated
        $elapsedSeconds = $this->timer_started_at
            ? now()->diffInSeconds($this->timer_started_at)
            : 0;

        $this->timer_running = false;
        $this->timer_started_at = null;
        $this->timer_accumulated_seconds = ($this->timer_accumulated_seconds ?? 0) + $elapsedSeconds;

        return $this->save();
    }

    /**
     * Resume a paused timer.
     */
    public function resumeTimer(): bool
    {
        if ($this->timer_running) {
            return false;
        }

        $this->timer_running = true;
        $this->timer_started_at = now();

        return $this->save();
    }

    public function stopTimer(): bool
    {
        // Calculate total seconds
        $totalSeconds = $this->timer_accumulated_seconds ?? 0;

        // Add current session if still running
        if ($this->timer_running && $this->timer_started_at) {
            $totalSeconds += now()->diffInSeconds($this->timer_started_at);
        }

        // Convert to hours (rounded to nearest quarter hour)
        $hours = $this->secondsToHours($totalSeconds);

        $this->timer_running = false;
        $this->timer_started_at = null;
        $this->timer_accumulated_seconds = 0;
        $this->hours = $this->hours + $hours;

        return $this->save();
    }

    /**
     * Convert seconds to hours (rounded to nearest quarter hour).
     */
    protected function secondsToHours(int $seconds, bool $round = true): float
    {
        $hours = $seconds / 3600;

        if ($round) {
            // Round to nearest 0.25 (quarter hour)
            return round($hours * 4) / 4;
        }

        return round($hours, 2);
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
        return $query->where('timer_running', true);
    }

    public function scopePausedTimers($query)
    {
        return $query->where('timer_running', false)
            ->where('timer_accumulated_seconds', '>', 0);
    }

    public function scopeForSubmission($query, int $submissionId)
    {
        return $query->where('submission_id', $submissionId);
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
