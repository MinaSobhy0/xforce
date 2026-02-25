<?php

return [
    // Navigation
    'navigation_label' => 'إعدادات المواعيد',
    'title' => 'إعدادات مواعيد الحجز',

    // Sections
    'sections' => [
        'time_duration' => 'الوقت والمدة',
        'booking_restrictions' => 'قيود الحجز',
        'online_booking' => 'الحجز عبر الإنترنت',
        'capacity_limits' => 'حدود السعة',
        'active_rules' => 'القواعد النشطة',
        'slot_preview' => 'معاينة المواعيد',
        'algorithm_visualization' => 'كيفية إنشاء المواعيد',
    ],

    // Fields - Time & Duration
    'fields' => [
        'default_slot_duration' => 'مدة الموعد الافتراضية',
        'slot_interval_minutes' => 'الفاصل الزمني',
        'buffer_minutes' => 'الفاصل بين المواعيد',
        'default_start_time' => 'بداية ساعات العمل',
        'default_end_time' => 'نهاية ساعات العمل',

        // Booking Restrictions
        'min_advance_hours' => 'الحد الأدنى للحجز المسبق',
        'max_advance_booking_days' => 'الحد الأقصى للحجز المسبق',
        'cancellation_policy_hours' => 'مهلة الإلغاء المطلوبة',
        'allow_same_day_booking' => 'السماح بالحجز في نفس اليوم',

        // Online Booking
        'allow_online_booking' => 'تفعيل الحجز عبر الإنترنت',
        'auto_confirm_appointments' => 'تأكيد المواعيد تلقائياً',
        'show_practitioner_selection' => 'السماح للمرضى باختيار الممارس',
        'require_deposit' => 'طلب عربون للحجز عبر الإنترنت',
        'deposit_percentage' => 'نسبة العربون',

        // Capacity Limits
        'max_appointments_per_day' => 'الحد الأقصى للمواعيد يومياً',
        'max_appointments_per_practitioner' => 'الحد الأقصى لكل ممارس',
        'overbooking_limit' => 'حد الحجز الزائد',
    ],

    // Helper texts
    'helper_texts' => [
        'slot_interval' => 'إنشاء مواعيد كل X دقيقة. اتركه فارغاً لاستخدام مدة الخدمة.',
        'min_advance' => 'يجب على المرضى الحجز قبل هذه المدة على الأقل',
        'max_advance' => 'أقصى مدة يمكن للمرضى الحجز مسبقاً',
        'auto_confirm' => 'تخطي خطوة التأكيد اليدوي',
        'overbooking' => 'السماح بالحجز بما يتجاوز السعة العادية',
    ],

    // Units
    'units' => [
        'minutes' => 'دقيقة',
        'hours' => 'ساعة',
        'days' => 'يوم',
        'percent' => '%',
    ],

    // Actions
    'actions' => [
        'save' => 'حفظ الإعدادات',
        'reset' => 'إعادة تعيين',
        'preview' => 'معاينة',
        'generate_preview' => 'إنشاء معاينة',
    ],

    // Messages
    'messages' => [
        'settings_saved' => 'تم حفظ إعدادات المواعيد بنجاح',
        'preview_generated' => 'تم إنشاء المعاينة',
        'no_slots_available' => 'لا توجد مواعيد متاحة للمعايير المحددة',
    ],

    // Algorithm Steps
    'algorithm' => [
        'step1_service' => 'تحميل الخدمة',
        'step1_desc' => 'المدة، الفاصل، القيود',
        'step2_validate' => 'التحقق من التاريخ',
        'step2_desc' => 'الأيام المسموحة، التواريخ المحظورة',
        'step3_time_range' => 'الحصول على النطاق الزمني',
        'step3_desc' => 'خاص بالخدمة أو الافتراضي',
        'step4_intervals' => 'إنشاء الفترات',
        'step4_desc' => 'بناءً على المدة + الفاصل',
        'step5_filters' => 'تطبيق المرشحات',
        'step5_filter1' => 'جدول الممارس',
        'step5_filter2' => 'الإجازات',
        'step5_filter3' => 'المواعيد الحالية',
        'step5_filter4' => 'توفر الغرف',
        'step5_filter5' => 'توفر المعدات',
        'step5_filter6' => 'قواعد الحجز',
    ],

    // Quick Stats
    'stats' => [
        'active_rules' => 'القواعد النشطة',
        'blackout_dates' => 'تواريخ الحظر',
        'default_duration' => 'المدة الافتراضية',
        'max_advance' => 'أقصى حجز مسبق',
    ],
];
