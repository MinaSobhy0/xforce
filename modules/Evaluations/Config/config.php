<?php

return [
    'name' => 'Evaluations',

    /*
    |--------------------------------------------------------------------------
    | Rating Scale
    |--------------------------------------------------------------------------
    |
    | The minimum and maximum values for rating fields.
    |
    */
    'rating_min' => 1,
    'rating_max' => 5,

    /*
    |--------------------------------------------------------------------------
    | NPS Score Range
    |--------------------------------------------------------------------------
    |
    | Net Promoter Score boundaries for categorization.
    |
    */
    'nps' => [
        'detractor_max' => 6,   // 0-6 are detractors
        'passive_max' => 8,     // 7-8 are passives
        // 9-10 are promoters
    ],

    /*
    |--------------------------------------------------------------------------
    | Evaluation Sources
    |--------------------------------------------------------------------------
    |
    | Available sources for evaluation collection.
    |
    */
    'sources' => [
        'staff' => 'Staff Entry',
        'kiosk' => 'In-Clinic Kiosk',
        'sms' => 'SMS Survey',
        'email' => 'Email Survey',
        'portal' => 'Patient Portal',
    ],
];
