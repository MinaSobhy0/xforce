<?php

return [
    'navigation' => 'Time Off',
    'singular' => 'Time Off Request',
    'plural' => 'Time Off Requests',
    'days_remaining' => 'days remaining',
    'hours_remaining' => 'hours remaining',
    'remaining_this_month' => 'remaining this month',
    'remaining_this_year' => 'remaining this year',
    'unknown_type' => 'Unknown Type',

    // Request units
    'request_units' => [
        'day' => 'Days',
        'half_day' => 'Half Days',
        'hour' => 'Hours',
    ],

    'request_units_singular' => [
        'day' => 'Day',
        'half_day' => 'Half Day',
        'hour' => 'Hour',
    ],

    // Allocation periods
    'allocation_periods' => [
        'yearly' => 'Yearly',
        'monthly' => 'Monthly',
    ],

    'sections' => [
        'request' => 'Request Details',
        'period' => 'Period',
        'details' => 'Additional Details',
    ],

    'fields' => [
        'practitioner' => 'Practitioner',
        'staff' => 'Staff Member',
        'branch' => 'Branch',
        'branch_help' => 'Leave empty to apply to all branches',
        'branch_auto' => 'Auto-filled from staff member',
        'type' => 'Type',
        'time_off_type' => 'Time Off Type',
        'legacy_type' => 'Type (Legacy)',
        'legacy_type_help' => 'Use only if no time off types are configured',
        'start_date' => 'Start Date',
        'end_date' => 'End Date',
        'start_time' => 'Start Time',
        'end_time' => 'End Time',
        'is_full_day' => 'Full Day',
        'period' => 'Period',
        'days' => 'Days',
        'duration' => 'Duration',
        'days_requested' => 'Days Requested',
        'days_requested_help' => 'Adjust if different from calendar days (e.g., half days)',
        'hours_requested' => 'Hours Requested',
        'hours_requested_help' => 'Hours will be auto-calculated from time range',
        'remaining_days' => ':days days remaining',
        'remaining_this_month' => ':value remaining this month',
        'remaining_this_year' => ':value remaining this year',
        'select_staff_first' => 'Select a staff member first',
        'reason' => 'Reason',
        'status' => 'Status',
        'approved_by' => 'Approved By',
        'rejection_reason' => 'Rejection Reason',
        'notes' => 'Notes',
        'created_at' => 'Requested At',
    ],

    'filters' => [
        'from' => 'From Date',
        'until' => 'Until Date',
        'pending_only' => 'Pending Only',
    ],

    'actions' => [
        'approve' => 'Approve',
        'reject' => 'Reject',
        'cancel' => 'Cancel',
    ],

    'messages' => [
        'approved' => 'Time off request approved',
        'rejected' => 'Time off request rejected',
        'cancelled' => 'Time off request cancelled',
        'cannot_edit_non_pending' => 'Only pending time off requests can be edited',
    ],

    'all_branches' => 'All Branches',

    'tabs' => [
        'my_time_off' => 'My Time Off',
        'pending' => 'Pending Approval',
        'approved' => 'Approved',
        'all' => 'All Requests',
    ],

    // Time Off Types
    'types' => [
        'navigation' => 'Time Off Types',
        'singular' => 'Time Off Type',
        'plural' => 'Time Off Types',

        'sections' => [
            'basic' => 'Basic Information',
            'unit_settings' => 'Unit & Allocation Settings',
            'settings' => 'Approval & Rules',
        ],

        'fields' => [
            'name' => 'Name',
            'code' => 'Code',
            'description' => 'Description',
            'color' => 'Color',
            'is_paid' => 'Paid Leave',
            'requires_approval' => 'Requires Approval',
            'request_unit' => 'Request Unit',
            'allocation_period' => 'Allocation Period',
            'hours_per_day' => 'Hours per Day',
            'default_allocation' => 'Default Allocation',
            'default_days' => 'Default Days/Year',
            'default_hours' => 'Default Hours',
            'max_per_request' => 'Max per Request',
            'max_days_per_request' => 'Max Days per Request',
            'max_hours_per_request' => 'Max Hours per Request',
            'min_days_notice' => 'Min Days Notice',
            'allow_half_day' => 'Allow Half Day',
            'allow_partial_day' => 'Allow Partial Day',
            'is_active' => 'Active',
            'sort_order' => 'Sort Order',
            'approval_type' => 'Who Can Approve',
            'approval_roles' => 'Approval Roles',
            'approval_users' => 'Approval Users',
        ],

        'approval_types' => [
            'any' => 'Any Manager',
            'roles' => 'Specific Roles Only',
            'users' => 'Specific Users Only',
            'roles_or_users' => 'Specific Roles or Users',
        ],

        'help' => [
            'code' => 'Unique identifier (e.g., ANNUAL, SICK, EXCUSE)',
            'is_paid' => 'Whether this type of leave is paid',
            'request_unit' => 'Unit for requesting time off (days, half days, or hours)',
            'allocation_period' => 'How often allocation resets (yearly or monthly)',
            'hours_per_day' => 'Standard work hours per day for conversion',
            'default_allocation_yearly' => 'Default allocation per year',
            'default_allocation_monthly' => 'Default allocation per month',
            'default_days' => 'Default allocation when creating new allocations',
            'max_per_request' => 'Maximum allowed per request (leave empty for unlimited)',
            'max_days' => 'Maximum days allowed per request (leave empty for unlimited)',
            'min_notice' => 'Minimum days in advance required to request',
            'partial_day' => 'Allow requesting specific hours within a day',
            'approval_roles' => 'Select roles that can approve this type of time off',
            'approval_users' => 'Select specific users that can approve this type of time off',
        ],
    ],

    // Time Off Allocations
    'allocations' => [
        'navigation' => 'Time Off Allocations',
        'singular' => 'Time Off Allocation',
        'plural' => 'Time Off Allocations',

        'sections' => [
            'allocation' => 'Allocation Details',
            'days' => 'Days',
            'hours' => 'Hours',
        ],

        'fields' => [
            'practitioner' => 'Practitioner',
            'type' => 'Time Off Type',
            'year' => 'Year',
            'month' => 'Month',
            'period' => 'Period',
            'allocated' => 'Allocated',
            'allocated_days' => 'Allocated Days',
            'allocated_hours' => 'Allocated Hours',
            'used' => 'Used',
            'used_days' => 'Used Days',
            'used_hours' => 'Used Hours',
            'carried_over' => 'Carried Over',
            'carried_over_hours' => 'Carried Over Hours',
            'remaining' => 'Remaining',
            'remaining_days' => 'Remaining Days',
            'remaining_hours' => 'Remaining Hours',
            'notes' => 'Notes',
        ],

        'help' => [
            'carried_over' => 'Carried over from previous period',
            'used_days' => 'Automatically calculated from approved requests',
            'month' => 'Required for monthly allocation types',
        ],

        'bulk' => [
            'button' => 'Bulk Allocate',
            'title' => 'Bulk Allocation',
            'submit' => 'Create Allocations',
            'select_staff' => 'Select Staff Members',
            'select_staff_help' => 'Choose which staff members to allocate to',
            'all_staff' => 'Select All Staff',
            'amount_help' => 'Amount to allocate (uses default if not changed)',
            'skip_existing' => 'Skip Existing Allocations',
            'skip_existing_help' => 'If checked, staff who already have an allocation will be skipped',
            'success' => 'Bulk Allocation Complete',
            'success_message' => 'Created :created allocations, skipped :skipped existing.',
        ],
    ],
];
