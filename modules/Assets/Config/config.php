<?php

return [
    'name' => 'Assets',

    // Depreciation methods available
    'depreciation_methods' => [
        'straight_line' => 'Straight Line',
        'declining_balance' => 'Declining Balance',
        'sum_of_years' => 'Sum of Years Digits',
        'no_depreciation' => 'No Depreciation',
    ],

    // Asset statuses
    'statuses' => [
        'draft' => 'Draft',
        'active' => 'Active',
        'fully_depreciated' => 'Fully Depreciated',
        'disposed' => 'Disposed',
        'written_off' => 'Written Off',
    ],

    // Acquisition methods
    'acquisition_methods' => [
        'purchase' => 'Purchase',
        'transfer' => 'Transfer',
        'donation' => 'Donation',
        'found' => 'Found/Discovered',
    ],

    // Disposal methods
    'disposal_methods' => [
        'sale' => 'Sale',
        'scrap' => 'Scrap',
        'donation' => 'Donation',
        'theft' => 'Theft',
        'damage' => 'Damage/Loss',
    ],

    // Default depreciation start rule
    // 'acquisition_date' = start from purchase date
    // 'next_month' = start from first day of next month
    'depreciation_start_rule' => 'next_month',

    // Auto-create assets from purchase orders
    'auto_create_on_purchase' => true,
];
