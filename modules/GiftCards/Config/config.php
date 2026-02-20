<?php

return [
    'name' => 'GiftCards',

    // Default expiry period in days (null = never expires)
    'default_expiry_days' => 365,

    // Minimum gift card value
    'min_value' => 100, // in minor units

    // Maximum gift card value
    'max_value' => 10000000, // in minor units

    // Allow partial redemption
    'allow_partial_redemption' => true,

    // Require recipient for gift cards
    'require_recipient' => false,

    // Generate unique codes automatically
    'auto_generate_code' => true,
];
