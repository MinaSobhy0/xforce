<?php

namespace Modules\Projects\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Projects\Models\Project;
use Modules\Projects\Models\ProjectTask;
use Modules\Projects\Models\ProjectTimeEntry;
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
        ?int $hourlyRateMinor = null
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
            'is_billable' => $isBillable,
            'hourly_rate_minor' => $hourlyRateMinor,
            'timer_started_at' => now(),
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

        $durationMinutes = now()->diffInMinutes($activeEntry->timer_started_at);
        $hours = round($durationMinutes / 60, 2);

        $activeEntry->update([
            'hours' => $hours,
            'timer_started_at' => null,
        ]);

        // Fire event
        event(new TimeEntryLogged($activeEntry));

        return $activeEntry;
    }

    /**
     * Get the active timer for a user.
     */
    public function getActiveTimer(?int $userId = null): ?ProjectTimeEntry
    {
        $userId = $userId ?? auth()->id();

        return ProjectTimeEntry::where('user_id', $userId)
            ->whereNotNull('timer_started_at')
            ->first();
    }

    /**
     * Check if user has an active timer.
     */
    public function hasActiveTimer(?int $userId = null): bool
    {
        return $this->getActiveTimer($userId) !== null;
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
}
