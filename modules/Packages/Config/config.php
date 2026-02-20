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
];
