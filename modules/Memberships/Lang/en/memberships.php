<?php

return [
    'navigation_label' => 'Memberships',
    'model_label' => 'Membership',
    'plural_label' => 'Memberships',

    'sections' => [
        'basic_info' => 'Membership Information',
        'pricing' => 'Pricing',
        'benefits' => 'Benefits',
        'settings' => 'Settings',
    ],

    'fields' => [
        'name' => 'Name',
        'name_en' => 'Name (English)',
        'name_ar' => 'Name (Arabic)',
        'description' => 'Description',
        'description_en' => 'Description (English)',
        'description_ar' => 'Description (Arabic)',
        'tier' => 'Tier',
        'price_monthly' => 'Monthly Price',
        'price_yearly' => 'Yearly Price',
        'monthly' => 'Monthly',
        'yearly' => 'Yearly',
        'discount_percentage' => 'Discount Percentage',
        'discount' => 'Discount',
        'included_sessions' => 'Included Sessions per Month',
        'treatment_id' => 'Treatment ID',
        'sessions_per_month' => 'Sessions per Month',
        'loyalty_multiplier' => 'Loyalty Points Multiplier',
        'loyalty' => 'Loyalty',
        'priority_booking' => 'Priority Booking',
        'priority' => 'Priority',
        'is_active' => 'Active',
        'active' => 'Active',
        'sort_order' => 'Sort Order',
        'members' => 'Active Members',
        'patient' => 'Patient',
        'status' => 'Status',
        'billing_cycle' => 'Billing Cycle',
        'started_at' => 'Started At',
        'expires_at' => 'Expires At',
        'days_remaining' => 'Days Remaining',
        'auto_renew' => 'Auto Renew',
        'notes' => 'Notes',
        'cancellation_reason' => 'Cancellation Reason',
    ],

    'tiers' => [
        'silver' => 'Silver',
        'gold' => 'Gold',
        'platinum' => 'Platinum',
        'diamond' => 'Diamond',
    ],

    'statuses' => [
        'active' => 'Active',
        'expired' => 'Expired',
        'cancelled' => 'Cancelled',
        'frozen' => 'Frozen',
    ],

    'billing_cycles' => [
        'monthly' => 'Monthly',
        'yearly' => 'Yearly',
    ],

    'actions' => [
        'add_session' => 'Add Session Benefit',
        'freeze' => 'Freeze',
        'unfreeze' => 'Unfreeze',
        'renew' => 'Renew',
        'cancel' => 'Cancel',
    ],

    'messages' => [
        'frozen' => 'Membership frozen successfully',
        'unfrozen' => 'Membership unfrozen successfully',
        'renewed' => 'Membership renewed successfully',
        'cancelled' => 'Membership cancelled successfully',
    ],
];
