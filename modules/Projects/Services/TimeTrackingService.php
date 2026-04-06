<?php

namespace Modules\Projects\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Projects\Models\Project;
use Modules\Projects\Models\ProjectTask;
use Modules\Projects\Models\ProjectTimeEntry;
use Modules\Projects\Models\TimesheetSubmission;
use Modules\Projects\Enums\TimesheetStatus;
use Modules\Projects\Events\TimeEntryLogged;
use Modules\Auth\Models\User;

class TimeTrackingService
{
    /**
     * Start a timer for a project/task.
     */
    public function startTimer(
        int $projectId,
        ?int $taskId = null,
        ?int $userId = null,
        bool $isBillable = false,
        ?int $hourlyRateMinor = null,
        ?array $description = null
    ): ProjectTimeEntry {
        $userId = $userId ?? auth()->id();

        // Stop any running timer for this user
        $this->stopActiveTimer($userId);

        // Get default hourly rate from project settings if not provided
        if ($hourlyRateMinor === null) {
            $hourlyRateMinor = config('projects.default_billable_rate_minor', 0);
        }

        return ProjectTimeEntry::create([
            'tenant_id' => Project::find($projectId)->tenant_id,
            'project_id' => $projectId,
            'task_id' => $taskId,
            'user_id' => $userId,
            'date' => now()->toDateString(),
            'hours' => 0,
            'description' => $description,
            'is_billable' => $isBillable,
            'hourly_rate_minor' => $hourlyRateMinor,
            'timer_started_at' => now(),
            'timer_running' => true,
            'timer_accumulated_seconds' => 0,
        ]);
    }

    /**
     * Stop the active timer for a user.
     */
    public function stopActiveTimer(?int $userId = null): ?ProjectTimeEntry
    {
        $userId = $userId ?? auth()->id();

        $activeEntry = $this->getActiveTimer($userId);

        if (!$activeEntry) {
            return null;
        }

        // Calculate total seconds
        $totalSeconds = $activeEntry->timer_accumulated_seconds ?? 0;

        // Add current session if still running
        if ($activeEntry->timer_running && $activeEntry->timer_started_at) {
            $totalSeconds += now()->diffInSeconds($activeEntry->timer_started_at);
        }

        // Convert to hours (rounded to nearest quarter hour)
        $hours = $this->secondsToHours($totalSeconds);

        $activeEntry->update([
            'hours' => $activeEntry->hours + $hours,
            'timer_started_at' => null,
            'timer_running' => false,
            'timer_accumulated_seconds' => 0,
        ]);

        // Fire event
        event(new TimeEntryLogged($activeEntry));

        return $activeEntry;
    }

    /**
     * Pause the active timer for a user.
     */
    public function pauseActiveTimer(?int $userId = null): ?ProjectTimeEntry
    {
        $userId = $userId ?? auth()->id();

        $activeEntry = $this->getActiveTimer($userId);

        if (!$activeEntry || !$activeEntry->timer_running) {
            return null;
        }

        // Calculate elapsed seconds since timer_start and add to accumulated
        $elapsedSeconds = $activeEntry->timer_started_at
            ? now()->diffInSeconds($activeEntry->timer_started_at)
            : 0;

        $activeEntry->update([
            'timer_running' => false,
            'timer_started_at' => null,
            'timer_accumulated_seconds' => ($activeEntry->timer_accumulated_seconds ?? 0) + $elapsedSeconds,
        ]);

        return $activeEntry->fresh();
    }

    /**
     * Resume a paused timer for a user.
     */
    public function resumeTimer(ProjectTimeEntry $entry): ProjectTimeEntry
    {
        if ($entry->timer_running) {
            return $entry;
        }

        $entry->update([
            'timer_running' => true,
            'timer_started_at' => now(),
        ]);

        return $entry->fresh();
    }

    /**
     * Get the active timer for a user.
     */
    public function getActiveTimer(?int $userId = null): ?ProjectTimeEntry
    {
        $userId = $userId ?? auth()->id();

        return ProjectTimeEntry::where('user_id', $userId)
            ->where(function ($q) {
                $q->where('timer_running', true)
                    ->orWhere('timer_accumulated_seconds', '>', 0);
            })
            ->first();
    }

