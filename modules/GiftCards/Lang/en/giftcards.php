<?php

return [
    'navigation_label' => 'Gift Cards',
    'model_label' => 'Gift Card',
    'plural_label' => 'Gift Cards',

    'sections' => [
        'basic_info' => 'Gift Card Information',
        'status' => 'Status & Balance',
    ],

    'fields' => [
        'code' => 'Code',
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
    ],

    'actions' => [
        'activate' => 'Activate',
        'redeem' => 'Redeem',
        'refund' => 'Refund',
        'adjust' => 'Adjust',
        'cancel' => 'Cancel',
    ],

    'messages' => [
        'activated' => 'Gift card activated successfully',
        'redeemed' => 'Gift card redeemed successfully',
        'refunded' => 'Gift card refunded successfully',
        'adjusted' => 'Gift card adjusted successfully',
        'cancelled' => 'Gift card cancelled successfully',
    ],
];
