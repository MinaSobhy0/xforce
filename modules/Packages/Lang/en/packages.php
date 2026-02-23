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
    ],

    'messages' => [
        'frozen' => 'Subscription frozen successfully',
        'unfrozen' => 'Subscription unfrozen successfully',
        'cancelled' => 'Subscription cancelled successfully',
        'session_used' => 'Session used successfully',
    ],
];
