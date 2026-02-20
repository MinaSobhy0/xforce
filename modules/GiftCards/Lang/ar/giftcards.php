<?php

return [
    'navigation_label' => 'بطاقات الهدايا',
    'model_label' => 'بطاقة هدية',
    'plural_label' => 'بطاقات الهدايا',

    'sections' => [
        'basic_info' => 'معلومات بطاقة الهدية',
        'status' => 'الحالة والرصيد',
    ],

    'fields' => [
        'code' => 'الكود',
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
    ],

    'actions' => [
        'activate' => 'تفعيل',
        'redeem' => 'استرداد',
        'refund' => 'استرجاع',
        'adjust' => 'تعديل',
        'cancel' => 'إلغاء',
    ],

    'messages' => [
        'activated' => 'تم تفعيل بطاقة الهدية بنجاح',
        'redeemed' => 'تم استرداد بطاقة الهدية بنجاح',
        'refunded' => 'تم استرجاع بطاقة الهدية بنجاح',
        'adjusted' => 'تم تعديل بطاقة الهدية بنجاح',
        'cancelled' => 'تم إلغاء بطاقة الهدية بنجاح',
    ],
];
