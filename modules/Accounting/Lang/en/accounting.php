<?php

return [
    'journals' => 'Journals',
    'journal' => 'Journal',
    'chart_of_accounts' => 'Chart of Accounts',
    'journal_entries' => 'Journal Entries',
    'fiscal_periods' => 'Fiscal Periods',
    'trial_balance' => 'Trial Balance',
    'profit_loss' => 'Profit & Loss',
    'balance_sheet' => 'Balance Sheet',

    'account_types' => [
        'asset' => 'Asset',
        'liability' => 'Liability',
        'equity' => 'Equity',
        'revenue' => 'Revenue',
        'expense' => 'Expense',
    ],

    'statuses' => [
        'draft' => 'Draft',
        'posted' => 'Posted',
        'cancelled' => 'Cancelled',
        'open' => 'Open',
        'closed' => 'Closed',
        'locked' => 'Locked',
    ],

    'actions' => [
        'post' => 'Post Entry',
        'reverse' => 'Reverse Entry',
        'close_period' => 'Close Period',
        'reopen_period' => 'Reopen Period',
    ],

    'messages' => [
        'entry_posted' => 'Journal entry posted successfully',
        'entry_reversed' => 'Journal entry reversed successfully',
        'period_closed' => 'Fiscal period closed',
        'unbalanced_entry' => 'Journal entry must be balanced (debit = credit)',
    ],
];
