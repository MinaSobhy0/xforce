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
