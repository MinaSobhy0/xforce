<?php

return [
    'navigation' => 'Time Off',
    'singular' => 'Time Off Request',
    'plural' => 'Time Off Requests',

    'sections' => [
        'request' => 'Request Details',
        'period' => 'Period',
        'details' => 'Additional Details',
    ],

    'fields' => [
        'practitioner' => 'Practitioner',
        'branch' => 'Branch',
        'branch_help' => 'Leave empty to apply to all branches',
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

    'all_branches' => 'All Branches',

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
        ],

        'help' => [
            'code' => 'Unique identifier (e.g., ANNUAL, SICK, PERSONAL)',
            'is_paid' => 'Whether this type of leave is paid',
            'default_days' => 'Default allocation when creating new allocations',
            'max_days' => 'Maximum days allowed per request (leave empty for unlimited)',
            'min_notice' => 'Minimum days in advance required to request',
            'partial_day' => 'Allow requesting specific hours within a day',
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
