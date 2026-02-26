<?php

return [
    // Module
    'module_name' => 'الأصول',
    'module_description' => 'إدارة الأصول الثابتة مع تتبع الإهلاك',

    // Navigation
    'nav' => [
        'asset_types' => 'أنواع الأصول',
        'assets' => 'الأصول',
    ],

    // Asset Types
    'asset_type' => [
        'singular' => 'نوع الأصل',
        'plural' => 'أنواع الأصول',
        'create' => 'إنشاء نوع أصل',
        'edit' => 'تعديل نوع الأصل',
        'fields' => [
            'code' => 'الرمز',
            'name' => 'الاسم',
            'description' => 'الوصف',
            'depreciation_method' => 'طريقة الإهلاك',
            'useful_life_years' => 'العمر الإنتاجي (سنوات)',
            'salvage_value_percent' => 'نسبة قيمة الإنقاذ %',
            'declining_balance_rate' => 'معدل القسط المتناقص %',
            'fixed_asset_account' => 'حساب الأصول الثابتة',
            'accumulated_depreciation_account' => 'حساب الإهلاك المتراكم',
            'depreciation_expense_account' => 'حساب مصروف الإهلاك',
            'gain_loss_account' => 'حساب الأرباح/الخسائر',
            'auto_create_on_purchase' => 'إنشاء تلقائي عند الشراء',
            'is_active' => 'نشط',
        ],
        'sections' => [
            'basic' => 'المعلومات الأساسية',
            'depreciation' => 'إعدادات الإهلاك',
            'accounts' => 'الحسابات المحاسبية',
            'behavior' => 'السلوك',
        ],
    ],

    // Assets
    'asset' => [
        'singular' => 'أصل',
        'plural' => 'الأصول',
        'create' => 'إنشاء أصل',
        'edit' => 'تعديل الأصل',
        'view' => 'عرض الأصل',
        'fields' => [
            'code' => 'رمز الأصل',
            'name' => 'الاسم',
            'asset_type' => 'نوع الأصل',
            'branch' => 'الفرع',
            'acquisition_date' => 'تاريخ الاقتناء',
            'acquisition_cost' => 'تكلفة الاقتناء',
            'acquisition_method' => 'طريقة الاقتناء',
            'salvage_value' => 'قيمة الإنقاذ',
            'depreciable_value' => 'القيمة القابلة للإهلاك',
            'accumulated_depreciation' => 'الإهلاك المتراكم',
            'book_value' => 'القيمة الدفترية',
            'depreciation_start_date' => 'تاريخ بدء الإهلاك',
            'last_depreciation_date' => 'تاريخ آخر إهلاك',
            'status' => 'الحالة',
            'serial_number' => 'الرقم التسلسلي',
            'location' => 'الموقع',
            'assigned_to' => 'مسند إلى',
            'notes' => 'ملاحظات',
            'disposal_date' => 'تاريخ التخلص',
            'disposal_method' => 'طريقة التخلص',
            'disposal_value' => 'قيمة التخلص',
            'disposal_notes' => 'ملاحظات التخلص',
        ],
        'sections' => [
            'basic' => 'المعلومات الأساسية',
            'acquisition' => 'تفاصيل الاقتناء',
            'depreciation' => 'الإهلاك',
            'additional' => 'معلومات إضافية',
            'disposal' => 'التخلص',
        ],
    ],

    // Depreciation Entries
    'depreciation_entry' => [
        'singular' => 'قيد إهلاك',
        'plural' => 'قيود الإهلاك',
        'fields' => [
            'period' => 'الفترة',
            'period_start' => 'بداية الفترة',
            'period_end' => 'نهاية الفترة',
            'amount' => 'مبلغ الإهلاك',
            'accumulated' => 'المتراكم',
            'book_value' => 'القيمة الدفترية',
            'journal_entry' => 'قيد اليومية',
            'status' => 'الحالة',
        ],
    ],

    // Statuses
    'statuses' => [
        'draft' => 'مسودة',
        'active' => 'نشط',
        'fully_depreciated' => 'مهلك بالكامل',
        'disposed' => 'تم التخلص منه',
        'written_off' => 'تم شطبه',
        'posted' => 'مرحل',
        'reversed' => 'معكوس',
    ],

    // Depreciation Methods
    'depreciation_methods' => [
        'straight_line' => 'القسط الثابت',
        'declining_balance' => 'القسط المتناقص',
        'sum_of_years' => 'مجموع أرقام السنوات',
        'no_depreciation' => 'بدون إهلاك',
    ],

    // Acquisition Methods
    'acquisition_methods' => [
        'purchase' => 'شراء',
        'transfer' => 'تحويل',
        'donation' => 'تبرع',
        'found' => 'مكتشف',
    ],

    // Disposal Methods
    'disposal_methods' => [
        'sale' => 'بيع',
        'scrap' => 'خردة',
        'donation' => 'تبرع',
        'theft' => 'سرقة',
        'damage' => 'تلف/خسارة',
    ],

    // Actions
    'actions' => [
        'activate' => 'تفعيل',
        'activate_description' => 'تفعيل هذا الأصل وترحيل قيد الاقتناء',
        'dispose' => 'التخلص',
        'dispose_description' => 'التخلص من هذا الأصل',
        'write_off' => 'شطب',
        'write_off_description' => 'شطب هذا الأصل بدون عائد',
        'run_depreciation' => 'تشغيل الإهلاك',
        'view_schedule' => 'عرض جدول الإهلاك',
        'view_journal_entry' => 'عرض قيد اليومية',
    ],

    // Messages
    'messages' => [
        'activated' => 'تم تفعيل الأصل بنجاح',
        'disposed' => 'تم التخلص من الأصل بنجاح',
        'written_off' => 'تم شطب الأصل بنجاح',
        'depreciation_processed' => 'تمت معالجة الإهلاك بنجاح',
        'cannot_activate' => 'لا يمكن تفعيل هذا الأصل. يرجى التحقق من إعدادات نوع الأصل.',
        'cannot_dispose' => 'لا يمكن التخلص من هذا الأصل في حالته الحالية.',
        'already_depreciated' => 'الإهلاك موجود بالفعل لهذه الفترة.',
    ],

    // Depreciation Command
    'command' => [
        'description' => 'معالجة الإهلاك الشهري للأصول النشطة',
        'processing' => 'جاري معالجة الإهلاك للفترة :period...',
        'dry_run' => '[تشغيل تجريبي] لن يتم إجراء أي تغييرات',
        'completed' => 'اكتملت معالجة الإهلاك',
        'processed' => 'تمت المعالجة: :count أصول',
        'skipped' => 'تم التخطي: :count أصول',
        'errors' => 'أخطاء: :count',
        'total_depreciation' => 'إجمالي الإهلاك: :amount',
    ],

    // Statistics
    'stats' => [
        'total_assets' => 'إجمالي الأصول',
        'total_value' => 'إجمالي قيمة الاقتناء',
        'total_depreciation' => 'إجمالي الإهلاك',
        'total_book_value' => 'إجمالي القيمة الدفترية',
        'active_assets' => 'الأصول النشطة',
        'disposed_assets' => 'الأصول المتخلص منها',
    ],
];
