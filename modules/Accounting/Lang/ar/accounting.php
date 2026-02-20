<?php

return [
    'journals' => 'دفاتر اليومية',
    'journal' => 'دفتر يومية',
    'chart_of_accounts' => 'دليل الحسابات',
    'journal_entries' => 'القيود اليومية',
    'fiscal_periods' => 'الفترات المالية',
    'trial_balance' => 'ميزان المراجعة',
    'profit_loss' => 'الأرباح والخسائر',
    'balance_sheet' => 'الميزانية العمومية',

    'account_types' => [
        'asset' => 'أصول',
        'liability' => 'خصوم',
        'equity' => 'حقوق الملكية',
        'revenue' => 'إيرادات',
        'expense' => 'مصروفات',
    ],

    'statuses' => [
        'draft' => 'مسودة',
        'posted' => 'مرحل',
        'cancelled' => 'ملغي',
        'open' => 'مفتوح',
        'closed' => 'مغلق',
        'locked' => 'مقفل',
    ],

    'actions' => [
        'post' => 'ترحيل القيد',
        'reverse' => 'عكس القيد',
        'close_period' => 'إغلاق الفترة',
        'reopen_period' => 'إعادة فتح الفترة',
    ],

    'messages' => [
        'entry_posted' => 'تم ترحيل القيد بنجاح',
        'entry_reversed' => 'تم عكس القيد بنجاح',
        'period_closed' => 'تم إغلاق الفترة المالية',
        'unbalanced_entry' => 'يجب أن يكون القيد متوازناً (المدين = الدائن)',
    ],
];
