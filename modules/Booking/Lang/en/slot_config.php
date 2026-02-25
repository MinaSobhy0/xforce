<?php

return [
    // Navigation
    'navigation_label' => 'Slot Configuration',
    'title' => 'Booking Slot Configuration',

    // Sections
    'sections' => [
        'time_duration' => 'Time & Duration',
        'booking_restrictions' => 'Booking Restrictions',
        'online_booking' => 'Online Booking',
        'capacity_limits' => 'Capacity Limits',
        'active_rules' => 'Active Rules',
        'slot_preview' => 'Slot Preview',
        'algorithm_visualization' => 'How Slots Are Generated',
    ],

    // Fields - Time & Duration
    'fields' => [
        'default_slot_duration' => 'Default Slot Duration',
        'slot_interval_minutes' => 'Slot Interval',
        'buffer_minutes' => 'Buffer Between Appointments',
        'default_start_time' => 'Default Working Hours Start',
        'default_end_time' => 'Default Working Hours End',

        // Booking Restrictions
        'min_advance_hours' => 'Minimum Advance Booking',
        'max_advance_booking_days' => 'Maximum Advance Booking',
        'cancellation_policy_hours' => 'Cancellation Notice Required',
        'allow_same_day_booking' => 'Allow Same-Day Booking',

        // Online Booking
        'allow_online_booking' => 'Enable Online Patient Booking',
        'auto_confirm_appointments' => 'Auto-Confirm Appointments',
        'show_practitioner_selection' => 'Allow Patients to Choose Practitioner',
        'require_deposit' => 'Require Deposit for Online Booking',
        'deposit_percentage' => 'Deposit Percentage',

        // Capacity Limits
        'max_appointments_per_day' => 'Max Appointments Per Day',
        'max_appointments_per_practitioner' => 'Max Per Practitioner',
        'overbooking_limit' => 'Overbooking Limit',
    ],

    // Helper texts
    'helper_texts' => [
        'slot_interval' => 'Generate slots every X minutes. Leave empty to use service duration.',
        'min_advance' => 'Patients must book at least this many hours ahead',
        'max_advance' => 'How far in advance can patients book',
        'auto_confirm' => 'Skip manual confirmation step',
        'overbooking' => 'Allow booking beyond normal capacity',
    ],

    // Units
    'units' => [
        'minutes' => 'minutes',
        'hours' => 'hours',
        'days' => 'days',
        'percent' => '%',
    ],

    // Actions
    'actions' => [
        'save' => 'Save Settings',
        'reset' => 'Reset to Defaults',
        'preview' => 'Preview',
        'generate_preview' => 'Generate Preview',
    ],

    // Messages
    'messages' => [
        'settings_saved' => 'Slot configuration saved successfully',
        'preview_generated' => 'Preview generated',
        'no_slots_available' => 'No slots available for the selected criteria',
    ],

    // Algorithm Steps
    'algorithm' => [
        'step1_service' => 'Load Service',
        'step1_desc' => 'Duration, buffer, restrictions',
        'step2_validate' => 'Validate Date',
        'step2_desc' => 'Allowed days, blackouts, max advance',
        'step3_time_range' => 'Get Time Range',
        'step3_desc' => 'Service-specific or defaults',
        'step4_intervals' => 'Generate Intervals',
        'step4_desc' => 'Based on duration + buffer',
        'step5_filters' => 'Apply Filters',
        'step5_filter1' => 'Practitioner Schedule',
        'step5_filter2' => 'Time Off',
        'step5_filter3' => 'Existing Appointments',
        'step5_filter4' => 'Room Availability',
        'step5_filter5' => 'Equipment Availability',
        'step5_filter6' => 'Booking Rules',
    ],

    // Quick Stats
    'stats' => [
        'active_rules' => 'Active Rules',
        'blackout_dates' => 'Blackout Dates',
        'default_duration' => 'Default Duration',
        'max_advance' => 'Max Advance',
    ],
];
