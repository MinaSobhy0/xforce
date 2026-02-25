<?php

return [
    // Navigation
    'navigation_label' => 'Blackout Dates',
    'navigation_group' => 'Booking Settings',

    // Resource
    'label' => 'Blackout Date',
    'plural_label' => 'Blackout Dates',

    // Fields
    'fields' => [
        'name' => 'Name',
        'branch' => 'Branch',
        'start_date' => 'Start Date',
        'end_date' => 'End Date',
        'is_recurring' => 'Recurring',
        'recurrence_type' => 'Recurrence Type',
        'affects_online_booking' => 'Affects Online Booking',
        'affects_staff_booking' => 'Affects Staff Booking',
        'reason' => 'Reason',
        'is_active' => 'Active',
    ],

    // Labels
    'labels' => [
        'all_branches' => 'All Branches',
        'single_day' => 'Single Day',
        'date_range' => 'Date Range',
        'upcoming' => 'Upcoming',
        'past' => 'Past',
    ],

    // Recurrence Types
    'recurrence_types' => [
        'yearly' => 'Yearly',
        'monthly' => 'Monthly',
    ],

    // Helper Texts
    'helper_texts' => [
        'branch' => 'Leave empty to apply to all branches',
        'affects_online' => 'Block online booking during this period',
        'affects_staff' => 'Also block staff from creating bookings',
        'recurring' => 'Repeat this blackout date every year/month',
    ],

    // Placeholders
    'placeholders' => [
        'name' => 'e.g., Eid Al-Fitr, Annual Maintenance',
        'reason' => 'e.g., National holiday, Clinic closed for maintenance',
    ],

    // Messages
    'messages' => [
        'created' => 'Blackout date created successfully',
        'updated' => 'Blackout date updated successfully',
        'deleted' => 'Blackout date deleted successfully',
    ],

    // Table
    'table' => [
        'date' => 'Date',
        'dates' => ':start to :end',
        'single' => ':date',
        'recurring_yearly' => 'Repeats yearly',
        'recurring_monthly' => 'Repeats monthly',
        'online_only' => 'Online only',
        'all_booking' => 'All booking',
    ],
];
