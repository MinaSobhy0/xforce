<?php

return [
    'name' => 'TreatmentPlans',

    // Auto-complete treatment plan when all sessions are completed
    'auto_complete_on_all_sessions' => true,

    // Default session interval in days
    'default_session_interval_days' => 7,

    // Status colors for UI
    'status_colors' => [
        'draft' => 'gray',
        'active' => 'success',
        'paused' => 'warning',
        'completed' => 'info',
        'cancelled' => 'danger',
    ],

    // Item status colors
    'item_status_colors' => [
        'pending' => 'gray',
        'in_progress' => 'warning',
        'completed' => 'success',
        'cancelled' => 'danger',
    ],

    // Preferred time slots
    'time_slots' => [
        'morning' => 'Morning (9AM - 12PM)',
        'afternoon' => 'Afternoon (12PM - 5PM)',
        'evening' => 'Evening (5PM - 9PM)',
    ],

    // Days of week
    'days_of_week' => [
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    ],

    // Source types for treatment plan creation
    'sources' => [
        'manual' => 'Manual Entry',
        'consultation' => 'From Consultation',
    ],
];
