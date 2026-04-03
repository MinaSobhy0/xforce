<?php

namespace Modules\Projects\Services;

use Illuminate\Support\Facades\DB;
use Modules\Projects\Models\Project;
use Modules\Projects\Models\ProjectTask;
use Modules\Projects\Models\ProjectStage;
use Modules\Projects\Events\ProjectCreated;
use Modules\Projects\Events\ProjectCompleted;

class ProjectService
{
    /**
     * Create a new project.
     */
    public function create(array $data): Project
    {
        return DB::transaction(function () use ($data) {
            $project = Project::create($data);

            // Fire event
            event(new ProjectCreated($project));

            return $project;
        });
    }

    /**
     * Update a project.
     */
    public function update(Project $project, array $data): Project
    {
        return DB::transaction(function () use ($project, $data) {
            $wasCompleted = $project->isCompleted();

            $project->update($data);

            // Fire event if project was just completed
            if (!$wasCompleted && $project->isCompleted()) {
                event(new ProjectCompleted($project));
            }

            return $project;
        });
    }

    /**
     * Calculate project progress.
     */
    public function calculateProgress(Project $project): int
    {
        $totalTasks = $project->tasks()->count();

        if ($totalTasks === 0) {
            return 0;
        }

        $completedTasks = $project->tasks()
            ->whereHas('stage', fn ($q) => $q->where('is_final', true))
            ->count();

        return (int) round(($completedTasks / $totalTasks) * 100);
    }

    /**
     * Calculate project actual cost.
     */
    public function calculateActualCost(Project $project): int
    {
        return $project->timeEntries()
            ->where('is_billable', true)
            ->get()
            ->sum(fn ($entry) => (int) round($entry->hours * $entry->hourly_rate_minor));
    }

    /**
     * Update project progress and actual cost.
     */
    public function recalculateStats(Project $project): void
    {
        $project->actual_cost_minor = $this->calculateActualCost($project);
        $project->save();
    }

    /**
     * Clone project from template.
     */
    public function cloneFromTemplate(Project $template, array $overrides = []): Project
    {
        return DB::transaction(function () use ($template, $overrides) {
            $data = array_merge([
                'tenant_id' => $template->tenant_id,
                'name' => $overrides['name'] ?? $template->name,
                'description' => $template->description,
                'branch_id' => $overrides['branch_id'] ?? $template->branch_id,
                'manager_id' => $overrides['manager_id'] ?? $template->manager_id,
                'priority' => $template->priority,
                'budget_minor' => $template->budget_minor,
                'color' => $template->color,
                'settings' => $template->settings,
                'allow_timesheets' => $template->allow_timesheets,
                'is_template' => false,
                'is_active' => true,
            ], $overrides);

            // Create new project (default stages will be created)
            $project = Project::create($data);

            // Remove default stages and clone from template
            $project->stages()->delete();
            $stageMap = [];

            foreach ($template->stages as $stage) {
                $newStage = $project->stages()->create([
                    'tenant_id' => $project->tenant_id,
                    'name' => $stage->name,
                    'status_type' => $stage->status_type,
                    'sort_order' => $stage->sort_order,
                    'color' => $stage->color,
                    'fold_by_default' => $stage->fold_by_default,
                    'is_final' => $stage->is_final,
                ]);
                $stageMap[$stage->id] = $newStage->id;
            }

            // Clone milestones
            $milestoneMap = [];
            foreach ($template->milestones as $milestone) {
                $newMilestone = $project->milestones()->create([
                    'tenant_id' => $project->tenant_id,
                    'name' => $milestone->name,
                    'description' => $milestone->description,
                    'sort_order' => $milestone->sort_order,
                    'status' => 'pending',
                ]);
                $milestoneMap[$milestone->id] = $newMilestone->id;
            }

            // Clone tasks (root tasks first, then subtasks)
            $taskMap = [];
            $rootTasks = $template->tasks()->whereNull('parent_task_id')->get();

            foreach ($rootTasks as $task) {
                $this->cloneTask($task, $project, $stageMap, $milestoneMap, $taskMap);
            }

            return $project;
        });
    }

    /**
     * Clone a task and its subtasks.
     */
    protected function cloneTask(
        ProjectTask $task,
        Project $project,
        array $stageMap,
        array $milestoneMap,
        array &$taskMap,
        ?int $parentTaskId = null
    ): ProjectTask {
        $newTask = $project->tasks()->create([
            'tenant_id' => $project->tenant_id,
            'stage_id' => $stageMap[$task->stage_id] ?? $project->stages()->first()->id,
            'parent_task_id' => $parentTaskId,
            'milestone_id' => isset($milestoneMap[$task->milestone_id]) ? $milestoneMap[$task->milestone_id] : null,
            'name' => $task->name,
            'description' => $task->description,
            'priority' => $task->priority,
            'estimated_hours' => $task->estimated_hours,
            'sort_order' => $task->sort_order,
            'custom_fields' => $task->custom_fields,
        ]);

        $taskMap[$task->id] = $newTask->id;

        // Clone subtasks
        foreach ($task->subtasks as $subtask) {
            $this->cloneTask($subtask, $project, $stageMap, $milestoneMap, $taskMap, $newTask->id);
        }

        // Clone tags
        $newTask->tags()->sync($task->tags->pluck('id'));

        return $newTask;
    }

