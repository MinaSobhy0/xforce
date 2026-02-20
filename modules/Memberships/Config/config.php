<?php

return [
    'name' => 'Memberships',

    // Tiers available
    'tiers' => ['silver', 'gold', 'platinum', 'diamond'],

    // Default discount percentage per tier
    'default_discounts' => [
        'silver' => 5,
        'gold' => 10,
        'platinum' => 15,
        'diamond' => 20,
    ],

    // Default loyalty multiplier per tier
    'default_loyalty_multipliers' => [
        'silver' => 1.0,
        'gold' => 1.5,
        'platinum' => 2.0,
        'diamond' => 3.0,
    ],

    // Auto-renewal settings
    'auto_renewal_enabled' => true,
    'renewal_reminder_days' => [30, 7, 1],
];
