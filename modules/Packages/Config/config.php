<?php

return [
    'name' => 'Packages',

    // Default validity period for packages in days
    'default_validity_days' => 365,

    // Allow package freezing
    'allow_freezing' => true,

    // Maximum freeze duration in days
    'max_freeze_days' => 90,

    // Allow package transfers between patients
    'allow_transfers' => false,

    // Notify before package expiry (days)
    'expiry_notification_days' => [30, 7, 1],

    // Automatically recognize revenue when package session is used
    'auto_revenue_recognition' => true,

    // Default minimum deposit percentage for packages (0 = full payment required)
    'default_min_deposit_percent' => 0,

    // Default activation rule for packages
    'default_activation_rule' => 'immediate', // 'immediate' or 'paid_in_full'
];
