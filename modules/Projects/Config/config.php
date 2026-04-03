<?php

return [
    'name' => 'Projects',

    // Default task estimated hours
    'default_task_hours' => 8,

    // Enable time tracking
    'enable_time_tracking' => true,

    // Enable Gantt view
    'enable_gantt_view' => true,

    // Auto-complete parent tasks when all subtasks complete
    'auto_complete_parent_tasks' => false,

    // Default billable hourly rate (in minor units)
    'default_billable_rate_minor' => 0,

    // Default project stages
    'default_stages' => [
        ['name' => ['en' => 'Backlog', 'ar' => 'قائمة الانتظار'], 'status_type' => 'todo', 'color' => '#6B7280', 'fold_by_default' => true],
        ['name' => ['en' => 'To Do', 'ar' => 'للتنفيذ'], 'status_type' => 'todo', 'color' => '#3B82F6', 'fold_by_default' => false],
        ['name' => ['en' => 'In Progress', 'ar' => 'قيد التنفيذ'], 'status_type' => 'in_progress', 'color' => '#F59E0B', 'fold_by_default' => false],
        ['name' => ['en' => 'Review', 'ar' => 'للمراجعة'], 'status_type' => 'review', 'color' => '#8B5CF6', 'fold_by_default' => false],
        ['name' => ['en' => 'Done', 'ar' => 'مكتمل'], 'status_type' => 'done', 'color' => '#10B981', 'fold_by_default' => false, 'is_final' => true],
    ],

    // Dependency types
    'dependency_types' => [
        'finish_to_start' => 'Finish to Start (FS)',
        'start_to_start' => 'Start to Start (SS)',
        'finish_to_finish' => 'Finish to Finish (FF)',
        'start_to_finish' => 'Start to Finish (SF)',
    ],

    // Project statuses
    'project_statuses' => [
        'planning' => 'Planning',
        'active' => 'Active',
        'on_hold' => 'On Hold',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ],

    // Priority levels
    'priorities' => [
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High',
        'urgent' => 'Urgent',
    ],
];
