<?php

return [
    'name' => 'Accounting',

    // Default chart of accounts structure
    'default_fiscal_year_start' => '01-01',

    // Auto-generate journal entries
    'auto_journal_on_invoice' => true,
    'auto_journal_on_payment' => true,

    // Default accounts mapping
    'accounts' => [
        'accounts_receivable' => '1200',
        'cash' => '1100',
        'bank' => '1150',
        'revenue' => '4000',
        'vat_payable' => '2100',
        'deferred_revenue' => '2200',
    ],
];
