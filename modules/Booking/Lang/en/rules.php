<?php

return [
    // Navigation
    'navigation_label' => 'Booking Rules',
    'navigation_group' => 'Booking Settings',

    // Resource
    'label' => 'Booking Rule',
    'plural_label' => 'Booking Rules',

    // Fields
    'fields' => [
        'name' => 'Rule Name',
        'code' => 'Rule Code',
        'description' => 'Description',
        'scope_level' => 'Scope Level',
        'branch' => 'Branch',
        'service' => 'Service',
        'rule_type' => 'Rule Type',
        'priority' => 'Priority',
        'is_active' => 'Active',
        'conditions' => 'Conditions',
        'actions' => 'Actions',
    ],

    // Scope Levels
    'scope_levels' => [
        'tenant' => 'Tenant (All Branches)',
        'branch' => 'Specific Branch',
        'service' => 'Specific Service',
    ],

    // Rule Types
    'rule_types' => [
        'slot_block' => 'Block Slots',
        'time_restriction' => 'Time Restriction',
        'capacity_limit' => 'Capacity Limit',
        'buffer_override' => 'Buffer Override',
        'advance_booking' => 'Advance Booking',
        'online_restriction' => 'Online Restriction',
        'practitioner_limit' => 'Practitioner Limit',
    ],

    // Rule Type Descriptions
    'rule_type_descriptions' => [
        'slot_block' => 'Block all slots during specified conditions',
        'time_restriction' => 'Restrict available hours',
        'capacity_limit' => 'Limit maximum appointments',
        'buffer_override' => 'Override buffer time between appointments',
        'advance_booking' => 'Override min/max advance booking time',
        'online_restriction' => 'Restrict online booking only',
        'practitioner_limit' => 'Limit specific practitioners',
    ],

    // Conditions
    'conditions' => [
        'days_of_week' => 'Days of Week',
        'time_range' => 'Time Range',
        'date_range' => 'Date Range',
        'services' => 'Services',
        'practitioners' => 'Practitioners',
        'start_time' => 'Start Time',
        'end_time' => 'End Time',
        'start_date' => 'Start Date',
        'end_date' => 'End Date',
    ],

    // Actions
    'action_fields' => [
        'block' => 'Block Slots',
        'reason' => 'Reason',
        'allowed_start' => 'Allowed Start Time',
        'allowed_end' => 'Allowed End Time',
        'max_appointments' => 'Maximum Appointments',
        'scope' => 'Per',
        'buffer_minutes' => 'Buffer Minutes',
        'min_hours' => 'Minimum Hours',
        'max_days' => 'Maximum Days',
        'allow' => 'Allow Online Booking',
    ],

    // Scope Options
    'scope_options' => [
        'day' => 'Per Day',
        'practitioner' => 'Per Practitioner',
    ],

    // Days
    'days' => [
        '0' => 'Sunday',
        '1' => 'Monday',
        '2' => 'Tuesday',
        '3' => 'Wednesday',
        '4' => 'Thursday',
        '5' => 'Friday',
        '6' => 'Saturday',
    ],

    // Placeholders
    'placeholders' => [
        'name' => 'e.g., Lunch Break',
        'code' => 'e.g., LUNCH_BREAK',
        'reason' => 'e.g., Staff lunch break',
    ],

    // Helper Texts
    'helper_texts' => [
        'code' => 'Unique identifier for this rule',
        'priority' => 'Higher priority rules are evaluated first (0-100)',
        'conditions_days' => 'Leave empty to apply all days',
        'conditions_services' => 'Leave empty to apply to all services',
    ],

    // Messages
    'messages' => [
        'created' => 'Booking rule created successfully',
        'updated' => 'Booking rule updated successfully',
        'deleted' => 'Booking rule deleted successfully',
    ],
];
