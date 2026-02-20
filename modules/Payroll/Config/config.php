<?php

return [
    'name' => 'Payroll',

    // Default payment day of month
    'default_payment_day' => 25,

    // Social insurance percentage
    'social_insurance_percentage' => 11.0,

    // Tax brackets (Egyptian tax system - simplified)
    'tax_brackets' => [
        ['from' => 0, 'to' => 15000, 'rate' => 0],
        ['from' => 15001, 'to' => 30000, 'rate' => 2.5],
        ['from' => 30001, 'to' => 45000, 'rate' => 10],
        ['from' => 45001, 'to' => 60000, 'rate' => 15],
        ['from' => 60001, 'to' => 200000, 'rate' => 20],
        ['from' => 200001, 'to' => 400000, 'rate' => 22.5],
        ['from' => 400001, 'to' => null, 'rate' => 25],
    ],
];
