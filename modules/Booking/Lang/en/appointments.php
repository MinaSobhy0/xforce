<?php

return [
    'navigation' => 'Appointments',
    'singular' => 'Appointment',
    'plural' => 'Appointments',

    'wizard' => [
        'patient' => 'Select Patient',
        'treatment' => 'Select Treatment',
        'schedule' => 'Schedule',
        'confirm' => 'Confirm',
    ],

    'fields' => [
        'code' => 'Code',
        'patient' => 'Patient',
        'treatment' => 'Treatment',
        'branch' => 'Branch',
        'practitioner' => 'Practitioner',
        'room' => 'Room',
        'equipment' => 'Equipment',
        'date' => 'Date',
        'start_time' => 'Start Time',
        'end_time' => 'End Time',
        'time' => 'Time',
        'duration' => 'Duration',
        'status' => 'Status',
        'price' => 'Price',
        'discount' => 'Discount',
        'net_price' => 'Net Price',
        'notes' => 'Notes',
        'internal_notes' => 'Internal Notes',
        'internal_notes_help' => 'Only visible to staff, not shown to patients',
        'cancellation_reason' => 'Cancellation Reason',
        'source' => 'Source',
        'confirmed_at' => 'Confirmed At',
        'checked_in_at' => 'Checked In At',
        'started_at' => 'Started At',
        'completed_at' => 'Completed At',
        'cancelled_at' => 'Cancelled At',
        'created_at' => 'Created At',
    ],

    'sections' => [
        'details' => 'Appointment Details',
        'patient' => 'Patient Information',
        'schedule' => 'Schedule',
        'pricing' => 'Pricing',
        'notes' => 'Notes',
        'timestamps' => 'Timestamps',
    ],

    'filters' => [
        'from' => 'From Date',
        'until' => 'Until Date',
        'today' => 'Today Only',
        'upcoming' => 'Upcoming',
    ],

    'actions' => [
        'confirm' => 'Confirm',
        'check_in' => 'Check In',
        'start' => 'Start',
        'complete' => 'Complete',
        'cancel' => 'Cancel',
        'no_show' => 'Mark No-Show',
        'reschedule' => 'Reschedule',
    ],

    'minutes' => 'min',
    'days' => 'days',
];
