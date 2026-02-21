<?php

return [
    'name' => 'PatientPortal',

    /*
    |--------------------------------------------------------------------------
    | Brand Name
    |--------------------------------------------------------------------------
    */
    'brand_name' => env('PORTAL_BRAND_NAME', 'Patient Portal'),

    /*
    |--------------------------------------------------------------------------
    | OTP Settings
    |--------------------------------------------------------------------------
    */
    'otp' => [
        'length' => 6,
        'expiry_minutes' => 5,
        'max_attempts' => 3,
        'cooldown_seconds' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */
    'auth' => [
        'methods' => ['phone_otp', 'email_password'],
        'default_method' => 'phone_otp',
        'session_lifetime' => 120, // minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Booking Settings
    |--------------------------------------------------------------------------
    */
    'booking' => [
        'advance_days' => 30, // How many days in advance can book
        'min_hours_before' => 2, // Minimum hours before appointment to book
        'cancel_hours_before' => 24, // Hours before appointment to allow cancellation
        'reschedule_hours_before' => 24, // Hours before to allow reschedule
        'slots_per_page' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Features Toggle
    |--------------------------------------------------------------------------
    */
    'features' => [
        'booking' => true,
        'invoices' => true,
        'online_payment' => true,
        'loyalty' => true,
        'gift_cards' => true,
        'consent_forms' => true,
        'photos' => true,
        'medical_history' => false, // Requires extra security
    ],

    /*
    |--------------------------------------------------------------------------
    | Display Settings
    |--------------------------------------------------------------------------
    */
    'display' => [
        'upcoming_appointments_limit' => 5,
        'recent_invoices_limit' => 5,
        'transaction_history_limit' => 20,
    ],
];
