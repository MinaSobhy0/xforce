<?php

return [
    'name' => 'Billing',

    // Default tax settings
    'default_tax_rate' => 14,
    'tax_inclusive' => false,

    // Auto invoice settings
    // Set to false when using visit-based invoicing (checkout page)
    'auto_invoice_on_complete' => false,

    // Payment settings
    'default_payment_terms_days' => 0,
    'enable_installments' => true,

    // Available payment methods
    'payment_methods' => [
        'cash' => true,
        'card' => true,
        'bank_transfer' => true,
        'wallet' => true,
        'gift_card' => true,
        'insurance' => false,
        'installment' => true,
        'online' => false,
    ],

    // Invoice numbering
    'invoice_prefix' => 'INV-',
    'payment_prefix' => 'PAY-',
];
