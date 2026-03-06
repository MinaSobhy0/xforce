<?php

return [
    'navigation' => 'Checkout',
    'title' => 'Visit Checkout',
    'heading' => 'Checkout - :code',

    'info' => [
        'check_in' => 'Check-in',
        'duration' => 'Duration',
        'checked_in_by' => 'Checked In By',
    ],

    'sections' => [
        'open_sessions' => 'Open Sessions',
        'completed_sessions' => 'Completed Sessions',
        'cancelled_sessions' => 'Cancelled Sessions',
        'products' => 'Products Sold',
        'packages' => 'Package Purchases',
        'summary' => 'Invoice Summary',
    ],

    'session_actions' => [
        'complete' => 'Complete Now',
        'cancel' => 'Cancel',
        'reschedule' => 'Reschedule',
    ],

    'session_of' => 'Session :current of :total',
    'package_covered' => 'Package',
    'discount' => 'Discount',

    'summary' => [
        'subtotal' => 'Subtotal',
        'package_sessions' => 'Package Sessions (:count)',
        'packages' => 'Package Purchases',
        'paying_now' => 'Paying Now',
        'balance_later' => 'Balance Due Later',
        'overall_discount' => 'Overall Discount',
        'discount' => 'Discount',
        'total' => 'Total',
    ],

    'discount_types' => [
        'none' => 'No Discount',
        'percent' => 'Percentage',
        'fixed' => 'Fixed Amount',
    ],

    'discount_reason_placeholder' => 'Discount reason (optional)',

    'stats' => [
        'total_services' => 'Services',
        'products_sold' => 'Products',
        'packages' => 'Packages',
    ],

    'labels' => [
        'payment_option' => 'Payment Option',
    ],

    'payment_options' => [
        'full' => 'Pay Full',
        'deposit' => 'Pay Deposit',
    ],

    'actions' => [
        'back' => 'Back',
        'view_patient' => 'View Patient',
        'confirm_checkout' => 'Complete Checkout',
        'generate_invoice' => 'Generate Invoice',
        'remove_package' => 'Remove Package',
    ],

    'modals' => [
        'confirm_checkout' => 'Confirm Checkout',
        'confirm_description' => 'Generate invoice for :total :currency?',
        'open_sessions_warning' => ':count open session(s) will be processed according to your selections.',
    ],

    'messages' => [
        'visit_not_found' => 'Visit not found',
        'already_invoiced' => 'This visit has already been invoiced',
        'visit_cancelled' => 'This visit has been cancelled',
        'discount_applied' => 'Discount applied',
        'checkout_success' => 'Checkout completed',
        'invoice_created' => 'Invoice :code has been created',
        'checkout_error' => 'Checkout failed',
        'empty_visit' => 'No billable items in this visit. Complete or add services before checkout.',
        'balance_remaining' => 'Balance of :amount :currency will be added to the invoice for later payment.',
        'package_removed' => 'Package removed from checkout',
    ],

    'invoice_notes' => [
        'discount' => 'Discount: :reason',
        'checkout_discount' => 'Checkout discount',
    ],
];
