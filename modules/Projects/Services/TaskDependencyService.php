<?php

namespace Modules\Projects\Services;

use Illuminate\Support\Collection;
use Modules\Projects\Models\ProjectTask;
use Modules\Projects\Models\ProjectTaskDependency;

class TaskDependencyService
{
    /**
     * Add a dependency between tasks.
     */
    public function addDependency(
        ProjectTask $task,
        ProjectTask $dependsOnTask,
        string $type = 'finish_to_start',
        int $lagDays = 0
    ): ?ProjectTaskDependency {
        // Prevent self-dependency
        if ($task->id === $dependsOnTask->id) {
            return null;
        }

        // Prevent duplicate dependency
        if ($this->dependencyExists($task, $dependsOnTask)) {
            return null;
        }

        // Prevent circular dependency
        if ($this->wouldCreateCycle($task, $dependsOnTask)) {
            return null;
        }

        return ProjectTaskDependency::create([
            'task_id' => $task->id,
            'depends_on_task_id' => $dependsOnTask->id,
            'dependency_type' => $type,
            'lag_days' => $lagDays,
        ]);
    }

    /**
     * Remove a dependency.
     */
    public function removeDependency(ProjectTask $task, ProjectTask $dependsOnTask): bool
    {
        return ProjectTaskDependency::where('task_id', $task->id)
            ->where('depends_on_task_id', $dependsOnTask->id)
            ->delete() > 0;
    }

    /**
     * Check if dependency already exists.
     */
    public function dependencyExists(ProjectTask $task, ProjectTask $dependsOnTask): bool
    {
        return ProjectTaskDependency::where('task_id', $task->id)
            ->where('depends_on_task_id', $dependsOnTask->id)
            ->exists();
    }

    /**
     * Check if adding this dependency would create a cycle.
     */
    public function wouldCreateCycle(ProjectTask $task, ProjectTask $dependsOnTask): bool
    {
        // If dependsOnTask already depends on task (directly or indirectly), adding this would create a cycle
        return $this->taskDependsOn($dependsOnTask, $task);
    }

