<?php

return [
    'name' => 'Attendance',

    /*
    |--------------------------------------------------------------------------
    | Auto Checkout Settings
    |--------------------------------------------------------------------------
    */
    'auto_checkout_enabled' => env('ATTENDANCE_AUTO_CHECKOUT_ENABLED', true),
    'auto_checkout_time' => env('ATTENDANCE_AUTO_CHECKOUT_TIME', '23:59'),

    /*
    |--------------------------------------------------------------------------
    | Geofence Settings
    |--------------------------------------------------------------------------
    */
    'geofence_radius_meters' => env('ATTENDANCE_GEOFENCE_RADIUS', 100),
    'require_gps_for_mobile' => env('ATTENDANCE_REQUIRE_GPS', true),

    /*
    |--------------------------------------------------------------------------
    | Check-in/out Settings
    |--------------------------------------------------------------------------
    */
    'allow_early_checkin_minutes' => env('ATTENDANCE_EARLY_CHECKIN_MINUTES', 30),
    'overtime_threshold_minutes' => env('ATTENDANCE_OVERTIME_THRESHOLD', 30),

    /*
    |--------------------------------------------------------------------------
    | Violation Settings
    |--------------------------------------------------------------------------
    */
    'auto_create_violations' => env('ATTENDANCE_AUTO_VIOLATIONS', true),
    'violation_auto_approve' => env('ATTENDANCE_VIOLATION_AUTO_APPROVE', false),
];
