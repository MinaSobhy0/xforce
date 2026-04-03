<?php

return [
    // General
    'project' => 'Project',
    'projects' => 'Projects',
    'tag' => 'Tag',
    'tags' => 'Tags',
    'time_entry' => 'Time Entry',
    'time_entries' => 'Time Entries',
    'dashboard' => 'Projects Dashboard',
    'kanban_board' => 'Kanban Board',
    'gantt_view' => 'Gantt View',

    // Sections
    'sections' => [
        'project_details' => 'Project Details',
        'dates' => 'Dates',
        'budget' => 'Budget',
        'settings' => 'Project Settings',
        'description' => 'Description',
        'statistics' => 'Statistics',
    ],

    // Fields
    'fields' => [
        'code' => 'Code',
        'name' => 'Name',
        'name_en' => 'Name (English)',
        'name_ar' => 'Name (Arabic)',
        'description' => 'Description',
        'description_en' => 'Description (English)',
        'description_ar' => 'Description (Arabic)',
        'branch' => 'Branch',
        'manager' => 'Manager',
        'status' => 'Status',
        'priority' => 'Priority',
        'color' => 'Color',
        'start_date' => 'Start Date',
        'end_date' => 'End Date',
        'deadline' => 'Deadline',
        'target_date' => 'Target Date',
        'budget' => 'Budget',
        'actual_cost' => 'Actual Cost',
        'progress' => 'Progress',
        'total_tasks' => 'Total Tasks',
        'tasks' => 'Tasks',
        'logged_hours' => 'Logged Hours',
        'allow_timesheets' => 'Allow Timesheets',
        'is_template' => 'Template',
        'is_active' => 'Active',
        'created' => 'Created',
        'template' => 'Template',
        'status_type' => 'Status Type',
        'sort_order' => 'Order',
        'fold_by_default' => 'Collapse by Default',
        'is_final' => 'Final Stage',
        'user' => 'User',
        'role' => 'Role',
        'email' => 'Email',
        'date' => 'Date',
        'billable' => 'Billable',
        'hourly_rate' => 'Hourly Rate',
        'amount' => 'Amount',
        'timer' => 'Timer',
    ],

    // Helpers
    'helpers' => [
        'is_template' => 'Templates can be cloned to create new projects',
        'is_final' => 'Tasks in this stage are marked as complete',
    ],

    // Actions
    'actions' => [
        'view_kanban' => 'Kanban Board',
        'clone' => 'Clone',
        'activate' => 'Activate',
        'complete' => 'Complete',
        'reopen' => 'Reopen',
        'change_role' => 'Change Role',
        'stop_timer' => 'Stop Timer',
        'start_timer' => 'Start Timer',
    ],

    // Filters
    'filters' => [
        'templates' => 'Templates',
        'active' => 'Active',
        'this_week' => 'This Week',
        'this_month' => 'This Month',
        'from' => 'From',
        'until' => 'Until',
    ],

    // Messages
    'messages' => [
        'select_project' => 'Select a project to view',
        'no_tasks' => 'No tasks in this project yet',
        'timer_stopped' => 'Timer stopped successfully',
    ],

    // Timer
    'timer_running' => 'Timer Running',

    // Stats
    'stats' => [
        'active_projects' => 'Active Projects',
        'active_projects_desc' => 'Currently in progress',
        'open_tasks' => 'Open Tasks',
        'open_tasks_desc' => 'Across all projects',
        'my_tasks' => 'My Tasks',
        'my_tasks_desc' => 'Assigned to you',
        'overdue_tasks' => 'Overdue',
        'overdue_tasks_desc' => 'Past deadline',
        'hours_this_week' => 'Hours This Week',
        'hours_this_week_desc' => 'Your logged time',
    ],
];
