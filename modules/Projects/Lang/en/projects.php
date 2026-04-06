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
    'timer_paused' => 'Timer Paused',

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

    // Kanban State
    'kanban_state' => [
        'normal' => 'In Progress',
        'blocked' => 'Blocked',
        'done' => 'Ready',
    ],

    // Timesheet Status
    'timesheet_status' => [
        'draft' => 'Draft',
        'submitted' => 'Submitted',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
    ],

    // Timesheet Submission
    'timesheet_submission' => 'Timesheet Submission',
    'timesheet_submissions' => 'Timesheet Submissions',
    'timesheet' => [
        'week_of' => 'Week of :date',
        'total_hours' => 'Total Hours',
        'billable_hours' => 'Billable Hours',
        'non_billable_hours' => 'Non-Billable Hours',
        'days_worked' => 'Days Worked',
        'submit_for_approval' => 'Submit for Approval',
        'approve' => 'Approve',
        'reject' => 'Reject',
        'rejection_reason' => 'Rejection Reason',
        'approved_by' => 'Approved By',
        'submitted_at' => 'Submitted At',
        'approved_at' => 'Approved At',
        'pending_approval' => 'Pending Approval',
        'no_submissions' => 'No submissions yet',
        'submit_confirmation' => 'Are you sure you want to submit this timesheet for approval?',
    ],

    // Privacy
    'privacy' => [
        'label' => 'Privacy',
        'employees' => 'All Employees',
        'followers' => 'Followers Only',
        'portal' => 'Portal Users',
    ],

    // Timer Actions
    'timer' => [
        'start' => 'Start Timer',
        'stop' => 'Stop Timer',
        'pause' => 'Pause Timer',
        'resume' => 'Resume Timer',
        'elapsed' => 'Elapsed Time',
    ],

    // Task fields
    'task' => [
        'remaining_hours' => 'Remaining Hours',
        'effective_hours' => 'Effective Hours',
        'kanban_state' => 'Kanban State',
        'mark_blocked' => 'Mark as Blocked',
        'mark_ready' => 'Mark as Ready',
        'reset_state' => 'Reset State',
    ],

    // Member fields
    'member' => [
        'hourly_rate' => 'Hourly Rate',
    ],
];
