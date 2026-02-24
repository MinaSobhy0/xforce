<?php

return [
    // Navigation
    'equipment' => 'المعدات',
    'equipment_item' => 'جهاز',
    'equipment_types' => 'أنواع المعدات',
    'equipment_type' => 'نوع المعدات',

    // Fields
    'name' => 'الاسم',
    'code' => 'الكود',
    'type' => 'النوع',
    'category' => 'الفئة',
    'manufacturer' => 'الشركة المصنعة',
    'model' => 'الموديل',
    'branch' => 'الفرع',
    'room' => 'الغرفة',
    'serial_number' => 'الرقم التسلسلي',
    'specifications' => 'المواصفات',
    'image' => 'الصورة',
    'active' => 'نشط',
    'units' => 'الوحدات',

    // Status
    'status' => 'الحالة',
    'status_active' => 'نشط',
    'status_maintenance' => 'تحت الصيانة',
    'status_out_of_service' => 'خارج الخدمة',
    'status_retired' => 'متقاعد',

    // Purchase & Depreciation
    'purchase_info' => 'معلومات الشراء',
    'purchase_date' => 'تاريخ الشراء',
    'purchase_price' => 'سعر الشراء',
    'warranty_expiry' => 'انتهاء الضمان',
    'depreciation_years' => 'سنوات الإهلاك',
    'depreciated_value' => 'القيمة المُهلكة',

    // Shots
    'shot_counter' => 'عداد الطلقات',
    'max_shots' => 'الحد الأقصى',
    'total_shots' => 'إجمالي الطلقات',
    'shots' => 'طلقات',
    'shots_remaining' => 'المتبقي',
    'shots_count' => 'عدد الطلقات',
    'record_shots' => 'تسجيل طلقات',
    'energy_setting' => 'إعداد الطاقة',
    'energy' => 'الطاقة',
    'spot_size' => 'حجم البقعة',
    'pulse_duration' => 'مدة النبضة',
    'pulse' => 'النبضة',
    'logged_at' => 'تاريخ التسجيل',

    // Maintenance
    'maintenance' => 'الصيانة',
    'maintenance_due' => 'موعد الصيانة',
    'last_maintenance' => 'آخر صيانة',
    'next_maintenance' => 'الصيانة القادمة',
    'log_maintenance' => 'تسجيل صيانة',
    'maintenance_type' => 'النوع',
    'description' => 'الوصف',
    'performed_by' => 'تم بواسطة',
    'performed_at' => 'تاريخ التنفيذ',
    'cost' => 'التكلفة',
    'next_due_date' => 'الموعد القادم',
    'next_due' => 'القادم',
    'parts_replaced' => 'القطع المُستبدلة',

    // Other
    'details' => 'التفاصيل',
    'notes' => 'ملاحظات',
    'specifications' => 'المواصفات',
    'spec_name' => 'المواصفة',
    'spec_value' => 'القيمة',
    'add_spec' => 'إضافة مواصفة',
    'image' => 'الصورة',
    'auto_generated' => 'يتم إنشاؤه تلقائياً',
    'price_help' => 'أدخل السعر بالقروش',
    'years' => 'سنوات',
    'max_shots_help' => 'اتركه فارغاً إذا لم يكن قابلاً للتطبيق',

    // Tracking
    'tracking_enabled' => 'تفعيل تتبع المعايير',
    'tracking_enabled_help' => 'تفعيل تتبع المعايير المخصصة أثناء جلسات العلاج',

    // Navigation for templates
    'navigation' => [
        'parameter_templates' => 'قوالب معايير المعدات',
    ],

    // Labels
    'labels' => [
        'parameter_template' => 'قالب المعايير',
        'parameter_templates' => 'قوالب المعايير',
    ],

    // Template
    'template' => [
        'info' => 'معلومات القالب',
        'name' => 'اسم القالب',
        'code' => 'كود القالب',
        'code_help' => 'كود فريد لهذا القالب (مثال: DIODE_LASER)',
        'category' => 'فئة المعدات',
        'category_help' => 'فئة المعدات التي ينطبق عليها هذا القالب',
        'parameters' => 'المعايير',
        'parameters_desc' => 'حدد المعايير التي سيتم جمعها أثناء جلسات العلاج',
        'parameters_count' => 'معايير',
        'equipment_using' => 'المعدات المستخدمة',
        'system' => 'نظام',
        'updated' => 'التحديث',
        'duplicate' => 'نسخ',
        'new_parameter' => 'معيار جديد',
        'select' => 'قالب المعايير',
        'select_help' => 'اختر قالب لاستخدام معايير محددة مسبقاً',
        'none' => 'بدون قالب (معايير مخصصة)',
        'apply' => 'تطبيق القالب',
        'apply_confirm' => 'سيتم استبدال المعايير الحالية من القالب. متابعة؟',
        'applied' => 'تم تطبيق معايير القالب بنجاح',

        // Type labels
        'types' => [
            'text' => 'نص',
            'number' => 'رقم',
            'decimal' => 'عشري',
            'select' => 'قائمة منسدلة',
            'boolean' => 'نعم/لا',
            'textarea' => 'نص طويل',
        ],

        // Category labels
        'categories' => [
            'energy' => 'إعدادات الطاقة',
            'timing' => 'التوقيت/النبضة',
            'spot' => 'البقعة/المنطقة',
            'cooling' => 'التبريد',
            'other' => 'أخرى',
        ],

        // Label fields
        'label_en' => 'التسمية (الإنجليزية)',
        'label_ar' => 'التسمية (العربية)',
        'help_en' => 'نص المساعدة (الإنجليزية)',
        'help_ar' => 'نص المساعدة (العربية)',
        'unit_help' => 'مثال: nm, J/cm², ms, %',

        // Source labels
        'source' => 'المصدر',
        'from_template' => 'من قالب',
        'manual' => 'يدوي',

        // Import actions
        'import' => 'استيراد من قالب',
        'import_confirm' => 'سيتم إضافة المعايير من القالب المحدد. متابعة؟',
    ],

    // Parameters
    'parameters' => [
        'title' => 'معايير التتبع',
        'basic_info' => 'المعلومات الأساسية',
        'value_config' => 'إعدادات القيمة',
        'tracking_config' => 'إعدادات التتبع',

        'key' => 'مفتاح المعيار',
        'key_help' => 'معرف فريد (مثال: fluence, pulse_width)',
        'name' => 'اسم العرض',
        'value_type' => 'نوع القيمة',
        'unit' => 'الوحدة',
        'category' => 'الفئة',
        'description' => 'الوصف',
        'description_help' => 'نص المساعدة المعروض للموظفين أثناء الإدخال',

        'min_value' => 'الحد الأدنى',
        'max_value' => 'الحد الأقصى',
        'default_value' => 'القيمة الافتراضية',
        'step' => 'الخطوة',
        'step_help' => 'خطوة الزيادة لحقول الأرقام',
        'options' => 'الخيارات',
        'option_value' => 'القيمة',
        'option_label' => 'التسمية',
        'add_option' => 'إضافة خيار',

        'is_required' => 'مطلوب',
        'is_required_help' => 'يجب ملؤه لإكمال الجلسة',
        'is_cumulative' => 'تراكمي',
        'is_cumulative_help' => 'القيم تتراكم أثناء الجلسة',
        'track_in_session' => 'تتبع في الجلسة',
        'track_in_session_help' => 'عرض في نموذج جلسة العلاج',
        'display_order' => 'ترتيب العرض',
        'is_active' => 'نشط',

        'type' => 'النوع',
        'required' => 'مطلوب',
        'track' => 'تتبع',
        'order' => 'الترتيب',
        'active' => 'نشط',
    ],
];
