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
        'exceeds_max_per_request' => 'Request exceeds the maximum allowed (:max)',
        'no_staff_profile' => 'No staff profile linked to this account',
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

    'profile' => [
        'updated' => 'Profile updated successfully',
        'password_changed' => 'Password changed successfully',
        'current_password_incorrect' => 'Current password is incorrect',
        'password_same_as_old' => 'New password must be different from current password',
        'avatar_updated' => 'Avatar updated successfully',
        'avatar_deleted' => 'Avatar removed successfully',
    ],

    'screens' => [
        'dashboard' => 'Dashboard',
        'attendance' => 'Attendance',
        'time_off' => 'Time Off',
        'payslip' => 'Payslip',
        'schedule' => 'Schedule',
        'calendar' => 'Calendar',
        'appointments' => 'Appointments',
        'patients' => 'Patients',
        'profile' => 'Profile',
        'commission' => 'Commission',
    ],

    'calendar' => [
        'appointment' => 'Appointment',
        'time_off' => 'Time Off',
        'full_day' => 'Full Day',
        'invalid_date' => 'Invalid date format',
        'no_events' => 'No events for this day',
        'working_day' => 'Working Day',
        'day_off' => 'Day Off',
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

    /*
    |--------------------------------------------------------------------------
    | Notification Settings
    |--------------------------------------------------------------------------
    */

    'notification_settings' => 'Push Notification Settings',
    'notification_types' => 'Push Notification Types',
    'notification_types_description' => 'Enable or disable notification types for all staff in your clinic. Disabled notifications will not be sent to any user, regardless of their personal preferences.',
    'settings_saved' => 'Settings saved successfully',
    'save_settings' => 'Save Settings',

    /*
    |--------------------------------------------------------------------------
    | Quick Actions Settings
    |--------------------------------------------------------------------------
    */

    'quick_actions_settings' => 'Dashboard Quick Actions',
    'quick_actions_section' => 'Quick Actions',
    'quick_actions_description' => 'Configure the quick action buttons that appear on the mobile app dashboard. Staff can tap these to quickly navigate to different sections of the app.',
    'quick_action_screen' => 'Screen',
    'quick_action_icon' => 'Icon',
    'quick_action_color' => 'Color',
    'quick_action_enabled' => 'Enabled',
    'add_quick_action' => 'Add Quick Action',
    'error_no_tenant' => 'No tenant context found',

    /*
    |--------------------------------------------------------------------------
    | Device Restriction Settings
    |--------------------------------------------------------------------------
    */

    'device_restriction' => 'Device Restriction',
    'device_restriction_description' => 'Control how many devices a user can use to access the mobile app.',
    'single_device_mode' => 'Single Device Mode',
    'single_device_mode_help' => 'When enabled, users can only be logged in on one device at a time. Registering a new device will require admin approval or deactivation of the existing device.',

    /*
    |--------------------------------------------------------------------------
    | Device Management
    |--------------------------------------------------------------------------
    */

    'registered_devices' => 'Registered Devices',
    'registered_devices_description' => 'View and manage all devices registered for the mobile app. You can deactivate devices to allow users to register new ones.',
    'device_user' => 'User',
    'device_id' => 'Device ID',
    'device_platform' => 'Platform',
    'device_app_version' => 'App Version',
    'device_active' => 'Active',
    'device_last_used' => 'Last Used',
    'device_registered' => 'Registered',
    'deactivate_device' => 'Deactivate Device',
    'deactivate_device_confirm' => 'Are you sure you want to deactivate this device? The user will need to register their device again to receive push notifications.',
    'activate_device' => 'Activate Device',
    'device_deactivated' => 'Device deactivated successfully',
    'device_activated' => 'Device activated successfully',
    'devices_deactivated' => 'Selected devices deactivated',
    'deactivate_selected' => 'Deactivate Selected',
    'active_only' => 'Active Only',
    'inactive_only' => 'Inactive Only',
];
