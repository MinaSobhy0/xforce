<?php

return [
    'name' => 'Booking',

    // Default appointment settings
    'default_slot_duration' => 30, // minutes
    'buffer_minutes' => 5,
    'auto_confirm' => false,
    'reminder_hours_before' => 24,
    'allow_online_booking' => true,
    'max_advance_booking_days' => 60,
    'cancellation_policy_hours' => 24,

    // Slot generation settings
    'min_advance_hours' => 2, // Minimum hours before appointment can be booked
    'slot_interval_minutes' => null, // null = use service duration, or set fixed interval
    'show_practitioner_selection' => true, // Allow manual practitioner selection
    'auto_assign_practitioner' => true, // Auto-select first available practitioner
    'auto_assign_room' => true, // Auto-assign room based on service settings
    'auto_assign_equipment' => true, // Auto-assign required equipment

    // Multi-service booking
    'max_services_per_booking' => 5,
    'allow_parallel_services' => false, // Different practitioners at same time
    'sequential_buffer_minutes' => 5, // Buffer between sequential services

    // Package booking
    'show_package_expiry_warning_days' => 14,
    'auto_suggest_packages' => true,
    'allow_in_screen_purchase' => true,

    // Working hours defaults
    'default_start_time' => '09:00',
    'default_end_time' => '21:00',

    // Status colors
    'status_colors' => [
        'scheduled' => 'info',
        'confirmed' => 'primary',
        'checked_in' => 'warning',
        'in_progress' => 'secondary',
        'completed' => 'success',
        'cancelled' => 'danger',
        'no_show' => 'gray',
        'rescheduled' => 'warning',
    ],

    // Appointment sources
    'sources' => [
        'walk_in' => 'Walk-in',
        'phone' => 'Phone',
        'website' => 'Website',
        'mobile_app' => 'Mobile App',
        'referral' => 'Referral',
        'social_media' => 'Social Media',
    ],
];
