<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Push Notification Language Lines
    |--------------------------------------------------------------------------
    */

    'device' => [
        'registered' => 'Device registered for push notifications',
        'unregistered' => 'Device unregistered from push notifications',
        'not_found' => 'Device not found',
        'single_device_restriction' => 'You already have an active device registered. Please contact your administrator to deactivate your existing device before registering a new one.',
    ],

    'not_found' => 'Notification not found',
    'marked_read' => 'Notification marked as read',
    'all_marked_read' => 'All notifications marked as read',
    'preferences_updated' => 'Notification preferences updated',

    /*
    |--------------------------------------------------------------------------
    | Attendance Violation Notifications
    |--------------------------------------------------------------------------
    */

    'violation' => [
        'title' => 'Attendance Violation',
        'body' => 'A :type violation has been recorded for :date.',
    ],

    'violation_status' => [
        'title' => 'Violation Status Update',
        'body' => 'Your :type violation has been :status.',
        'waived_body' => 'Your :type violation for :date has been waived.',
        'approved' => 'approved',
        'waived' => 'waived',
    ],

    /*
    |--------------------------------------------------------------------------
    | Time Off Notifications
    |--------------------------------------------------------------------------
    */

    'time_off' => [
        'approved_title' => 'Time Off Approved',
        'approved_body' => 'Your leave request from :start_date to :end_date has been approved.',
        'rejected_title' => 'Time Off Rejected',
        'rejected_body' => 'Your leave request has been rejected. Reason: :reason',
        'no_reason' => 'No reason provided',
    ],

    /*
    |--------------------------------------------------------------------------
    | Appointment Notifications
    |--------------------------------------------------------------------------
    */

    'appointment' => [
        'assigned_title' => 'New Appointment Assigned',
        'assigned_body' => 'You have a new appointment with :patient_name on :date at :time.',
        'reminder_title' => 'Upcoming Appointment',
        'reminder_body' => 'You have an appointment with :patient_name at :time.',
        'cancelled_title' => 'Appointment Cancelled',
        'cancelled_body' => 'Your appointment with :patient_name has been cancelled.',
        'unknown_patient' => 'a patient',
    ],

    /*
    |--------------------------------------------------------------------------
    | Payslip Notifications
    |--------------------------------------------------------------------------
    */

    'payslip' => [
        'ready_title' => 'Payslip Ready',
        'ready_body' => 'Your payslip for :period is now available.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Commission Notifications
    |--------------------------------------------------------------------------
    */

    'commission' => [
        'earned_title' => 'Commission Earned',
        'earned_body' => 'You have earned :amount commission for :service.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Schedule Notifications
    |--------------------------------------------------------------------------
    */

    'schedule' => [
        'changed_title' => 'Schedule Changed',
        'changed_body' => 'Your schedule has been updated, effective :date.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Check-in Reminder Notifications
    |--------------------------------------------------------------------------
    */

    'check_in' => [
        'reminder_title' => 'Check-In Reminder',
        'reminder_body' => 'Don\'t forget to check in. Your shift starts at :time.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Types (for preferences)
    |--------------------------------------------------------------------------
    */

    'types' => [
        'attendance_violation' => 'Attendance Violations',
        'violation_status_changed' => 'Violation Status Changes',
        'check_in_reminder' => 'Check-In Reminders',
        'time_off_approved' => 'Time Off Approvals',
        'time_off_rejected' => 'Time Off Rejections',
        'appointment_assigned' => 'New Appointment Assigned',
        'appointment_reminder' => 'Appointment Reminders',
        'appointment_cancelled' => 'Appointment Cancellations',
        'payslip_ready' => 'Payslip Notifications',
        'commission_earned' => 'Commission Notifications',
        'schedule_changed' => 'Schedule Changes',
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Type Descriptions (for settings page)
    |--------------------------------------------------------------------------
    */

    'types_descriptions' => [
        'attendance_violation' => 'Notify staff when attendance violations are recorded',
        'violation_status_changed' => 'Notify staff when violation status changes (approved/waived)',
        'check_in_reminder' => 'Remind staff to check in before their shift starts',
        'time_off_approved' => 'Notify staff when their time-off request is approved',
        'time_off_rejected' => 'Notify staff when their time-off request is rejected',
        'appointment_assigned' => 'Notify practitioners when a new appointment is assigned to them',
        'appointment_reminder' => 'Remind staff about upcoming appointments',
        'appointment_cancelled' => 'Notify staff when their appointments are cancelled',
        'payslip_ready' => 'Notify staff when their payslip is available',
        'commission_earned' => 'Notify staff when commission is recorded',
        'schedule_changed' => 'Notify staff when their schedule is modified',
    ],
];
