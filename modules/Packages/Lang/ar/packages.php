<?php

return [
    'navigation_label' => 'الباقات',
    'model_label' => 'باقة',
    'plural_label' => 'الباقات',
    'packages' => 'الباقات',

    'sections' => [
        'basic_info' => 'معلومات الباقة',
        'items' => 'محتويات الباقة',
        'items_description' => 'أضف العلاجات والكميات المتضمنة في هذه الباقة',
    ],

    'fields' => [
        'name' => 'الاسم',
        'name_en' => 'الاسم (إنجليزي)',
        'name_ar' => 'الاسم (عربي)',
        'description' => 'الوصف',
        'description_en' => 'الوصف (إنجليزي)',
        'description_ar' => 'الوصف (عربي)',
        'type' => 'النوع',
        'price' => 'السعر',
        'validity_days' => 'فترة الصلاحية',
        'validity' => 'الصلاحية',
        'days' => 'يوم',
        'is_transferable' => 'قابل للتحويل',
        'is_active' => 'نشط',
        'active' => 'نشط',
        'sort_order' => 'ترتيب العرض',
        'treatment' => 'العلاج',
        'service' => 'الخدمة',
        'quantity' => 'الكمية',
        'treatments' => 'العلاجات',
        'treatments_suffix' => 'علاج',
        'services' => 'الخدمات',
        'services_suffix' => 'خدمة',
        'sessions' => 'الجلسات',
        'sessions_suffix' => 'جلسة',
        'sessions_used' => 'الجلسات المستخدمة',
        'subscriptions' => 'الاشتراكات النشطة',
        'created_at' => 'تاريخ الإنشاء',
        'patient' => 'المريض',
        'status' => 'الحالة',
        'purchased_at' => 'تاريخ الشراء',
        'expires_at' => 'تاريخ الانتهاء',
        'days_remaining' => 'الأيام المتبقية',
        'progress' => 'التقدم',
        'notes' => 'ملاحظات',
        'cancellation_reason' => 'سبب الإلغاء',
    ],

    'types' => [
        'session_bundle' => 'باقة جلسات',
        'value_bundle' => 'باقة قيمة',
    ],

    'statuses' => [
        'active' => 'نشط',
        'completed' => 'مكتمل',
        'expired' => 'منتهي',
        'cancelled' => 'ملغي',
        'frozen' => 'مجمد',
    ],

    'actions' => [
        'add_item' => 'إضافة علاج',
        'freeze' => 'تجميد',
        'unfreeze' => 'إلغاء التجميد',
        'cancel' => 'إلغاء',
        'use_session' => 'استخدام جلسة',
    ],

    'messages' => [
        'frozen' => 'تم تجميد الاشتراك بنجاح',
        'unfrozen' => 'تم إلغاء تجميد الاشتراك بنجاح',
        'cancelled' => 'تم إلغاء الاشتراك بنجاح',
        'session_used' => 'تم استخدام الجلسة بنجاح',
    ],
];
