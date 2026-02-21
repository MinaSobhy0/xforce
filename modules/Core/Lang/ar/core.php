<?php

return [
    // Module info
    'module_name' => 'النظام الأساسي',
    'module_description' => 'وظائف النظام الأساسية بما في ذلك إدارة المستأجرين وإعدادات النظام',

    // Tenants
    'tenant' => 'مستأجر',
    'tenants' => 'المستأجرين',
    'tenant_name' => 'اسم المستأجر',
    'tenant_slug' => 'المعرف',
    'tenant_status' => 'الحالة',
    'tenant_plan' => 'خطة الاشتراك',
    'tenant_created' => 'تاريخ الإنشاء',
    'tenant_trial_ends' => 'انتهاء الفترة التجريبية',

    // Tenant Statuses
    'status_active' => 'نشط',
    'status_inactive' => 'غير نشط',
    'status_suspended' => 'موقوف',
    'status_trial' => 'تجريبي',

    // Settings
    'settings' => 'الإعدادات',
    'system_settings' => 'إعدادات النظام',
    'general_settings' => 'الإعدادات العامة',
    'save_settings' => 'حفظ الإعدادات',
    'settings_saved' => 'تم حفظ الإعدادات بنجاح',

    // Modules
    'modules' => 'الوحدات',
    'module_management' => 'إدارة الوحدات',
    'activate_module' => 'تفعيل الوحدة',
    'deactivate_module' => 'إلغاء تفعيل الوحدة',
    'module_activated' => 'تم تفعيل الوحدة بنجاح',
    'module_deactivated' => 'تم إلغاء تفعيل الوحدة بنجاح',
    'module_required_dependency' => 'لا يمكن إلغاء التفعيل: وحدات أخرى تعتمد على هذه الوحدة',

    // Actions
    'create' => 'إنشاء',
    'edit' => 'تعديل',
    'delete' => 'حذف',
    'view' => 'عرض',
    'save' => 'حفظ',
    'cancel' => 'إلغاء',
    'confirm' => 'تأكيد',
    'search' => 'بحث',
    'filter' => 'تصفية',
    'export' => 'تصدير',
    'import' => 'استيراد',

    // Common
    'name' => 'الاسم',
    'description' => 'الوصف',
    'status' => 'الحالة',
    'actions' => 'الإجراءات',
    'created_at' => 'تاريخ الإنشاء',
    'updated_at' => 'تاريخ التحديث',
    'yes' => 'نعم',
    'no' => 'لا',
    'all' => 'الكل',
    'none' => 'لا شيء',
    'general' => 'عام',
    'minutes' => 'دقائق',
    'hours' => 'ساعات',
    'days' => 'أيام',

    // Branches
    'branch' => 'فرع',
    'branches' => 'الفروع',
    'branch_details' => 'تفاصيل الفرع',
    'main_branch' => 'الفرع الرئيسي',
    'main_branch_help' => 'هذا هو الفرع الرئيسي للعيادة',
    'main' => 'رئيسي',
    'contact_information' => 'معلومات الاتصال',
    'address' => 'العنوان',
    'city' => 'المدينة',
    'phone' => 'الهاتف',
    'email' => 'البريد الإلكتروني',
    'google_maps_url' => 'رابط خرائط جوجل',
    'working_hours' => 'ساعات العمل',
    'day' => 'اليوم',
    'open_time' => 'وقت الفتح',
    'close_time' => 'وقت الإغلاق',
    'closed' => 'مغلق',
    'timezone' => 'المنطقة الزمنية',
    'currency' => 'العملة',
    'cannot_delete_main_branch' => 'لا يمكن حذف الفرع الرئيسي',
    'staff' => 'الموظفين',

    // Days of week
    'days_of_week' => [
        'sunday' => 'الأحد',
        'monday' => 'الإثنين',
        'tuesday' => 'الثلاثاء',
        'wednesday' => 'الأربعاء',
        'thursday' => 'الخميس',
        'friday' => 'الجمعة',
        'saturday' => 'السبت',
    ],

    // Rooms
    'room' => 'غرفة',
    'rooms' => 'الغرف',
    'room_details' => 'تفاصيل الغرفة',
    'room_type' => 'نوع الغرفة',
    'room_types' => [
        'treatment' => 'غرفة علاج',
        'consultation' => 'غرفة استشارة',
        'waiting' => 'منطقة الانتظار',
        'reception' => 'الاستقبال',
        'storage' => 'مخزن',
        'staff' => 'غرفة الموظفين',
        'other' => 'أخرى',
    ],
    'capacity' => 'السعة',
    'floor' => 'الطابق',
    'color' => 'اللون',
    'code' => 'الرمز',
    'active' => 'نشط',
    'bookable' => 'قابل للحجز',
    'bookable_help' => 'هل يمكن استخدام هذه الغرفة للمواعيد؟',
    'sort_order' => 'ترتيب العرض',
    'additional_settings' => 'إعدادات إضافية',
    'setting_key' => 'مفتاح الإعداد',
    'setting_value' => 'قيمة الإعداد',

    // Messages
    'confirm_delete' => 'هل أنت متأكد من رغبتك في الحذف؟',
    'record_created' => 'تم إنشاء السجل بنجاح',
    'record_updated' => 'تم تحديث السجل بنجاح',
    'record_deleted' => 'تم حذف السجل بنجاح',
    'error_occurred' => 'حدث خطأ',

    // Dashboard Widgets
    'widgets' => [
        'total_patients' => 'إجمالي المرضى',
        'this_month' => 'هذا الشهر',
        'todays_appointments' => 'مواعيد اليوم',
        'upcoming' => 'قادم',
        'monthly_revenue' => 'إيرادات الشهر',
        'vs_last_month' => 'مقارنة بالشهر الماضي',
        'outstanding_balance' => 'الرصيد المستحق',
        'pending_payments' => 'مدفوعات معلقة',
    ],
];
