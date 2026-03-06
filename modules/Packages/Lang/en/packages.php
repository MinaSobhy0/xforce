<?php

return [
    'navigation_label' => 'Packages',
    'model_label' => 'Package',
    'plural_label' => 'Packages',
    'packages' => 'Packages',

    'sections' => [
        'basic_info' => 'Package Information',
        'items' => 'Package Items',
        'items_description' => 'Add treatments and their quantities included in this package',
        'patient_selection' => 'Select Patient',
        'package_selection' => 'Select Package',
        'package_details' => 'Package Details',
        'payment' => 'Payment Options',
        'summary' => 'Order Summary',
        'active_packages' => 'Active Packages',
    ],

    'labels' => [
        'expires_in' => 'Expires in :days days',
        'balance_due' => 'Balance Due',
        'usage_progress' => 'Usage Progress',
        'sessions_used' => 'sessions used',
        'sessions_remaining' => 'sessions remaining',
        'sessions_consumed' => 'consumed',
        'sessions_booked' => 'booked',
        'sessions_available' => 'available',
        'consumed' => 'Consumed',
        'booked' => 'Booked',
        'pulses_remaining' => 'pulses remaining',
    ],

    'pages' => [
        'sell_package' => 'Sell Package',
    ],

    'fields' => [
        'name' => 'Name',
        'name_en' => 'Name (English)',
        'name_ar' => 'Name (Arabic)',
        'description' => 'Description',
        'description_en' => 'Description (English)',
        'description_ar' => 'Description (Arabic)',
        'type' => 'Type',
        'price' => 'Price',
        'validity_days' => 'Validity Period',
        'validity' => 'Validity',
        'days' => 'days',
        'is_transferable' => 'Transferable',
        'is_active' => 'Active',
        'active' => 'Active',
        'sort_order' => 'Sort Order',
        'treatment' => 'Treatment',
        'service' => 'Service',
        'quantity' => 'Quantity',
        'treatments' => 'Treatments',
        'treatments_suffix' => 'treatments',
        'services' => 'Services',
        'services_suffix' => 'services',
        'sessions' => 'Sessions',
        'sessions_suffix' => 'sessions',
        'sessions_used' => 'Sessions Used',
        'subscriptions' => 'Active Subs',
        'created_at' => 'Created At',
        'patient' => 'Patient',
        'status' => 'Status',
        'purchased_at' => 'Purchased At',
        'expires_at' => 'Expires At',
        'days_remaining' => 'Days Remaining',
        'progress' => 'Progress',
        'notes' => 'Notes',
        'cancellation_reason' => 'Cancellation Reason',
        'package' => 'Package',
        'payment_option' => 'Payment Option',
        'deposit_amount' => 'Deposit Amount',
        'min_deposit' => 'Minimum deposit: :amount (:percent%)',
        'min_deposit_percent' => 'Min Deposit %',
        'activation_rule' => 'Activation Rule',
        'activation_rule_help' => 'When should the package become active?',
        'consumption_type' => 'Type',
        'unit_price' => 'Unit Price',
        'line_total' => 'Total',
        'total_price' => 'Package Total',
        'pulses_per_session' => 'Pulses/Session',
    ],

    'consumption_types' => [
        'sessions' => 'Sessions',
        'pulses' => 'Pulses',
    ],

    'payment_options' => [
        'full' => 'Pay Full Amount Now',
        'deposit' => 'Pay Deposit Now',
    ],

    'activation_rules' => [
        'immediate' => 'Activate Immediately (can use sessions with balance)',
        'paid_in_full' => 'Activate After Full Payment',
    ],

    'validation' => [
        'min_deposit' => 'Minimum deposit is :amount',
    ],

    'types' => [
        'session_bundle' => 'Session Bundle',
        'value_bundle' => 'Value Bundle',
    ],

    'statuses' => [
        'active' => 'Active',
        'completed' => 'Completed',
        'expired' => 'Expired',
        'cancelled' => 'Cancelled',
        'frozen' => 'Frozen',
    ],

    'actions' => [
        'add_item' => 'Add Treatment',
        'freeze' => 'Freeze',
        'unfreeze' => 'Unfreeze',
        'cancel' => 'Cancel',
        'use_session' => 'Use Session',
        'sell_package' => 'Sell Package',
        'book_session' => 'Book Session',
    ],

    'messages' => [
        'frozen' => 'Subscription frozen successfully',
        'unfrozen' => 'Subscription unfrozen successfully',
        'cancelled' => 'Subscription cancelled successfully',
        'session_used' => 'Session used successfully',
        'no_active_packages' => 'No active packages',
    ],

    'notifications' => [
        'package_sold' => 'Package Sold Successfully',
        'package_sold_body' => ':package sold to :patient',
        'error' => 'Error',
    ],
];
