<?php

return [
    'navigation' => 'Waitlist',
    'singular' => 'Waitlist Entry',
    'plural' => 'Waitlist',

    'sections' => [
        'patient' => 'Patient',
        'treatment' => 'Treatment Details',
        'preferences' => 'Preferences',
        'notes' => 'Notes',
    ],

    'fields' => [
        'patient' => 'Patient',
        'treatment' => 'Treatment',
        'branch' => 'Branch',
        'practitioner' => 'Preferred Practitioner',
        'practitioner_help' => 'Leave empty for any available practitioner',
        'preferred_days' => 'Preferred Days',
        'preferred_times' => 'Preferred Times',
        'priority' => 'Priority',
        'status' => 'Status',
        'notes' => 'Notes',
        'notified_at' => 'Notified At',
        'expires_at' => 'Expires At',
        'expires_at_help' => 'Leave empty for no expiration',
        'created_at' => 'Added At',
    ],

    'filters' => [
        'active_only' => 'Active Only',
    ],

    'actions' => [
        'notify' => 'Mark Notified',
        'book' => 'Create Appointment',
        'mark_booked' => 'Mark as Booked',
        'cancel' => 'Cancel',
    ],

    'any_practitioner' => 'Any Practitioner',
];
