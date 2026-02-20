<?php

return [
    'name' => 'Loyalty',

    /*
    |--------------------------------------------------------------------------
    | Points Earning Configuration
    |--------------------------------------------------------------------------
    */

    // Default points per currency unit (1 EGP = X points)
    'default_points_per_currency_unit' => 1,

    // Apply membership tier multipliers to earned points
    'apply_membership_multiplier' => true,

    /*
    |--------------------------------------------------------------------------
    | Tier Configuration
    |--------------------------------------------------------------------------
    */

    'tier_thresholds' => [
        'bronze' => 0,
        'silver' => 1000,
        'gold' => 5000,
        'platinum' => 10000,
        'diamond' => 25000,
    ],

    'tier_benefits' => [
        'bronze' => [
            'points_multiplier' => 1.0,
            'discount_percentage' => 0,
        ],
        'silver' => [
            'points_multiplier' => 1.1,
            'discount_percentage' => 5,
        ],
        'gold' => [
            'points_multiplier' => 1.25,
            'discount_percentage' => 10,
        ],
        'platinum' => [
            'points_multiplier' => 1.5,
            'discount_percentage' => 15,
        ],
        'diamond' => [
            'points_multiplier' => 2.0,
            'discount_percentage' => 20,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Points Expiry Configuration
    |--------------------------------------------------------------------------
    */

    'points_expiry_enabled' => true,
    'points_expiry_months' => 24, // Points expire after 24 months

    /*
    |--------------------------------------------------------------------------
    | Points Redemption Configuration
    |--------------------------------------------------------------------------
    */

    // How many points equal 1 currency unit (100 points = 1 EGP)
    'points_redemption_ratio' => 100,

    // Minimum points required to redeem
    'min_redemption_points' => 100,

    // Maximum percentage of invoice that can be paid with points
    'max_redemption_percentage' => 50,

    /*
    |--------------------------------------------------------------------------
    | Referral Configuration
    |--------------------------------------------------------------------------
    */

    'referral_expiry_days' => 90, // Referral codes expire after 90 days
    'max_referrals_per_patient' => null, // null = unlimited

    /*
    |--------------------------------------------------------------------------
    | Notification Configuration
    |--------------------------------------------------------------------------
    */

    'notifications' => [
        'points_earned' => true,
        'points_redeemed' => true,
        'points_expiring_soon' => true,
        'tier_upgrade' => true,
        'birthday_bonus' => true,
    ],

    // Days before expiry to send reminder
    'expiry_reminder_days' => 30,
];
