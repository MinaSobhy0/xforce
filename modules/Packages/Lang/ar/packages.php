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
        'patient_selection' => 'اختيار المريض',
        'package_selection' => 'اختيار الباقة',
        'package_details' => 'تفاصيل الباقة',
        'payment' => 'خيارات الدفع',
        'summary' => 'ملخص الطلب',
        'active_packages' => 'الباقات النشطة',
    ],

    'labels' => [
        'expires_in' => 'تنتهي خلال :days يوم',
        'balance_due' => 'الرصيد المستحق',
        'usage_progress' => 'تقدم الاستخدام',
        'sessions_used' => 'جلسة مستخدمة',
        'sessions_remaining' => 'جلسات متبقية',
        'sessions_consumed' => 'مستهلكة',
        'sessions_booked' => 'محجوزة',
        'sessions_available' => 'متاحة',
        'sessions' => 'جلسات',
        'consumed' => 'مستهلكة',
        'booked' => 'محجوزة',
        'pulses' => 'نبضات',
        'pulses_remaining' => 'نبضات متبقية',
        'pulses_consumed' => 'نبضات مستهلكة',
        'pulses_available' => 'نبضات متاحة',
    ],

    'pages' => [
        'sell_package' => 'بيع باقة',
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
        'package' => 'الباقة',
        'payment_option' => 'خيار الدفع',
        'deposit_amount' => 'مبلغ العربون',
        'min_deposit' => 'الحد الأدنى للعربون: :amount (:percent%)',
        'min_deposit_percent' => 'نسبة الحد الأدنى %',
        'activation_rule' => 'قاعدة التفعيل',
        'activation_rule_help' => 'متى يجب تفعيل الباقة؟',
        'consumption_type' => 'النوع',
        'unit_price' => 'سعر الوحدة',
        'line_total' => 'الإجمالي',
        'total_price' => 'إجمالي الباقة',
        'pulses_per_session' => 'نبضات/جلسة',
    ],

    'consumption_types' => [
        'sessions' => 'جلسات',
        'pulses' => 'نبضات',
    ],

    'payment_options' => [
        'full' => 'دفع المبلغ كاملاً الآن',
        'deposit' => 'دفع عربون الآن',
    ],

    'activation_rules' => [
        'immediate' => 'تفعيل فوري (يمكن استخدام الجلسات مع وجود رصيد متبقي)',
        'paid_in_full' => 'تفعيل بعد الدفع الكامل',
    ],

    'validation' => [
        'min_deposit' => 'الحد الأدنى للعربون هو :amount',
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
        'sell_package' => 'بيع الباقة',
        'book_session' => 'حجز جلسة',
    ],

    'notifications' => [
        'package_sold' => 'تم بيع الباقة بنجاح',
        'package_sold_body' => 'تم بيع :package إلى :patient',
        'error' => 'خطأ',
    ],

    'messages' => [
        'frozen' => 'تم تجميد الاشتراك بنجاح',
        'unfrozen' => 'تم إلغاء تجميد الاشتراك بنجاح',
        'cancelled' => 'تم إلغاء الاشتراك بنجاح',
        'session_used' => 'تم استخدام الجلسة بنجاح',
        'no_active_packages' => 'لا توجد باقات نشطة',
    ],

    'invoice_notes' => 'الباقة: :package - صالحة لمدة :days يوم',

    // Package Subscriptions Resource
    'subscriptions' => [
        'navigation_label' => 'الاشتراكات',
        'model_label' => 'اشتراك',
        'plural_label' => 'الاشتراكات',

        'sections' => [
            'details' => 'تفاصيل الاشتراك',
            'usage' => 'ملخص الاستخدام',
            'payment' => 'معلومات الدفع',
            'services' => 'خدمات الباقة',
        ],

        'fields' => [
            'usage' => 'الاستخدام',
            'balance' => 'الرصيد',
            'package_price' => 'سعر الباقة',
            'paid' => 'المدفوع',
            'payment_progress' => 'تقدم الدفع',
            'total_sessions' => 'الإجمالي',
            'used' => 'مستخدم',
            'booked' => 'محجوز',
            'remaining' => 'متبقي',
            'frozen_until' => 'تجميد حتى',
            'branch' => 'الفرع',
            'quantity_used' => 'الكمية',
            'unit_type' => 'النوع',
            'appointment_date' => 'تاريخ الموعد',
            'used_at' => 'تاريخ الاستخدام',
            'date' => 'التاريخ',
            'time' => 'الوقت',
            'practitioner' => 'الممارس',
            'is_package_session' => 'جلسة باقة',
        ],

        'filters' => [
            'expiring_soon' => 'تنتهي قريباً (30 يوم)',
            'has_balance' => 'لديه رصيد مستحق',
        ],

        'relations' => [
            'usages' => 'سجل استخدام الجلسات',
            'appointments' => 'المواعيد',
        ],

        'actions' => [
            'view_appointment' => 'عرض',
        ],
    ],
];