    /**
     * Get the running timer for a user (must be actively ticking).
     */
    public function getRunningTimer(?int $userId = null): ?ProjectTimeEntry
    {
        $userId = $userId ?? auth()->id();

        return ProjectTimeEntry::where('user_id', $userId)
            ->where('timer_running', true)
            ->first();
    }

    /**
     * Get paused timers for a user.
     */
    public function getPausedTimers(?int $userId = null): \Illuminate\Database\Eloquent\Collection
    {
        $userId = $userId ?? auth()->id();

        return ProjectTimeEntry::where('user_id', $userId)
            ->where('timer_running', false)
            ->where('timer_accumulated_seconds', '>', 0)
            ->get();
    }

    /**
     * Check if user has an active timer.
     */
    public function hasActiveTimer(?int $userId = null): bool
    {
        return $this->getActiveTimer($userId) !== null;
    }

    /**
     * Get the elapsed seconds for a timer (including current session).
     */
    public function getElapsedSeconds(ProjectTimeEntry $entry): int
    {
        $total = $entry->timer_accumulated_seconds ?? 0;

        if ($entry->timer_running && $entry->timer_started_at) {
            $total += now()->diffInSeconds($entry->timer_started_at);
        }

        return $total;
    }

    /**
     * Format elapsed seconds as HH:MM:SS.
     */
    public function formatElapsedTime(int $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
    }

    /**
     * Convert seconds to hours (rounded to nearest quarter hour).
     */
    public function secondsToHours(int $seconds, bool $round = true): float
    {
        $hours = $seconds / 3600;

        if ($round) {
            // Round to nearest 0.25 (quarter hour)
            return round($hours * 4) / 4;
        }

        return round($hours, 2);
    }

    /**
     * Get all running timers (for admin dashboard).
     */
    public function getAllRunningTimers(): \Illuminate\Database\Eloquent\Collection
    {
        return ProjectTimeEntry::with(['user', 'project', 'task'])
            ->where('timer_running', true)
            ->get();
    }

    /**
     * Stop all timers for a user.
     */
    public function stopAllTimers(int $userId): int
    {
        $timers = ProjectTimeEntry::where('user_id', $userId)
            ->where(function ($q) {
                $q->where('timer_running', true)
                    ->orWhere('timer_accumulated_seconds', '>', 0);
            })
            ->get();

        foreach ($timers as $timer) {
            $timer->stopTimer();
            event(new TimeEntryLogged($timer));
        }

        return $timers->count();
    }

    /**
     * Log time manually.
     */
    public function logTime(
        int $projectId,
        float $hours,
        ?int $taskId = null,
        ?int $userId = null,
        ?string $date = null,
        ?array $description = null,
        bool $isBillable = false,
        ?int $hourlyRateMinor = null
    ): ProjectTimeEntry {
        $userId = $userId ?? auth()->id();
        $date = $date ?? now()->toDateString();

        if ($hourlyRateMinor === null) {
            $hourlyRateMinor = config('projects.default_billable_rate_minor', 0);
        }

        $entry = ProjectTimeEntry::create([
            'tenant_id' => Project::find($projectId)->tenant_id,
            'project_id' => $projectId,
            'task_id' => $taskId,
            'user_id' => $userId,
            'date' => $date,
            'hours' => $hours,
            'description' => $description,
            'is_billable' => $isBillable,
            'hourly_rate_minor' => $hourlyRateMinor,
        ]);

        // Fire event
        event(new TimeEntryLogged($entry));

        return $entry;
    }

    /**
     * Update an existing time entry.
     */
    public function updateTimeEntry(ProjectTimeEntry $entry, array $data): ProjectTimeEntry
    {
        $entry->update($data);
        return $entry;
    }

    /**
     * Delete a time entry.
     */
    public function deleteTimeEntry(ProjectTimeEntry $entry): bool
    {
        return $entry->delete();
    }

