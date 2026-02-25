<?php

return [
    'navigation_label' => 'بطاقات الهدايا',
    'model_label' => 'بطاقة هدية',
    'plural_label' => 'بطاقات الهدايا',
    'templates' => 'قوالب بطاقات الهدايا',

    'sections' => [
        'basic_info' => 'معلومات بطاقة الهدية',
        'status' => 'الحالة والرصيد',
    ],

    'fields' => [
        'code' => 'الكود',
        'name' => 'الاسم',
        'description' => 'الوصف',
        'value' => 'القيمة',
        'initial_value' => 'القيمة الأصلية',
        'remaining_value' => 'الرصيد المتبقي',
        'status' => 'الحالة',
        'purchaser' => 'المشتري',
        'recipient' => 'المستلم',
        'expires_at' => 'تاريخ الانتهاء',
        'activated_at' => 'تاريخ التفعيل',
        'notes' => 'ملاحظات',
        'usage' => 'الاستخدام',
        'amount' => 'المبلغ',
        'balance' => 'الرصيد',
        'type' => 'النوع',
        'date' => 'التاريخ',
        'created_at' => 'تاريخ الإنشاء',
        'created_by' => 'أنشأ بواسطة',
        'reason' => 'السبب',
        'is_active' => 'نشط',
        'template' => 'القالب',
        'assigned_to' => 'مسند إلى',
        'sold_by' => 'باع بواسطة',
    ],

    'statuses' => [
        'draft' => 'مسودة',
        'active' => 'نشط',
        'partially_used' => 'مستخدم جزئياً',
        'fully_used' => 'مستخدم بالكامل',
        'expired' => 'منتهي',
        'cancelled' => 'ملغي',
    ],

    'types' => [
        'activate' => 'تفعيل',
        'redeem' => 'استرداد',
        'refund' => 'استرجاع',
        'adjust' => 'تعديل',
        'expire' => 'انتهاء',
    ],

    'filters' => [
        'has_balance' => 'له رصيد',
        'expiring_soon' => 'ينتهي قريباً',
        'by_template' => 'حسب القالب',
        'assigned' => 'المسندة فقط',
        'unassigned' => 'غير المسندة فقط',
    ],

    'actions' => [
        'activate' => 'تفعيل',
        'redeem' => 'استرداد',
        'refund' => 'استرجاع',
        'adjust' => 'تعديل',
        'cancel' => 'إلغاء',
        'generate_batch' => 'إنشاء دفعة',
        'assign_to_staff' => 'إسناد لموظف',
        'unassign' => 'إلغاء الإسناد',
        'export_csv' => 'تصدير CSV',
        'export_pdf' => 'تصدير PDF',
        'print' => 'طباعة',
        'view_statistics' => 'عرض الإحصائيات',
    ],

    'messages' => [
        'activated' => 'تم تفعيل بطاقة الهدية بنجاح',
        'redeemed' => 'تم استرداد بطاقة الهدية بنجاح',
        'refunded' => 'تم استرجاع بطاقة الهدية بنجاح',
        'adjusted' => 'تم تعديل بطاقة الهدية بنجاح',
        'cancelled' => 'تم إلغاء بطاقة الهدية بنجاح',
        'batch_generated' => 'تم إنشاء :count بطاقة هدية بنجاح',
        'batch_failed' => 'فشل إنشاء بطاقات الهدايا',
        'assigned' => 'تم إسناد :count بطاقة للموظف',
        'unassigned' => 'تم إلغاء إسناد :count بطاقة',
        'invalid_amount' => 'المبلغ خارج النطاق المسموح',
    ],

    'redemption_for_invoice' => 'استرداد للفاتورة :invoice',
    'days' => 'يوم',
    'close' => 'إغلاق',
    'statistics' => 'الإحصائيات',

    // Template section
    'template' => [
        'singular' => 'قالب بطاقة هدية',
        'plural' => 'قوالب بطاقات الهدايا',

        'sections' => [
            'basic' => 'المعلومات الأساسية',
            'value_config' => 'إعدادات القيمة',
            'discount' => 'إعدادات الخصم',
            'gl_accounts' => 'ربط الحسابات المحاسبية',
            'behavior' => 'سلوك البطاقة',
        ],

        'min_amount' => 'الحد الأدنى للمبلغ',
        'max_amount' => 'الحد الأقصى للمبلغ',
        'preset_amounts' => 'المبالغ المحددة مسبقاً',
        'preset_amounts_hint' => 'أدخل المبالغ الشائعة (مثل: 50، 100، 200)',
        'validity_days' => 'الصلاحية (أيام)',

        'discount_type' => 'نوع الخصم',
        'discount_percentage' => 'نسبة مئوية',
        'discount_fixed' => 'مبلغ ثابت',
        'no_discount' => 'بدون خصم',
        'discount_value' => 'قيمة الخصم',
        'discount_hint' => 'نسبة (0-100) أو مبلغ ثابت',

        'liability_account' => 'حساب التزام بطاقات الهدايا',
        'liability_hint' => 'حساب الدائن عند بيع البطاقة',
        'revenue_account' => 'حساب إيرادات الاسترداد',
        'revenue_hint' => 'حساب الدائن عند استرداد البطاقة',
        'breakage_account' => 'حساب إيرادات الانتهاء',
        'breakage_hint' => 'حساب الدائن عند انتهاء البطاقة برصيد',
        'expense_account' => 'حساب مصروفات الخصم',
        'expense_hint' => 'حساب المدين للخصومات',
        'sales_journal' => 'دفتر المبيعات',

        'allow_partial' => 'السماح بالاسترداد الجزئي',
        'allow_partial_hint' => 'السماح باستخدام البطاقة للدفع الجزئي',
        'requires_activation' => 'يتطلب التفعيل',
        'requires_activation_hint' => 'يجب تفعيل البطاقة قبل الاستخدام',

        'cards_count' => 'البطاقات المصدرة',
        'quantity' => 'الكمية',
        'card_value' => 'قيمة البطاقة',
        'value_range' => 'يجب أن تكون بين :min و :max',
        'generate_pin' => 'إنشاء رمز PIN',
    ],

    // Statistics
    'stats' => [
        'total_issued' => 'إجمالي المصدر',
        'total_value' => 'إجمالي القيمة',
        'active_cards' => 'البطاقات النشطة',
        'outstanding_balance' => 'الرصيد القائم',
        'redeemed_value' => 'القيمة المستردة',
        'expired_cards' => 'البطاقات المنتهية',
        'breakage_value' => 'قيمة الانتهاء',
        'redemption_rate' => 'نسبة الاسترداد',
        'cards_sold' => 'البطاقات المباعة',
        'cards_available' => 'البطاقات المتاحة',
        'sales_value' => 'قيمة المبيعات',
    ],

    // Staff Dashboard
    'staff_dashboard' => [
        'title' => 'بطاقات الهدايا الخاصة بي',
        'my_statistics' => 'إحصائياتي',
        'available_cards' => 'البطاقات المتاحة',
        'cards_by_denomination' => 'البطاقات حسب القيمة',
        'no_cards_assigned' => 'لا توجد بطاقات مسندة إليك',
        'quick_sell' => 'بيع سريع',
        'sell' => 'بيع',
        'sell_card' => 'بيع بطاقة هدية',
        'complete_sale' => 'إتمام البيع',
        'patient_type' => 'العميل',
        'existing_patient' => 'مريض موجود',
        'new_patient' => 'مريض جديد',
        'recipient_hint' => 'اختياري - إذا كانت البطاقة هدية لشخص آخر',
        'payment_method' => 'طريقة الدفع',
    ],
];
