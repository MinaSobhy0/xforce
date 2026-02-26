<?php

return [
    // Module
    'module_name' => 'Assets',
    'module_description' => 'Fixed asset management with depreciation tracking',

    // Navigation
    'nav' => [
        'asset_types' => 'Asset Types',
        'assets' => 'Assets',
    ],

    // Asset Types
    'asset_type' => [
        'singular' => 'Asset Type',
        'plural' => 'Asset Types',
        'create' => 'Create Asset Type',
        'edit' => 'Edit Asset Type',
        'fields' => [
            'code' => 'Code',
            'name' => 'Name',
            'description' => 'Description',
            'depreciation_method' => 'Depreciation Method',
            'useful_life_years' => 'Useful Life (Years)',
            'salvage_value_percent' => 'Salvage Value %',
            'declining_balance_rate' => 'Declining Balance Rate %',
            'fixed_asset_account' => 'Fixed Asset Account',
            'accumulated_depreciation_account' => 'Accumulated Depreciation Account',
            'depreciation_expense_account' => 'Depreciation Expense Account',
            'gain_loss_account' => 'Gain/Loss Account',
            'auto_create_on_purchase' => 'Auto-create on Purchase',
            'is_active' => 'Active',
        ],
        'sections' => [
            'basic' => 'Basic Information',
            'depreciation' => 'Depreciation Settings',
            'accounts' => 'GL Accounts',
            'behavior' => 'Behavior',
        ],
    ],

    // Assets
    'asset' => [
        'singular' => 'Asset',
        'plural' => 'Assets',
        'create' => 'Create Asset',
        'edit' => 'Edit Asset',
        'view' => 'View Asset',
        'fields' => [
            'code' => 'Asset Code',
            'name' => 'Name',
            'asset_type' => 'Asset Type',
            'branch' => 'Branch',
            'acquisition_date' => 'Acquisition Date',
            'acquisition_cost' => 'Acquisition Cost',
            'acquisition_method' => 'Acquisition Method',
            'salvage_value' => 'Salvage Value',
            'depreciable_value' => 'Depreciable Value',
            'accumulated_depreciation' => 'Accumulated Depreciation',
            'book_value' => 'Book Value',
            'depreciation_start_date' => 'Depreciation Start Date',
            'last_depreciation_date' => 'Last Depreciation Date',
            'status' => 'Status',
            'serial_number' => 'Serial Number',
            'location' => 'Location',
            'assigned_to' => 'Assigned To',
            'notes' => 'Notes',
            'disposal_date' => 'Disposal Date',
            'disposal_method' => 'Disposal Method',
            'disposal_value' => 'Disposal Value',
            'disposal_notes' => 'Disposal Notes',
        ],
        'sections' => [
            'basic' => 'Basic Information',
            'acquisition' => 'Acquisition Details',
            'depreciation' => 'Depreciation',
            'additional' => 'Additional Information',
            'disposal' => 'Disposal',
        ],
    ],

    // Depreciation Entries
    'depreciation_entry' => [
        'singular' => 'Depreciation Entry',
        'plural' => 'Depreciation Entries',
        'fields' => [
            'period' => 'Period',
            'period_start' => 'Period Start',
            'period_end' => 'Period End',
            'amount' => 'Depreciation Amount',
            'accumulated' => 'Accumulated',
            'book_value' => 'Book Value',
            'journal_entry' => 'Journal Entry',
            'status' => 'Status',
        ],
    ],

    // Statuses
    'statuses' => [
        'draft' => 'Draft',
        'active' => 'Active',
        'fully_depreciated' => 'Fully Depreciated',
        'disposed' => 'Disposed',
        'written_off' => 'Written Off',
        'posted' => 'Posted',
        'reversed' => 'Reversed',
    ],

    // Depreciation Methods
    'depreciation_methods' => [
        'straight_line' => 'Straight Line',
        'declining_balance' => 'Declining Balance',
        'sum_of_years' => 'Sum of Years Digits',
        'no_depreciation' => 'No Depreciation',
    ],

    // Acquisition Methods
    'acquisition_methods' => [
        'purchase' => 'Purchase',
        'transfer' => 'Transfer',
        'donation' => 'Donation',
        'found' => 'Found/Discovered',
    ],

    // Disposal Methods
    'disposal_methods' => [
        'sale' => 'Sale',
        'scrap' => 'Scrap',
        'donation' => 'Donation',
        'theft' => 'Theft',
        'damage' => 'Damage/Loss',
    ],

    // Actions
    'actions' => [
        'activate' => 'Activate',
        'activate_description' => 'Activate this asset and post acquisition journal entry',
        'dispose' => 'Dispose',
        'dispose_description' => 'Dispose of this asset',
        'write_off' => 'Write Off',
        'write_off_description' => 'Write off this asset with no proceeds',
        'run_depreciation' => 'Run Depreciation',
        'view_schedule' => 'View Depreciation Schedule',
        'view_journal_entry' => 'View Journal Entry',
    ],

    // Messages
    'messages' => [
        'activated' => 'Asset has been activated successfully',
        'disposed' => 'Asset has been disposed successfully',
        'written_off' => 'Asset has been written off successfully',
        'depreciation_processed' => 'Depreciation processed successfully',
        'cannot_activate' => 'Cannot activate this asset. Please check asset type configuration.',
        'cannot_dispose' => 'Cannot dispose this asset in its current status.',
        'already_depreciated' => 'Depreciation already exists for this period.',
    ],

    // Depreciation Command
    'command' => [
        'description' => 'Process monthly depreciation for active assets',
        'processing' => 'Processing depreciation for period :period...',
        'dry_run' => '[DRY RUN] No changes will be made',
        'completed' => 'Depreciation processing completed',
        'processed' => 'Processed: :count assets',
        'skipped' => 'Skipped: :count assets',
        'errors' => 'Errors: :count',
        'total_depreciation' => 'Total depreciation: :amount',
    ],

    // Statistics
    'stats' => [
        'total_assets' => 'Total Assets',
        'total_value' => 'Total Acquisition Value',
        'total_depreciation' => 'Total Depreciation',
        'total_book_value' => 'Total Book Value',
        'active_assets' => 'Active Assets',
        'disposed_assets' => 'Disposed Assets',
    ],
];
