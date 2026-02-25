<?php

return [
    // Navigation
    'navigation_label' => 'قواعد الحجز',
    'navigation_group' => 'إعدادات الحجز',

    // Resource
    'label' => 'قاعدة حجز',
    'plural_label' => 'قواعد الحجز',

    // Fields
    'fields' => [
        'name' => 'اسم القاعدة',
        'code' => 'رمز القاعدة',
        'description' => 'الوصف',
        'scope_level' => 'مستوى النطاق',
        'branch' => 'الفرع',
        'service' => 'الخدمة',
        'rule_type' => 'نوع القاعدة',
        'priority' => 'الأولوية',
        'is_active' => 'نشطة',
        'conditions' => 'الشروط',
        'actions' => 'الإجراءات',
    ],

    // Scope Levels
    'scope_levels' => [
        'tenant' => 'كل الفروع',
        'branch' => 'فرع محدد',
        'service' => 'خدمة محددة',
    ],

    // Rule Types
    'rule_types' => [
        'slot_block' => 'حظر المواعيد',
        'time_restriction' => 'تقييد الوقت',
        'capacity_limit' => 'حد السعة',
        'buffer_override' => 'تجاوز الفاصل',
        'advance_booking' => 'الحجز المسبق',
        'online_restriction' => 'تقييد الحجز عبر الإنترنت',
        'practitioner_limit' => 'حد الممارس',
    ],

    // Rule Type Descriptions
    'rule_type_descriptions' => [
        'slot_block' => 'حظر جميع المواعيد خلال الشروط المحددة',
        'time_restriction' => 'تقييد ساعات العمل المتاحة',
        'capacity_limit' => 'تحديد الحد الأقصى للمواعيد',
        'buffer_override' => 'تجاوز الوقت الفاصل بين المواعيد',
        'advance_booking' => 'تجاوز الحد الأدنى/الأقصى لوقت الحجز المسبق',
        'online_restriction' => 'تقييد الحجز عبر الإنترنت فقط',
        'practitioner_limit' => 'تحديد ممارسين محددين',
    ],

    // Conditions
    'conditions' => [
        'days_of_week' => 'أيام الأسبوع',
        'time_range' => 'النطاق الزمني',
        'date_range' => 'نطاق التاريخ',
        'services' => 'الخدمات',
        'practitioners' => 'الممارسين',
        'start_time' => 'وقت البداية',
        'end_time' => 'وقت النهاية',
        'start_date' => 'تاريخ البداية',
        'end_date' => 'تاريخ النهاية',
    ],

    // Actions
    'action_fields' => [
        'block' => 'حظر المواعيد',
        'reason' => 'السبب',
        'allowed_start' => 'وقت البداية المسموح',
        'allowed_end' => 'وقت النهاية المسموح',
        'max_appointments' => 'الحد الأقصى للمواعيد',
        'scope' => 'لكل',
        'buffer_minutes' => 'الفاصل بالدقائق',
        'min_hours' => 'الحد الأدنى بالساعات',
        'max_days' => 'الحد الأقصى بالأيام',
        'allow' => 'السماح بالحجز عبر الإنترنت',
    ],

    // Scope Options
    'scope_options' => [
        'day' => 'لكل يوم',
        'practitioner' => 'لكل ممارس',
    ],

    // Days
    'days' => [
        '0' => 'الأحد',
        '1' => 'الإثنين',
        '2' => 'الثلاثاء',
        '3' => 'الأربعاء',
        '4' => 'الخميس',
        '5' => 'الجمعة',
        '6' => 'السبت',
    ],

    // Placeholders
    'placeholders' => [
        'name' => 'مثال: استراحة الغداء',
        'code' => 'مثال: LUNCH_BREAK',
        'reason' => 'مثال: استراحة الموظفين',
    ],

    // Helper Texts
    'helper_texts' => [
        'code' => 'معرف فريد لهذه القاعدة',
        'priority' => 'يتم تقييم القواعد ذات الأولوية الأعلى أولاً (0-100)',
        'conditions_days' => 'اتركه فارغاً للتطبيق على جميع الأيام',
        'conditions_services' => 'اتركه فارغاً للتطبيق على جميع الخدمات',
    ],

    // Messages
    'messages' => [
        'created' => 'تم إنشاء قاعدة الحجز بنجاح',
        'updated' => 'تم تحديث قاعدة الحجز بنجاح',
        'deleted' => 'تم حذف قاعدة الحجز بنجاح',
    ],
];
