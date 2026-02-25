<?php

return [
    // Navigation
    'navigation_label' => 'تواريخ الحظر',
    'navigation_group' => 'إعدادات الحجز',

    // Resource
    'label' => 'تاريخ حظر',
    'plural_label' => 'تواريخ الحظر',

    // Fields
    'fields' => [
        'name' => 'الاسم',
        'branch' => 'الفرع',
        'start_date' => 'تاريخ البداية',
        'end_date' => 'تاريخ النهاية',
        'is_recurring' => 'متكرر',
        'recurrence_type' => 'نوع التكرار',
        'affects_online_booking' => 'يؤثر على الحجز عبر الإنترنت',
        'affects_staff_booking' => 'يؤثر على حجز الموظفين',
        'reason' => 'السبب',
        'is_active' => 'نشط',
    ],

    // Labels
    'labels' => [
        'all_branches' => 'جميع الفروع',
        'single_day' => 'يوم واحد',
        'date_range' => 'نطاق تواريخ',
        'upcoming' => 'قادم',
        'past' => 'ماضي',
    ],

    // Recurrence Types
    'recurrence_types' => [
        'yearly' => 'سنوياً',
        'monthly' => 'شهرياً',
    ],

    // Helper Texts
    'helper_texts' => [
        'branch' => 'اتركه فارغاً للتطبيق على جميع الفروع',
        'affects_online' => 'حظر الحجز عبر الإنترنت خلال هذه الفترة',
        'affects_staff' => 'أيضاً منع الموظفين من إنشاء الحجوزات',
        'recurring' => 'تكرار تاريخ الحظر هذا كل سنة/شهر',
    ],

    // Placeholders
    'placeholders' => [
        'name' => 'مثال: عيد الفطر، الصيانة السنوية',
        'reason' => 'مثال: عطلة وطنية، العيادة مغلقة للصيانة',
    ],

    // Messages
    'messages' => [
        'created' => 'تم إنشاء تاريخ الحظر بنجاح',
        'updated' => 'تم تحديث تاريخ الحظر بنجاح',
        'deleted' => 'تم حذف تاريخ الحظر بنجاح',
    ],

    // Table
    'table' => [
        'date' => 'التاريخ',
        'dates' => ':start إلى :end',
        'single' => ':date',
        'recurring_yearly' => 'يتكرر سنوياً',
        'recurring_monthly' => 'يتكرر شهرياً',
        'online_only' => 'الإنترنت فقط',
        'all_booking' => 'كل الحجوزات',
    ],
];
