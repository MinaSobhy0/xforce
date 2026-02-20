<?php

return [
    'navigation' => 'قائمة الانتظار',
    'singular' => 'عنصر انتظار',
    'plural' => 'قائمة الانتظار',

    'sections' => [
        'patient' => 'المريض',
        'treatment' => 'تفاصيل العلاج',
        'preferences' => 'التفضيلات',
        'notes' => 'ملاحظات',
    ],

    'fields' => [
        'patient' => 'المريض',
        'treatment' => 'العلاج',
        'branch' => 'الفرع',
        'practitioner' => 'المختص المفضل',
        'practitioner_help' => 'اتركه فارغاً لأي مختص متاح',
        'preferred_days' => 'الأيام المفضلة',
        'preferred_times' => 'الأوقات المفضلة',
        'priority' => 'الأولوية',
        'status' => 'الحالة',
        'notes' => 'ملاحظات',
        'notified_at' => 'تاريخ الإشعار',
        'expires_at' => 'تاريخ الانتهاء',
        'expires_at_help' => 'اتركه فارغاً لعدم وجود انتهاء',
        'created_at' => 'تاريخ الإضافة',
    ],

    'filters' => [
        'active_only' => 'النشطة فقط',
    ],

    'actions' => [
        'notify' => 'تمييز كمُشعَر',
        'book' => 'إنشاء موعد',
        'mark_booked' => 'تمييز كمحجوز',
        'cancel' => 'إلغاء',
    ],

    'any_practitioner' => 'أي مختص',
];
