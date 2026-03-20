<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Mobile API Language Lines
    |--------------------------------------------------------------------------
    */

    'general' => [
        'success' => 'Success',
        'error' => 'Error',
        'unauthorized' => 'Unauthorized',
        'forbidden' => 'Access denied',
        'not_found' => 'Resource not found',
        'validation_error' => 'Validation error',
        'server_error' => 'Server error',
    ],

    'auth' => [
        'login_success' => 'Login successful',
        'login_failed' => 'Invalid credentials',
        'logout_success' => 'Logged out successfully',
        'token_refresh' => 'Token refreshed',
        'invalid_token' => 'Invalid or expired token',
        '2fa_required' => 'Two-factor authentication required',
        '2fa_invalid' => 'Invalid verification code',
        '2fa_success' => 'Two-factor authentication verified',
        'account_disabled' => 'Your account has been disabled',
        'not_staff' => 'Only staff members can access this app',
    ],

    'tenant' => [
        'invalid_code' => 'Invalid clinic code',
        'code_expired' => 'This code has expired',
        'code_usage_exceeded' => 'This code has reached its usage limit',
        'tenant_not_found' => 'Clinic not found',
        'tenant_inactive' => 'This clinic is currently inactive',
        'resolved' => 'Clinic found',
        'validated' => 'Clinic validated',
    ],

    'attendance' => [
        'checked_in' => 'Checked in successfully',
        'checked_out' => 'Checked out successfully',
        'already_checked_in' => 'You are already checked in',
        'already_completed_today' => 'Attendance already completed for today',
        'not_checked_in' => 'You are not checked in',
        'break_started' => 'Break started',
        'break_ended' => 'Break ended',
        'not_on_break' => 'You are not on break',
        'already_on_break' => 'You are already on break',
        'invalid_location' => 'You are not within the allowed area',
        'location_required' => 'Location is required for geofence check-in',
        'invalid_qr' => 'Invalid or expired QR code',
        'dispute_submitted' => 'Dispute submitted successfully',
        'cannot_dispute' => 'This violation cannot be disputed',
        'method_not_allowed' => 'This check-in method is not allowed for you',
        'location_not_allowed' => 'You are not allowed to check in at this location',
    ],

    'time_off' => [
        'request_submitted' => 'Leave request submitted',
        'request_cancelled' => 'Leave request cancelled',
        'cannot_cancel' => 'This request cannot be cancelled',
        'insufficient_balance' => 'Insufficient leave balance',
        'dates_overlap' => 'These dates overlap with an existing request',
    ],

    'payroll' => [
        'no_payslip' => 'No payslip available for this period',
        'payslip_downloaded' => 'Payslip downloaded',
    ],

    'schedule' => [
        'no_schedule' => 'No schedule assigned',
        'no_shift' => 'No shift for this date',
        'flexible' => 'Flexible schedule',
        'fixed' => 'Fixed schedule',
    ],

    'commission' => [
        'no_plan' => 'No commission plan assigned',
        'no_records' => 'No commission records found',
        'status_pending' => 'Pending',
        'status_approved' => 'Approved',
        'status_paid' => 'Paid',
        'status_cancelled' => 'Cancelled',
    ],

    'appointments' => [
        'session_started' => 'Session started',
        'session_completed' => 'Session completed',
        'notes_saved' => 'Notes saved',
        'cannot_start' => 'Cannot start this session',
        'cannot_complete' => 'Cannot complete this session',
    ],

    'patients' => [
        'no_results' => 'No patients found',
    ],

    'screens' => [
        'dashboard' => 'Dashboard',
        'attendance' => 'Attendance',
        'time_off' => 'Time Off',
        'payslip' => 'Payslip',
        'schedule' => 'Schedule',
        'appointments' => 'Appointments',
        'patients' => 'Patients',
        'profile' => 'Profile',
        'commission' => 'Commission',
    ],

    'dashboard' => [
        'today_appointments' => 'Today\'s Appointments',
        'completed' => 'Completed',
        'pending_commission' => 'Pending Commission',
        'hours_today' => 'Hours Today',
    ],

    'filament' => [
        'qr_page_title' => 'Mobile App QR Code',
        'qr_page_description' => 'Scan this QR code with the XLinic mobile app to connect to your clinic',
        'download_qr' => 'Download QR Code',
        'copy_link' => 'Copy Link',
        'link_copied' => 'Link copied to clipboard',
        'clinic_code' => 'Clinic Code',
    ],
];
