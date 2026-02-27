<?php

return [
    // Navigation
    'navigation' => 'Reception',
    'title' => 'Reception Dashboard',
    'heading' => 'Reception Dashboard',

    // Sections
    'sections' => [
        'appointments' => 'Today\'s Appointments',
        'patient_flow' => 'Patient Flow',
    ],

    // Statistics
    'stats' => [
        'total' => 'Total',
        'total_desc' => 'Appointments today',
        'waiting' => 'Waiting',
        'waiting_desc' => 'In waiting area',
        'in_rooms' => 'In Rooms',
        'in_rooms_desc' => 'Assigned to room',
        'in_progress' => 'In Progress',
        'in_progress_desc' => 'With doctor',
        'completed' => 'Completed',
        'completed_desc' => 'Done today',
        'no_shows' => 'No-shows',
        'no_shows_desc' => 'Did not arrive',
    ],

    // Statuses
    'statuses' => [
        'scheduled' => 'Scheduled',
        'confirmed' => 'Confirmed',
        'checked_in' => 'Checked In',
        'in_progress' => 'In Progress',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'no_show' => 'No Show',
        'rescheduled' => 'Rescheduled',
    ],

    // Columns
    'columns' => [
        'time' => 'Time',
        'patient' => 'Patient',
        'service' => 'Service',
        'doctor' => 'Doctor',
        'room' => 'Room',
        'wait_time' => 'Wait Time',
        'status' => 'Status',
    ],

    // Filters
    'filters' => [
        'status' => 'Status',
        'practitioner' => 'Practitioner',
        'room' => 'Room',
        'search' => 'Search patient...',
        'date' => 'Date',
        'previous_day' => 'Previous day',
        'next_day' => 'Next day',
        'today' => 'Today',
        'viewing_today' => 'Viewing Today',
        'viewing_date' => 'Viewing Past/Future Date',
    ],

    // Actions
    'actions' => [
        'check_in' => 'Check In',
        'checking_in' => 'Checking In',
        'assign_room' => 'Assign Room',
        'assign_doctor' => 'Assign Doctor',
        'start' => 'Start Session',
        'no_show' => 'Mark No-show',
        'view' => 'View',
        'record_payment' => 'Record Payment',
        'checkout' => 'Checkout',
        'loading' => 'Loading',
    ],

    // Forms
    'forms' => [
        'room' => 'Select Room',
        'practitioner' => 'Select Practitioner',
    ],

    // Messages
    'messages' => [
        'appointment_not_found' => 'Appointment not found',
        'cannot_check_in' => 'Cannot check in patient',
        'checked_in' => 'Patient Checked In',
        'checked_in_body' => ':patient has been checked in',
        'room_not_found' => 'Room not found',
        'room_assigned' => 'Room Assigned',
        'room_assigned_body' => ':patient assigned to :room',
        'practitioner_not_found' => 'Practitioner not found',
        'doctor_assigned' => 'Doctor Assigned',
        'doctor_assigned_body' => ':patient assigned to :doctor',
        'cannot_start' => 'Cannot start session',
        'session_started' => 'Session Started',
        'session_started_body' => 'Session started for :patient',
        'cannot_mark_no_show' => 'Cannot mark as no-show',
        'marked_no_show' => 'Marked as No-show',
        'marked_no_show_body' => ':patient marked as no-show',
        'no_invoice' => 'No invoice found for this appointment',
    ],

    // Patient Flow
    'flow' => [
        'title' => 'Patient Flow',
        'arriving' => 'Arriving Soon',
        'arriving_desc' => 'Next 30 minutes',
        'waiting' => 'Waiting Area',
        'waiting_desc' => 'Checked in, no room',
        'in_rooms' => 'In Rooms',
        'in_rooms_desc' => 'Assigned to room',
        'with_doctor' => 'With Doctor',
        'with_doctor_desc' => 'Session in progress',
        'done' => 'Done',
        'done_desc' => 'Completed (last 2 hrs)',
        'empty' => 'No patients',
        'room_available' => 'Available',
        'room_empty' => 'Room is available',
        'ready_for_checkout' => 'Ready for Checkout',
    ],

    // Modal
    'modal' => [
        'cancel' => 'Cancel',
        'save' => 'Save',
        'current_doctor' => 'Current Doctor',
    ],

    // Misc
    'unassigned' => 'Unassigned',
    'no_room' => 'No room',
    'unknown_patient' => 'Unknown Patient',
];
