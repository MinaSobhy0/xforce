<?php

return [
    // Module
    'api' => 'API',

    // Auth
    'login_success' => 'Login successful',
    'logout_success' => 'Logged out successfully',
    'logout_all_success' => 'Logged out from all devices',
    'invalid_credentials' => 'Invalid email or password',
    'patient_not_found' => 'Phone number not registered',
    'account_inactive' => 'Account is inactive',
    'otp_sent' => 'Verification code sent',
    'otp_send_failed' => 'Failed to send verification code',
    'otp_expired' => 'Verification code expired',
    'invalid_otp' => 'Invalid verification code',

    // Profile
    'profile_updated' => 'Profile updated successfully',

    // Booking
    'booking_success' => 'Appointment booked successfully',
    'booking_failed' => 'Failed to book appointment',
    'booking_too_soon' => 'Cannot book appointment with less than required notice',
    'slot_not_available' => 'This time slot is no longer available',
    'appointment_not_found' => 'Appointment not found',
    'appointment_cancelled' => 'Appointment cancelled successfully',
    'cannot_cancel' => 'This appointment cannot be cancelled',
    'cancel_too_late' => 'Cannot cancel appointment within :hours hours of scheduled time',
    'cancelled_via_api' => 'Cancelled via API',

    // Gift Cards
    'gift_card_not_found' => 'Gift card not found or expired',

    // Modules
    'module_not_active' => 'The :module feature is not available',

    // Errors
    'unauthorized' => 'Unauthorized',
    'forbidden' => 'Access denied',
    'not_found' => 'Resource not found',
    'validation_failed' => 'Validation failed',
    'server_error' => 'Internal server error',
];
