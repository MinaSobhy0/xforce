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

    'tabs' => [
        'all' => 'All',
        'today' => 'Today',
        'cash' => 'Cash',
        'bank' => 'Bank Transfer',
    ],

    'messages' => [
        'invoice_issued' => 'Invoice issued successfully',
        'payment_recorded' => 'Payment recorded successfully',
        'invoice_cancelled' => 'Invoice cancelled',
    ],

    // PDF Invoice
    'pdf' => [
        'invoice' => 'Invoice',
        'date' => 'Date',
        'due_date' => 'Due Date',
        'bill_to' => 'Bill To',
        'branch' => 'Branch',
        'phone' => 'Phone',
        'email' => 'Email',
        'tax_number' => 'Tax No.',
        'description' => 'Description',
        'qty' => 'Qty',
        'unit_price' => 'Unit Price',
        'discount' => 'Discount',
        'tax' => 'Tax',
        'total' => 'Total',
        'subtotal' => 'Subtotal',
        'paid' => 'Paid',
        'balance_due' => 'Balance Due',
        'payment_history' => 'Payment History',
        'method' => 'Method',
        'reference' => 'Reference',
        'amount' => 'Amount',
        'notes' => 'Notes',
        'thank_you' => 'Thank you for your business!',
    ],

    // Payment integration errors
    'errors' => [
        'gift_card_not_found' => 'Gift card not found',
        'gift_card_not_redeemable' => 'Gift card cannot be redeemed (status: :status)',
        'nothing_to_pay' => 'Nothing to pay',
        'no_patient_on_invoice' => 'Invoice has no associated patient',
        'no_loyalty_points' => 'Patient has no loyalty points available',
        'insufficient_points' => 'Insufficient loyalty points (requested: :requested, available: :available)',
    ],

    // Payment notes
    'notes' => [
        'gift_card_payment' => 'Payment via gift card :code',
        'loyalty_points_payment' => 'Payment via :points loyalty points',
        'member_discount_applied' => 'Member discount applied (:tier tier, :percentage%)',
    ],

    // Loyalty points
    'loyalty_points' => 'Loyalty Points',
    'points_balance' => 'Points Balance',
    'points_value' => 'Points Value',
    'use_points' => 'Use Points',

    // Member discount
    'member_discount' => 'Member Discount',
    'membership_tier' => 'Membership Tier',
];
