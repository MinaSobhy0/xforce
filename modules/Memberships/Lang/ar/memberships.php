<?php

return [
    'navigation_label' => 'العضويات',
    'model_label' => 'عضوية',
    'plural_label' => 'العضويات',

    'sections' => [
        'basic_info' => 'معلومات العضوية',
        'pricing' => 'الأسعار',
        'benefits' => 'المزايا',
        'settings' => 'الإعدادات',
    ],

    'fields' => [
        'name' => 'الاسم',
        'name_en' => 'الاسم (إنجليزي)',
        'name_ar' => 'الاسم (عربي)',
        'description' => 'الوصف',
        'description_en' => 'الوصف (إنجليزي)',
        'description_ar' => 'الوصف (عربي)',
        'tier' => 'المستوى',
        'price_monthly' => 'السعر الشهري',
        'price_yearly' => 'السعر السنوي',
        'monthly' => 'شهري',
        'yearly' => 'سنوي',
        'discount_percentage' => 'نسبة الخصم',
        'discount' => 'الخصم',
        'included_sessions' => 'الجلسات المتضمنة شهرياً',
        'treatment_id' => 'معرف العلاج',
        'sessions_per_month' => 'الجلسات شهرياً',
        'loyalty_multiplier' => 'مضاعف نقاط الولاء',
        'loyalty' => 'الولاء',
        'priority_booking' => 'أولوية الحجز',
        'priority' => 'الأولوية',
        'is_active' => 'نشط',
        'active' => 'نشط',
        'sort_order' => 'ترتيب العرض',
        'members' => 'الأعضاء النشطون',
        'patient' => 'المريض',
        'status' => 'الحالة',
        'billing_cycle' => 'دورة الفوترة',
        'started_at' => 'تاريخ البدء',
        'expires_at' => 'تاريخ الانتهاء',
        'days_remaining' => 'الأيام المتبقية',
        'auto_renew' => 'تجديد تلقائي',
        'notes' => 'ملاحظات',
        'cancellation_reason' => 'سبب الإلغاء',
    ],

    'tiers' => [
        'silver' => 'فضي',
        'gold' => 'ذهبي',
        'platinum' => 'بلاتيني',
        'diamond' => 'ماسي',
    ],

    'statuses' => [
        'active' => 'نشط',
        'expired' => 'منتهي',
        'cancelled' => 'ملغي',
        'frozen' => 'مجمد',
    ],

    'billing_cycles' => [
        'monthly' => 'شهري',
        'yearly' => 'سنوي',
    ],

    'actions' => [
        'add_session' => 'إضافة ميزة جلسات',
        'freeze' => 'تجميد',
        'unfreeze' => 'إلغاء التجميد',
        'renew' => 'تجديد',
        'cancel' => 'إلغاء',
    ],

    'messages' => [
        'frozen' => 'تم تجميد العضوية بنجاح',
        'unfrozen' => 'تم إلغاء تجميد العضوية بنجاح',
        'renewed' => 'تم تجديد العضوية بنجاح',
        'cancelled' => 'تم إلغاء العضوية بنجاح',
    ],
];
