<?php

return [
    'navigation_label' => 'Gift Cards',
    'model_label' => 'Gift Card',
    'plural_label' => 'Gift Cards',
    'templates' => 'Gift Card Templates',

    'sections' => [
        'basic_info' => 'Gift Card Information',
        'status' => 'Status & Balance',
    ],

    'fields' => [
        'code' => 'Code',
        'name' => 'Name',
        'description' => 'Description',
        'value' => 'Value',
        'initial_value' => 'Initial Value',
        'remaining_value' => 'Remaining Value',
        'status' => 'Status',
        'purchaser' => 'Purchaser',
        'recipient' => 'Recipient',
        'expires_at' => 'Expires At',
        'activated_at' => 'Activated At',
        'notes' => 'Notes',
        'usage' => 'Usage',
        'amount' => 'Amount',
        'balance' => 'Balance',
        'type' => 'Type',
        'date' => 'Date',
        'created_at' => 'Created At',
        'created_by' => 'Created By',
        'reason' => 'Reason',
        'is_active' => 'Active',
        'template' => 'Template',
        'assigned_to' => 'Assigned To',
        'sold_by' => 'Sold By',
    ],

    'statuses' => [
        'draft' => 'Draft',
        'active' => 'Active',
        'partially_used' => 'Partially Used',
        'fully_used' => 'Fully Used',
        'expired' => 'Expired',
        'cancelled' => 'Cancelled',
    ],

    'types' => [
        'activate' => 'Activation',
        'redeem' => 'Redemption',
        'refund' => 'Refund',
        'adjust' => 'Adjustment',
        'expire' => 'Expiration',
    ],

    'filters' => [
        'has_balance' => 'Has Balance',
        'expiring_soon' => 'Expiring Soon',
        'by_template' => 'By Template',
        'assigned' => 'Assigned Only',
        'unassigned' => 'Unassigned Only',
    ],

    'actions' => [
        'activate' => 'Activate',
        'redeem' => 'Redeem',
        'refund' => 'Refund',
        'adjust' => 'Adjust',
        'cancel' => 'Cancel',
        'generate_batch' => 'Generate Batch',
        'assign_to_staff' => 'Assign to Staff',
        'unassign' => 'Unassign',
        'export_csv' => 'Export CSV',
        'export_pdf' => 'Export PDF',
        'print' => 'Print',
        'view_statistics' => 'View Statistics',
    ],

    'messages' => [
        'activated' => 'Gift card activated successfully',
        'redeemed' => 'Gift card redeemed successfully',
        'refunded' => 'Gift card refunded successfully',
        'adjusted' => 'Gift card adjusted successfully',
        'cancelled' => 'Gift card cancelled successfully',
        'batch_generated' => ':count gift cards generated successfully',
        'batch_failed' => 'Failed to generate gift cards',
        'assigned' => ':count cards assigned to staff',
        'unassigned' => ':count cards unassigned',
        'invalid_amount' => 'Amount is outside the allowed range',
    ],

    'redemption_for_invoice' => 'Redemption for invoice :invoice',
    'days' => 'days',
    'close' => 'Close',
    'statistics' => 'Statistics',

    // Template section
    'template' => [
        'singular' => 'Gift Card Template',
        'plural' => 'Gift Card Templates',

        'sections' => [
            'basic' => 'Basic Information',
            'value_config' => 'Value Configuration',
            'discount' => 'Discount Settings',
            'gl_accounts' => 'GL Account Mapping',
            'behavior' => 'Card Behavior',
        ],

        'min_amount' => 'Minimum Amount',
        'max_amount' => 'Maximum Amount',
        'preset_amounts' => 'Preset Amounts',
        'preset_amounts_hint' => 'Enter common amounts (e.g., 50, 100, 200)',
        'validity_days' => 'Validity (Days)',

        'discount_type' => 'Discount Type',
        'discount_percentage' => 'Percentage Off',
        'discount_fixed' => 'Fixed Amount Off',
        'no_discount' => 'No Discount',
        'discount_value' => 'Discount Value',
        'discount_hint' => 'Percentage (0-100) or fixed amount in minor units',

        'liability_account' => 'Gift Card Liability Account',
        'liability_hint' => 'Credit account when card is sold',
        'revenue_account' => 'Redemption Revenue Account',
        'revenue_hint' => 'Credit account when card is redeemed',
        'breakage_account' => 'Breakage Revenue Account',
        'breakage_hint' => 'Credit account when card expires with balance',
        'expense_account' => 'Discount Expense Account',
        'expense_hint' => 'Debit account for discounts given',
        'sales_journal' => 'Sales Journal',

        'allow_partial' => 'Allow Partial Redemption',
        'allow_partial_hint' => 'Allow card to be used for partial payments',
        'requires_activation' => 'Requires Activation',
        'requires_activation_hint' => 'Card must be activated before use',

        'cards_count' => 'Cards Issued',
        'quantity' => 'Quantity',
        'card_value' => 'Card Value',
        'value_range' => 'Must be between :min and :max',
        'generate_pin' => 'Generate PIN Code',
    ],

    // Statistics
    'stats' => [
        'total_issued' => 'Total Issued',
        'total_value' => 'Total Value',
        'active_cards' => 'Active Cards',
        'outstanding_balance' => 'Outstanding Balance',
        'redeemed_value' => 'Redeemed Value',
        'expired_cards' => 'Expired Cards',
        'breakage_value' => 'Breakage Value',
        'redemption_rate' => 'Redemption Rate',
        'cards_sold' => 'Cards Sold',
        'cards_available' => 'Cards Available',
        'sales_value' => 'Sales Value',
    ],

    // Staff Dashboard
    'staff_dashboard' => [
        'title' => 'My Gift Cards',
        'my_statistics' => 'My Statistics',
        'available_cards' => 'Available Cards',
        'cards_by_denomination' => 'Cards by Denomination',
        'no_cards_assigned' => 'No cards assigned to you',
        'quick_sell' => 'Quick Sell',
        'sell' => 'Sell',
        'sell_card' => 'Sell Gift Card',
        'complete_sale' => 'Complete Sale',
        'patient_type' => 'Customer',
        'existing_patient' => 'Existing Patient',
        'new_patient' => 'New Patient',
        'recipient_hint' => 'Optional - if the card is a gift for someone else',
        'payment_method' => 'Payment Method',
    ],
];
