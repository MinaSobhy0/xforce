<?php

return [
    'navigation' => 'Time Off',
    'singular' => 'Time Off Request',
    'plural' => 'Time Off Requests',
    'days_remaining' => 'days remaining',
    'unknown_type' => 'Unknown Type',

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
        'days_requested' => 'Days Requested',
        'days_requested_help' => 'Adjust if different from calendar days (e.g., half days)',
        'remaining_days' => ':days days remaining',
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
            'settings' => 'Settings & Rules',
        ],

        'fields' => [
            'name' => 'Name',
            'code' => 'Code',
            'description' => 'Description',
            'color' => 'Color',
            'is_paid' => 'Paid Leave',
            'requires_approval' => 'Requires Approval',
            'default_days' => 'Default Days/Year',
            'max_days_per_request' => 'Max Days per Request',
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
            'code' => 'Unique identifier (e.g., ANNUAL, SICK, PERSONAL)',
            'is_paid' => 'Whether this type of leave is paid',
            'default_days' => 'Default allocation when creating new allocations',
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
        ],

        'fields' => [
            'practitioner' => 'Practitioner',
            'type' => 'Time Off Type',
            'year' => 'Year',
            'allocated_days' => 'Allocated Days',
            'used_days' => 'Used Days',
            'carried_over' => 'Carried Over',
            'remaining_days' => 'Remaining Days',
            'notes' => 'Notes',
        ],

        'help' => [
            'carried_over' => 'Days carried over from previous year',
            'used_days' => 'Automatically calculated from approved requests',
        ],
    ],
];
