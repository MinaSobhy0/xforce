<?php

return [
    // Navigation & Labels
    'navigation_label' => 'خطط العلاج',
    'model_label' => 'خطة العلاج',
    'plural_label' => 'خطط العلاج',

    // Sections
    'sections' => [
        'basic_info' => 'المعلومات الأساسية',
        'plan_details' => 'تفاصيل الخطة',
        'dates' => 'التواريخ',
        'package_info' => 'معلومات الباقة',
        'services' => 'الخدمات',
        'progress' => 'التقدم',
        'notes' => 'الملاحظات',
        'appointments' => 'المواعيد',
        'financials' => 'الملخص المالي',
    ],

    // Fields
    'fields' => [
        'code' => 'الرمز',
        'name' => 'الاسم',
        'name_en' => 'الاسم (بالإنجليزية)',
        'name_ar' => 'الاسم (بالعربية)',
        'description' => 'الوصف',
        'description_en' => 'الوصف (بالإنجليزية)',
        'description_ar' => 'الوصف (بالعربية)',
        'patient' => 'المريض',
        'branch' => 'الفرع',
        'status' => 'الحالة',
        'source' => 'المصدر',
        'start_date' => 'تاريخ البدء',
        'target_end_date' => 'تاريخ الانتهاء المتوقع',
        'actual_end_date' => 'تاريخ الانتهاء الفعلي',
        'recommended_package' => 'الباقة الموصى بها',
        'package_subscription' => 'اشتراك الباقة',
        'notes' => 'ملاحظات',
        'internal_notes' => 'ملاحظات داخلية',
        'created_by' => 'أنشئ بواسطة',
        'created_at' => 'تاريخ الإنشاء',
        'activated_at' => 'تاريخ التفعيل',
        'completed_at' => 'تاريخ الإكمال',
        'cancelled_at' => 'تاريخ الإلغاء',
        'cancellation_reason' => 'سبب الإلغاء',

        // Item fields
        'service' => 'الخدمة',
        'recommended_sessions' => 'الجلسات الموصى بها',
        'completed_sessions' => 'الجلسات المكتملة',
        'remaining_sessions' => 'الجلسات المتبقية',
        'session_interval_days' => 'الفترة بين الجلسات (أيام)',
        'preferred_practitioner' => 'الممارس المفضل',
        'preferred_day_of_week' => 'الأيام المفضلة',
        'preferred_time_slot' => 'الوقت المفضل',
        'sort_order' => 'ترتيب العرض',

        // Appointment fields
        'appointment' => 'الموعد',
        'session_number' => 'رقم الجلسة',
        'date' => 'التاريخ',
        'time' => 'الوقت',
        'practitioner' => 'الممارس',
    ],

    // Statuses
    'statuses' => [
        'draft' => 'مسودة',
        'active' => 'نشط',
        'paused' => 'متوقف مؤقتاً',
        'completed' => 'مكتمل',
        'cancelled' => 'ملغي',
    ],

    // Item statuses
    'item_statuses' => [
        'pending' => 'في الانتظار',
        'in_progress' => 'قيد التنفيذ',
        'completed' => 'مكتمل',
        'cancelled' => 'ملغي',
    ],

    // Sources
    'sources' => [
        'manual' => 'إدخال يدوي',
        'consultation' => 'من الاستشارة',
    ],

    // Time slots
    'time_slots' => [
        'morning' => 'صباحاً (9 ص - 12 م)',
        'afternoon' => 'بعد الظهر (12 م - 5 م)',
        'evening' => 'مساءً (5 م - 9 م)',
    ],

    // Days of week
    'days_of_week' => [
        0 => 'الأحد',
        1 => 'الاثنين',
        2 => 'الثلاثاء',
        3 => 'الأربعاء',
        4 => 'الخميس',
        5 => 'الجمعة',
        6 => 'السبت',
    ],

    // Actions
    'actions' => [
        'create' => 'إنشاء خطة علاج',
        'edit' => 'تعديل خطة العلاج',
        'view' => 'عرض خطة العلاج',
        'delete' => 'حذف خطة العلاج',
        'activate' => 'تفعيل',
        'pause' => 'إيقاف مؤقت',
        'resume' => 'استئناف',
        'complete' => 'تعليم كمكتمل',
        'cancel' => 'إلغاء',
        'add_item' => 'إضافة خدمة',
        'remove_item' => 'إزالة الخدمة',
        'book_appointment' => 'حجز موعد',
        'buy_package' => 'شراء الباقة',
        'link_package' => 'ربط الباقة',
        'collect_deposit' => 'تحصيل عربون',
        'generate_invoice' => 'إنشاء فاتورة',
        'view_invoice' => 'عرض الفاتورة',
    ],

    // Messages
    'messages' => [
        'created' => 'تم إنشاء خطة العلاج بنجاح.',
        'updated' => 'تم تحديث خطة العلاج بنجاح.',
        'deleted' => 'تم حذف خطة العلاج بنجاح.',
        'activated' => 'تم تفعيل خطة العلاج بنجاح.',
        'paused' => 'تم إيقاف خطة العلاج مؤقتاً بنجاح.',
        'resumed' => 'تم استئناف خطة العلاج بنجاح.',
        'completed' => 'تم تعليم خطة العلاج كمكتملة.',
        'cancelled' => 'تم إلغاء خطة العلاج.',
        'item_added' => 'تم إضافة الخدمة إلى خطة العلاج.',
        'item_removed' => 'تم إزالة الخدمة من خطة العلاج.',
        'package_linked' => 'تم ربط اشتراك الباقة بخطة العلاج.',
        'cannot_edit' => 'لا يمكن تعديل خطة العلاج هذه.',
        'cannot_transition' => 'لا يمكن تغيير الحالة إلى :status.',
        'deposit_collected' => 'تم تحصيل العربون بنجاح.',
        'deposit_amount' => 'المبلغ: :amount',
        'invoice_generated' => 'تم إنشاء الفاتورة بنجاح.',
        'invoice_code' => 'رقم الفاتورة: :code',
    ],

    // Progress
    'progress' => [
        'overall' => 'التقدم العام',
        'sessions_completed' => ':completed من :total جلسات مكتملة',
        'percentage' => ':percent% مكتمل',
        'days_remaining' => ':days يوم متبقي',
        'overdue' => 'متأخر بـ :days يوم',
        'items_needing_scheduling' => ':count خدمات تحتاج جدولة',
    ],

    // Widgets & Stats
    'stats' => [
        'total_plans' => 'إجمالي الخطط',
        'active_plans' => 'الخطط النشطة',
        'completed_plans' => 'الخطط المكتملة',
        'completion_rate' => 'معدل الإكمال',
    ],

    // Package section
    'package' => [
        'recommended' => 'الباقة الموصى بها',
        'not_purchased' => 'غير مشتراة',
        'purchased' => 'مشتراة',
        'sessions_remaining' => ':count جلسات متبقية',
        'save_amount' => 'وفر :amount',
        'buy_now' => 'شراء الباقة',
        'no_recommendation' => 'لا توجد باقة موصى بها لهذه الخطة.',
    ],

    // Relation managers
    'items' => [
        'title' => 'خدمات الخطة',
        'empty' => 'لم تتم إضافة خدمات إلى هذه الخطة بعد.',
        'add' => 'إضافة خدمة',
    ],

    'plan_appointments' => [
        'title' => 'مواعيد الخطة',
        'empty' => 'لم تتم جدولة أي مواعيد بعد.',
        'add' => 'جدولة موعد',
    ],

    // Filters
    'filters' => [
        'status' => 'الحالة',
        'patient' => 'المريض',
        'branch' => 'الفرع',
        'date_range' => 'نطاق التاريخ',
        'created_by' => 'أنشئ بواسطة',
        'has_package' => 'لديه باقة',
    ],

    // Tabs
    'tabs' => [
        'overview' => 'نظرة عامة',
        'services' => 'الخدمات',
        'appointments' => 'المواعيد',
        'history' => 'السجل',
    ],

    // Tooltips
    'tooltips' => [
        'session_interval' => 'الأيام الموصى بها بين الجلسات',
        'preferred_time' => 'وقت الموعد المفضل للمريض',
        'progress_bar' => 'التقدم نحو إكمال جميع الجلسات',
    ],

    // Confirmations
    'confirmations' => [
        'activate' => 'هل أنت متأكد أنك تريد تفعيل خطة العلاج هذه؟',
        'cancel' => 'هل أنت متأكد أنك تريد إلغاء خطة العلاج هذه؟ لا يمكن التراجع عن هذا الإجراء.',
        'complete' => 'هل أنت متأكد أنك تريد تعليم خطة العلاج هذه كمكتملة؟',
        'delete' => 'هل أنت متأكد أنك تريد حذف خطة العلاج هذه؟',
    ],

    // Financials
    'financials' => [
        'total_value' => 'القيمة الإجمالية',
        'deposits' => 'العرابين',
        'invoiced' => 'المفوتر',
        'paid' => 'المدفوع',
        'balance' => 'الرصيد',
        'amount' => 'المبلغ',
        'payment_method' => 'طريقة الدفع',
        'reference' => 'رقم المرجع',
        'due_date' => 'تاريخ الاستحقاق',
        'items_to_invoice' => 'البنود للفوترة',
        'apply_deposits' => 'تطبيق العرابين المتاحة',
        'available_deposits' => 'العرابين المتاحة: :amount',
        'to_invoice' => 'للفوترة',
    ],
];
