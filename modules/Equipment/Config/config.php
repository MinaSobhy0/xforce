<?php

return [
    'name' => 'Equipment',

    // Maintenance reminder settings
    'maintenance_reminder_days' => 7,

    // Shot counter settings
    'shot_counter_warning_threshold' => 90, // percentage

    // Equipment categories
    'categories' => [
        'laser' => 'Laser',
        'ipl' => 'IPL',
        'rf' => 'RF (Radiofrequency)',
        'hifu' => 'HIFU',
        'cryolipolysis' => 'Cryolipolysis',
        'microneedling' => 'Microneedling',
        'hydrafacial' => 'Hydrafacial',
        'led' => 'LED Therapy',
        'other' => 'Other',
    ],

    // Equipment statuses
    'statuses' => [
        'active' => 'Active',
        'maintenance' => 'Under Maintenance',
        'out_of_service' => 'Out of Service',
        'retired' => 'Retired',
    ],

    // Maintenance types
    'maintenance_types' => [
        'preventive' => 'Preventive Maintenance',
        'corrective' => 'Corrective Maintenance',
        'calibration' => 'Calibration',
    ],
];