    /**
     * Check if taskA depends on taskB (directly or indirectly).
     */
    public function taskDependsOn(ProjectTask $taskA, ProjectTask $taskB, array $visited = []): bool
    {
        // Prevent infinite loop
        if (in_array($taskA->id, $visited)) {
            return false;
        }

        $visited[] = $taskA->id;

        // Check direct dependencies
        $directDeps = $taskA->dependencies()->pluck('depends_on_task_id')->toArray();

        if (in_array($taskB->id, $directDeps)) {
            return true;
        }

        // Check indirect dependencies (DFS)
        foreach ($directDeps as $depTaskId) {
            $depTask = ProjectTask::find($depTaskId);
            if ($depTask && $this->taskDependsOn($depTask, $taskB, $visited)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a task can start (all dependencies are satisfied).
     */
    public function canTaskStart(ProjectTask $task): bool
    {
        foreach ($task->dependencies as $dependency) {
            if (!$dependency->isSatisfied()) {
                return false;
            }
        }
        return true;
    }

    /**
     * Get all blocking tasks for a given task.
     */
    public function getBlockingTasks(ProjectTask $task): Collection
    {
        return $task->dependencies
            ->filter(fn ($dep) => !$dep->isSatisfied())
            ->map(fn ($dep) => $dep->dependsOnTask);
    }

    /**
     * Get the earliest start date for a task based on dependencies.
     */
    public function getEarliestStartDate(ProjectTask $task): ?\Carbon\Carbon
    {
        $earliestDate = null;

        foreach ($task->dependencies as $dependency) {
            $depDate = $dependency->getEarliestStartDate();

            if ($depDate && (!$earliestDate || $depDate->gt($earliestDate))) {
                $earliestDate = $depDate;
            }
        }

        return $earliestDate;
    }

    /**
     * Get all tasks that depend on this task (directly or indirectly).
     */
    public function getDependentTasks(ProjectTask $task, array $visited = []): Collection
    {
        if (in_array($task->id, $visited)) {
            return collect();
        }

        $visited[] = $task->id;
        $dependents = collect();

        // Get direct dependents
        $directDependents = ProjectTask::whereHas('dependencies', function ($q) use ($task) {
            $q->where('depends_on_task_id', $task->id);
        })->get();

        foreach ($directDependents as $dependent) {
            $dependents->push($dependent);
            $dependents = $dependents->merge($this->getDependentTasks($dependent, $visited));
        }

        return $dependents->unique('id');
    }

    /**
     * Get dependency chain for Gantt visualization.
     */
    public function getDependencyChain(ProjectTask $task): array
    {
        $chain = [];

        foreach ($task->dependencies as $dependency) {
            $chain[] = [
                'from_task_id' => $dependency->depends_on_task_id,
                'to_task_id' => $task->id,
                'type' => $dependency->dependency_type,
                'lag_days' => $dependency->lag_days,
                'is_satisfied' => $dependency->isSatisfied(),
            ];
        }

        return $chain;
    }

    /**
     * Validate all dependencies in a project (check for cycles).
     */
    public function validateProjectDependencies(int $projectId): array
    {
        $issues = [];

        $tasks = ProjectTask::where('project_id', $projectId)->get();

        foreach ($tasks as $task) {
            // Check for self-references
            $selfRef = $task->dependencies()->where('depends_on_task_id', $task->id)->exists();
            if ($selfRef) {
                $issues[] = [
                    'task_id' => $task->id,
                    'type' => 'self_reference',
                    'message' => "Task {$task->code} depends on itself",
                ];
            }

            // Check for cycles
            foreach ($task->dependencies as $dependency) {
                if ($this->taskDependsOn($dependency->dependsOnTask, $task)) {
                    $issues[] = [
                        'task_id' => $task->id,
                        'type' => 'circular',
                        'message' => "Circular dependency detected: {$task->code} <-> {$dependency->dependsOnTask->code}",
                    ];
                }
            }
        }

        return $issues;
    }

    /**
     * Calculate critical path for the project.
     * Returns tasks that are on the critical path (longest path of dependent tasks).
     */
    public function calculateCriticalPath(int $projectId): Collection
    {
        $tasks = ProjectTask::where('project_id', $projectId)
            ->with('dependencies')
            ->get()
            ->keyBy('id');

        // Calculate earliest start and finish times
        $taskData = [];

        foreach ($tasks as $task) {
            $taskData[$task->id] = [
                'task' => $task,
                'es' => 0, // Earliest Start
                'ef' => $task->estimated_hours ?? 0, // Earliest Finish
                'ls' => PHP_INT_MAX, // Latest Start
                'lf' => PHP_INT_MAX, // Latest Finish
                'slack' => 0,
            ];
        }

        // Forward pass - calculate ES and EF
        $processed = [];
        while (count($processed) < count($tasks)) {
            foreach ($tasks as $taskId => $task) {
                if (in_array($taskId, $processed)) {
                    continue;
                }

                // Check if all dependencies are processed
                $canProcess = true;
                $maxEf = 0;

                foreach ($task->dependencies as $dep) {
                    if (!in_array($dep->depends_on_task_id, $processed)) {
                        $canProcess = false;
                        break;
                    }
                    $maxEf = max($maxEf, $taskData[$dep->depends_on_task_id]['ef'] + $dep->lag_days);
                }

                if ($canProcess) {
                    $taskData[$taskId]['es'] = $maxEf;
                    $taskData[$taskId]['ef'] = $maxEf + ($task->estimated_hours ?? 0);
                    $processed[] = $taskId;
                }
            }

            // Prevent infinite loop
            if (count($processed) === 0) {
                break;
            }
        }

        // Find project end time
        $projectEndTime = max(array_column($taskData, 'ef'));

        // Backward pass - calculate LS and LF
        $processed = [];
        while (count($processed) < count($tasks)) {
            foreach ($tasks as $taskId => $task) {
                if (in_array($taskId, $processed)) {
                    continue;
                }

                // Find tasks that depend on this one
                $dependents = $task->dependents;

                if ($dependents->isEmpty()) {
                    // No dependents - use project end time
                    $taskData[$taskId]['lf'] = $projectEndTime;
                    $taskData[$taskId]['ls'] = $projectEndTime - ($task->estimated_hours ?? 0);
                    $processed[] = $taskId;
                } else {
                    // Check if all dependents are processed
                    $canProcess = true;
                    $minLs = PHP_INT_MAX;

                    foreach ($dependents as $dep) {
                        if (!in_array($dep->task_id, $processed)) {
                            $canProcess = false;
                            break;
                        }
                        $minLs = min($minLs, $taskData[$dep->task_id]['ls'] - $dep->lag_days);
                    }

                    if ($canProcess) {
                        $taskData[$taskId]['lf'] = $minLs;
                        $taskData[$taskId]['ls'] = $minLs - ($task->estimated_hours ?? 0);
                        $processed[] = $taskId;
                    }
                }
            }
        }

        // Calculate slack and identify critical path
        foreach ($taskData as $taskId => &$data) {
            $data['slack'] = $data['ls'] - $data['es'];
        }

        // Critical path = tasks with zero slack
        return collect($taskData)
            ->filter(fn ($data) => $data['slack'] === 0)
            ->map(fn ($data) => $data['task']);
    }
}
