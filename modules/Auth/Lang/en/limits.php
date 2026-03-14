<?php

return [
    'email' => [
        'subject' => 'Action Required: User Limit Exceeded',
        'greeting' => 'Hello :name,',
        'exceeded_message' => 'Your organization currently has :current users, which exceeds your plan limit of :limit users by :overage user(s).',
        'grace_period' => 'You have :days days (until :date) to purchase additional user slots to avoid service interruption.',
        'action' => 'Upgrade Your Plan',
        'contact_support' => 'If you have any questions, please contact our support team.',
    ],

    'notification' => [
        'title' => 'User Limit Exceeded',
        'body' => 'You have :current users but your limit is :limit. Please purchase additional users.',
    ],

    'banner' => [
        'warning' => 'You have exceeded your user limit (:current/:limit). Please purchase additional users within :days days.',
        'expired' => 'Your grace period has expired! You have :current users but your limit is :limit. Please upgrade immediately.',
        'action' => 'Upgrade Now',
    ],
];
