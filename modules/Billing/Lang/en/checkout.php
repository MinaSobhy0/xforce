<?php

return [
    'navigation' => 'Session Checkout',
    'title' => 'Session Checkout',
    'heading' => 'Checkout - :patient (:code)',

    'invoice_code' => 'Invoice',
    'appointment' => 'Appointment',

    // Sections
    'services_completed' => 'Completed Services',
    'products_sold' => 'Products Sold',
    'payment_methods' => 'Payment Methods',
    'quick_actions' => 'Quick Actions',
    'summary' => 'Summary',

    // Labels
    'required' => 'required',
    'optional' => 'optional - uncheck to cancel',
    'products_note' => 'Uncheck products to cancel and return to inventory',
    'services_total' => 'Services Total',
    'products_total' => 'Products Total',
    'selected_total' => 'Selected Total',
    'payments_total' => 'Payments Total',
    'balance' => 'Balance',

    // Empty states
    'no_services' => 'No services in this invoice',
    'no_products' => 'No products sold',
    'invoice_not_found' => 'Invoice not found',

    // Payment
    'payment' => 'Payment',
    'select_method' => 'Select payment method...',
    'amount' => 'Amount',
    'reference' => 'Reference',
    'add_payment_method' => 'Add Payment Method',

    // Actions
    'pay_services_only' => 'Services Only',
    'pay_all' => 'Pay All',
    'complete_checkout' => 'Complete Checkout',
    'back' => 'Back to Reception',

    // Warnings
    'insufficient_payment_warning' => 'Payment amount is less than selected total',

    // Messages
    'checkout_complete' => 'Checkout completed successfully',
    'insufficient_payment' => 'Insufficient Payment',
    'insufficient_payment_body' => 'Required: :required, Provided: :provided',
    'no_payment_method' => 'Please select at least one payment method',
    'error' => 'An error occurred',
];