    /**
     * Get time summary for a project.
     */
    public function getProjectTimeSummary(Project $project, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $query = $project->timeEntries();

        if ($startDate) {
            $query->where('date', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('date', '<=', $endDate);
        }

        $entries = $query->get();

        return [
            'total_hours' => $entries->sum('hours'),
            'billable_hours' => $entries->where('is_billable', true)->sum('hours'),
            'non_billable_hours' => $entries->where('is_billable', false)->sum('hours'),
            'total_amount_minor' => $entries->where('is_billable', true)
                ->sum(fn ($e) => (int) round($e->hours * $e->hourly_rate_minor)),
            'entries_count' => $entries->count(),
        ];
    }

    /**
     * Get time summary for a user.
     */
    public function getUserTimeSummary(int $userId, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $query = ProjectTimeEntry::where('user_id', $userId);

        if ($startDate) {
            $query->where('date', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('date', '<=', $endDate);
        }

        $entries = $query->get();

        return [
            'total_hours' => $entries->sum('hours'),
            'billable_hours' => $entries->where('is_billable', true)->sum('hours'),
            'projects_count' => $entries->pluck('project_id')->unique()->count(),
            'tasks_count' => $entries->pluck('task_id')->filter()->unique()->count(),
        ];
    }

    /**
     * Get timesheet data for a user in a date range.
     */
    public function getTimesheet(int $userId, Carbon $startDate, Carbon $endDate): array
    {
        $entries = ProjectTimeEntry::where('user_id', $userId)
            ->whereBetween('date', [$startDate, $endDate])
            ->with(['project', 'task'])
            ->orderBy('date')
            ->get();

        $timesheet = [];
        $current = $startDate->copy();

        while ($current->lte($endDate)) {
            $dateKey = $current->format('Y-m-d');
            $dayEntries = $entries->where('date', $current->toDateString());

            $timesheet[$dateKey] = [
                'date' => $dateKey,
                'day_name' => $current->format('l'),
                'total_hours' => $dayEntries->sum('hours'),
                'entries' => $dayEntries->map(fn ($e) => [
                    'id' => $e->id,
                    'project_id' => $e->project_id,
                    'project_name' => $e->project->display_name,
                    'task_id' => $e->task_id,
                    'task_name' => $e->task?->display_name,
                    'hours' => $e->hours,
                    'description' => $e->display_description,
                    'is_billable' => $e->is_billable,
                    'is_timer_running' => $e->is_timer_running,
                ])->values()->toArray(),
            ];

            $current->addDay();
        }

        return $timesheet;
    }

    /**
     * Get weekly timesheet summary.
     */
    public function getWeeklySummary(int $userId, ?Carbon $weekStart = null): array
    {
        $weekStart = $weekStart ?? now()->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();

        $entries = ProjectTimeEntry::where('user_id', $userId)
            ->whereBetween('date', [$weekStart, $weekEnd])
            ->get();

        $dailyHours = [];
        $current = $weekStart->copy();

        while ($current->lte($weekEnd)) {
            $dayEntries = $entries->where('date', $current->toDateString());
            $dailyHours[$current->format('D')] = $dayEntries->sum('hours');
            $current->addDay();
        }

        return [
            'week_start' => $weekStart->format('Y-m-d'),
            'week_end' => $weekEnd->format('Y-m-d'),
            'total_hours' => $entries->sum('hours'),
            'billable_hours' => $entries->where('is_billable', true)->sum('hours'),
            'daily_hours' => $dailyHours,
            'projects' => $entries->groupBy('project_id')
                ->map(fn ($group, $projectId) => [
                    'project_id' => $projectId,
                    'project_name' => $group->first()->project->display_name,
                    'hours' => $group->sum('hours'),
                ])->values()->toArray(),
        ];
    }

    /**
     * Get time entries report by project.
     */
    public function getProjectReport(Project $project, Carbon $startDate, Carbon $endDate): array
    {
        $entries = $project->timeEntries()
            ->whereBetween('date', [$startDate, $endDate])
            ->with(['user', 'task'])
            ->get();

        return [
            'project_id' => $project->id,
            'project_name' => $project->display_name,
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
            ],
            'summary' => [
                'total_hours' => $entries->sum('hours'),
                'billable_hours' => $entries->where('is_billable', true)->sum('hours'),
                'non_billable_hours' => $entries->where('is_billable', false)->sum('hours'),
                'total_amount_minor' => $entries->where('is_billable', true)
                    ->sum(fn ($e) => (int) round($e->hours * $e->hourly_rate_minor)),
            ],
            'by_user' => $entries->groupBy('user_id')
                ->map(fn ($group, $userId) => [
                    'user_id' => $userId,
                    'user_name' => $group->first()->user->name,
                    'hours' => $group->sum('hours'),
                    'billable_hours' => $group->where('is_billable', true)->sum('hours'),
                ])->values()->toArray(),
            'by_task' => $entries->groupBy('task_id')
                ->map(fn ($group, $taskId) => [
                    'task_id' => $taskId,
                    'task_name' => $group->first()->task?->display_name ?? 'No Task',
                    'hours' => $group->sum('hours'),
                ])->values()->toArray(),
        ];
    }

    // Timesheet Submission Methods

    /**
     * Get or create a timesheet submission for a specific week.
     */
    public function getOrCreateSubmission(int $userId, Carbon $weekStart, ?int $tenantId = null): TimesheetSubmission
    {
        return TimesheetSubmission::getOrCreateForWeek($userId, $weekStart, $tenantId);
    }

    /**
     * Submit a timesheet for approval.
     */
    public function submitTimesheet(TimesheetSubmission $submission): bool
    {
        if (!$submission->status->canSubmit()) {
            return false;
        }

        // Link time entries to the submission
        $this->linkEntriesToSubmission($submission);

        return $submission->submit();
    }

    /**
     * Approve a timesheet submission.
     */
    public function approveTimesheet(TimesheetSubmission $submission, int $approverId): bool
    {
        return $submission->approve($approverId);
    }

    /**
     * Reject a timesheet submission.
     */
    public function rejectTimesheet(TimesheetSubmission $submission, int $approverId, string $reason): bool
    {
        return $submission->reject($approverId, $reason);
    }

    /**
     * Link time entries in the week to the submission.
     */
    protected function linkEntriesToSubmission(TimesheetSubmission $submission): void
    {
        ProjectTimeEntry::where('user_id', $submission->user_id)
            ->whereBetween('date', [$submission->week_start, $submission->week_end])
            ->whereNull('submission_id')
            ->update(['submission_id' => $submission->id]);
    }

    /**
     * Get pending timesheet submissions for approval.
     */
    public function getPendingSubmissions(): \Illuminate\Database\Eloquent\Collection
    {
        return TimesheetSubmission::with(['user'])
            ->where('status', TimesheetStatus::SUBMITTED)
            ->orderBy('submitted_at', 'asc')
            ->get();
    }

    /**
     * Get submissions for a specific user.
     */
    public function getUserSubmissions(int $userId, ?int $limit = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = TimesheetSubmission::where('user_id', $userId)
            ->orderBy('week_start', 'desc');

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Check if a week's timesheet can be edited.
     */
    public function canEditWeek(int $userId, Carbon $weekStart): bool
    {
        $submission = TimesheetSubmission::where('user_id', $userId)
            ->where('week_start', $weekStart->startOfWeek())
            ->first();

        if (!$submission) {
            return true;
        }

        return $submission->canEdit();
    }

    /**
     * Get submission statistics for a manager.
     */
    public function getSubmissionStats(): array
    {
        $pending = TimesheetSubmission::where('status', TimesheetStatus::SUBMITTED)->count();
        $approvedThisWeek = TimesheetSubmission::where('status', TimesheetStatus::APPROVED)
            ->whereBetween('approved_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->count();
        $rejectedThisWeek = TimesheetSubmission::where('status', TimesheetStatus::REJECTED)
            ->whereBetween('approved_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->count();

        return [
            'pending_count' => $pending,
            'approved_this_week' => $approvedThisWeek,
            'rejected_this_week' => $rejectedThisWeek,
        ];
    }
}
