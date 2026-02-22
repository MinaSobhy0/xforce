<?php

return [
    'navigation' => [
        'profiles' => 'Staff Profiles',
        'commissions' => 'Commissions',
    ],

    'labels' => [
        'profile' => 'Staff Profile',
        'profiles' => 'Staff Profiles',
        'commission' => 'Commission',
        'commissions' => 'Commissions',
        'schedule_assignment' => 'Schedule Assignment',
        'schedule_assignments' => 'Schedule Assignments',
    ],

    'relation_managers' => [
        'schedule_assignments' => 'Work Schedules',
    ],

    'sections' => [
        'basic_info' => 'Basic Information',
        'bio' => 'Biography & Specializations',
        'compensation' => 'Compensation',
        'commission' => 'Commission',
        'commission_description' => 'Assign a commission plan to calculate commissions on service revenue. Commission plans are managed in Settings > Commission Plans.',
        'settings' => 'Settings',
        'commission_details' => 'Commission Details',
        'amounts' => 'Amounts',
        'approval' => 'Approval',
        'commission_rule' => 'Commission Rule',
        'commission_type' => 'Commission Type',
        'tier_range' => 'Tier Range',
        'schedule_details' => 'Schedule Details',
        'schedule_summary' => 'Schedule Summary',
    ],

    'fields' => [
        'name' => 'Name',
        'user' => 'User',
        'branch' => 'Branch',
        'employee_number' => 'Employee Number',
        'job_title' => 'Job Title',
        'bio' => 'Biography',
        'specializations' => 'Specializations',
        'base_salary' => 'Base Salary',
        'commission_type' => 'Commission Type',
        'commission_percentage' => 'Commission Percentage',
        'commission' => 'Commission',
        'hire_date' => 'Hire Date',
        'contract_end_date' => 'Contract End Date',
        'is_active' => 'Active',
        'pending_earnings' => 'Pending',
        'treatment' => 'Treatment',
        'service' => 'Service',
        'category' => 'Category',
        'type' => 'Type',
        'flat_amount' => 'Flat Amount',
        'percentage' => 'Percentage',
        'tier_from' => 'Tier From',
        'tier_to' => 'Tier To',
        'rule' => 'Rule',
        'date' => 'Date',
        'appointment' => 'Appointment',
        'revenue' => 'Revenue',
        'rate' => 'Rate',
        'amount' => 'Amount',
        'status' => 'Status',
        'approved_at' => 'Approved At',
        'approved_by' => 'Approved By',
        'paid_at' => 'Paid At',
        'notes' => 'Notes',
        'work_schedule' => 'Work Schedule',
        'schedule' => 'Schedule',
        'effective_from' => 'Effective From',
        'effective_until' => 'Effective Until',
        'is_primary' => 'Primary',
        'is_primary_help' => 'Mark as primary schedule for this staff member',
    ],

    'commission_types' => [
        'flat' => 'Flat Amount',
        'percentage' => 'Percentage',
        'tiered' => 'Tiered',
    ],

    'statuses' => [
        'pending' => 'Pending',
        'approved' => 'Approved',
        'paid' => 'Paid',
        'cancelled' => 'Cancelled',
    ],

    'actions' => [
        'approve' => 'Approve',
        'cancel' => 'Cancel',
        'approve_selected' => 'Approve Selected',
    ],

    'messages' => [
        'approved' => 'Commission approved successfully',
        'cancelled' => 'Commission cancelled',
        'approved_count' => ':count commissions approved',
        'all_services' => 'All services',
    ],

    'widgets' => [
        'pending_commissions' => 'Pending Commissions',
        'pending_value' => 'pending value',
        'approved_commissions' => 'Approved Commissions',
        'awaiting_payment' => 'awaiting payment',
        'paid_this_month' => 'Paid This Month',
        'commissions_paid' => 'commissions paid',
    ],
];
