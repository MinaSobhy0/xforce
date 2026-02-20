<?php

return [
    'invoice' => 'Invoice',
    'invoices' => 'Invoices',
    'payment' => 'Payment',
    'payments' => 'Payments',
    'tax_rate' => 'Tax Rate',
    'tax_rates' => 'Tax Rates',

    'statuses' => [
        'draft' => 'Draft',
        'issued' => 'Issued',
        'partially_paid' => 'Partially Paid',
        'paid' => 'Paid',
        'overdue' => 'Overdue',
        'cancelled' => 'Cancelled',
        'refunded' => 'Refunded',
    ],

    'types' => [
        'standard' => 'Standard Invoice',
        'credit_note' => 'Credit Note',
        'proforma' => 'Proforma Invoice',
    ],

    'payment_methods' => [
        'cash' => 'Cash',
        'card' => 'Card',
        'bank_transfer' => 'Bank Transfer',
        'wallet' => 'Digital Wallet',
        'gift_card' => 'Gift Card',
        'insurance' => 'Insurance',
        'installment' => 'Installment',
        'online' => 'Online Payment',
    ],

    'actions' => [
        'issue' => 'Issue Invoice',
        'record_payment' => 'Record Payment',
        'cancel' => 'Cancel Invoice',
        'print' => 'Print',
        'download_pdf' => 'Download PDF',
    ],

    'messages' => [
        'invoice_issued' => 'Invoice issued successfully',
        'payment_recorded' => 'Payment recorded successfully',
        'invoice_cancelled' => 'Invoice cancelled',
    ],
];
