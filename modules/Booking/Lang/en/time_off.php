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
        'start_date' => 'Start Date',
        'end_date' => 'End Date',
        'start_time' => 'Start Time',
        'end_time' => 'End Time',
        'is_full_day' => 'Full Day',
        'period' => 'Period',
        'days' => 'Days',
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
];
