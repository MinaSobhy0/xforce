<?php

return [
    'navigation' => 'Visits',
    'singular' => 'Visit',
    'plural' => 'Visits',

    'fields' => [
        'code' => 'Visit Code',
        'patient' => 'Patient',
        'branch' => 'Branch',
        'check_in_at' => 'Check-in Time',
        'check_out_at' => 'Check-out Time',
        'checked_in_by' => 'Checked In By',
        'checked_out_by' => 'Checked Out By',
        'status' => 'Status',
        'source' => 'Source',
        'chief_complaint' => 'Chief Complaint',
        'total' => 'Total',
        'notes' => 'Notes',
        'invoice' => 'Invoice',
        'appointments' => 'Appointments',
        'products' => 'Products',
        'duration' => 'Duration',
    ],

    'statuses' => [
        'open' => 'Open',
        'completed' => 'Completed',
        'invoiced' => 'Invoiced',
        'cancelled' => 'Cancelled',
    ],

    'sources' => [
        'walk_in' => 'Walk-in',
        'appointment' => 'Appointment',
        'online' => 'Online Booking',
    ],

    'actions' => [
        'checkout' => 'Checkout',
        'view_invoice' => 'View Invoice',
        'cancel' => 'Cancel Visit',
    ],

    'sections' => [
        'visit_info' => 'Visit Information',
        'appointments' => 'Appointments',
        'products' => 'Products Sold',
        'billing' => 'Billing Summary',
        'invoice_payments' => 'Invoice & Payments',
        'payments' => 'Payment History',
    ],

    'filters' => [
        'open_only' => 'Open Only',
    ],

    'messages' => [
        'visit_created' => 'Visit created',
        'visit_cancelled' => 'Visit cancelled',
        'checkout_required' => 'Please checkout before leaving',
        'no_visits' => 'No visit history found',
    ],
];
