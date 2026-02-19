<?php

return [
    'name' => 'Treatments',

    'default_duration_minutes' => env('TREATMENTS_DEFAULT_DURATION', 60),
    'default_buffer_minutes' => env('TREATMENTS_DEFAULT_BUFFER', 15),
    'require_consent' => env('TREATMENTS_REQUIRE_CONSENT', true),
    'show_prices_to_patients' => env('TREATMENTS_SHOW_PRICES', true),

    'fitzpatrick_ranges' => [
        'I-III' => ['min' => 1, 'max' => 3],
        'I-IV' => ['min' => 1, 'max' => 4],
        'I-V' => ['min' => 1, 'max' => 5],
        'I-VI' => ['min' => 1, 'max' => 6],
        'III-VI' => ['min' => 3, 'max' => 6],
        'IV-VI' => ['min' => 4, 'max' => 6],
    ],
];