    /**
     * Save project as template.
     */
    public function saveAsTemplate(Project $project, array $data = []): Project
    {
        return DB::transaction(function () use ($project, $data) {
            $template = $project->replicate(['code']);
            $template->name = $data['name'] ?? $project->name;
            $template->is_template = true;
            $template->status = Project::STATUS_PLANNING;
            $template->start_date = null;
            $template->end_date = null;
            $template->deadline = null;
            $template->actual_cost_minor = 0;
            $template->save();

            // Clone stages
            foreach ($project->stages as $stage) {
                $template->stages()->create([
                    'tenant_id' => $template->tenant_id,
                    'name' => $stage->name,
                    'status_type' => $stage->status_type,
                    'sort_order' => $stage->sort_order,
                    'color' => $stage->color,
                    'fold_by_default' => $stage->fold_by_default,
                    'is_final' => $stage->is_final,
                ]);
            }

            // Clone milestones (without dates)
            foreach ($project->milestones as $milestone) {
                $template->milestones()->create([
                    'tenant_id' => $template->tenant_id,
                    'name' => $milestone->name,
                    'description' => $milestone->description,
                    'sort_order' => $milestone->sort_order,
                    'status' => 'pending',
                ]);
            }

            return $template;
        });
    }

    /**
     * Get project statistics.
     */
    public function getStatistics(Project $project): array
    {
        $totalTasks = $project->tasks()->count();
        $completedTasks = $project->tasks()
            ->whereHas('stage', fn ($q) => $q->where('is_final', true))
            ->count();

        $overdueTasks = $project->tasks()
            ->whereNull('completed_date')
            ->whereNotNull('deadline')
            ->where('deadline', '<', now())
            ->count();

        $totalHours = $project->timeEntries()->sum('hours');
        $billableHours = $project->timeEntries()->where('is_billable', true)->sum('hours');

        return [
            'total_tasks' => $totalTasks,
            'completed_tasks' => $completedTasks,
            'pending_tasks' => $totalTasks - $completedTasks,
            'overdue_tasks' => $overdueTasks,
            'progress_percent' => $totalTasks > 0 ? (int) round(($completedTasks / $totalTasks) * 100) : 0,
            'total_hours' => $totalHours,
            'billable_hours' => $billableHours,
            'estimated_hours' => $project->tasks()->sum('estimated_hours') ?? 0,
            'actual_cost_minor' => $project->actual_cost_minor,
            'budget_minor' => $project->budget_minor,
            'budget_used_percent' => $project->budget_minor > 0
                ? (int) round(($project->actual_cost_minor / $project->budget_minor) * 100)
                : 0,
        ];
    }

    /**
     * Get tasks grouped by stage for Kanban view.
     */
    public function getKanbanData(Project $project): array
    {
        $stages = $project->stages()
            ->with(['tasks' => function ($query) {
                $query->with(['assignedTo', 'tags', 'subtasks'])
                    ->orderBy('sort_order');
            }])
            ->orderBy('sort_order')
            ->get();

        return $stages->map(function ($stage) {
            return [
                'id' => $stage->id,
                'name' => $stage->display_name,
                'color' => $stage->color,
                'status_type' => $stage->status_type,
                'fold_by_default' => $stage->fold_by_default,
                'is_final' => $stage->is_final,
                'task_count' => $stage->tasks->count(),
                'tasks' => $stage->tasks->map(function ($task) {
                    return [
                        'id' => $task->id,
                        'code' => $task->code,
                        'name' => $task->display_name,
                        'priority' => $task->priority,
                        'deadline' => $task->deadline?->format('Y-m-d'),
                        'is_overdue' => $task->is_overdue,
                        'assignee' => $task->assignedTo ? [
                            'id' => $task->assignedTo->id,
                            'name' => $task->assignedTo->name,
                            'avatar' => $task->assignedTo->avatar_url ?? null,
                        ] : null,
                        'tags' => $task->tags->map(fn ($tag) => [
                            'id' => $tag->id,
                            'name' => $tag->display_name,
                            'color' => $tag->color,
                        ]),
                        'subtask_count' => $task->subtask_count,
                        'completed_subtask_count' => $task->completed_subtask_count,
                        'progress_percent' => $task->progress_percent,
                    ];
                }),
            ];
        })->toArray();
    }
}
