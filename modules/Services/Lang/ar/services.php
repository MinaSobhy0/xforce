<?php

return [
    'module_name' => 'الخدمات',
    'module_description' => 'كتالوج الخدمات وإدارة نماذج الموافقة',

    'navigation' => [
        'services' => 'الخدمات',
        'categories' => 'الفئات',
        'consent_templates' => 'نماذج الموافقة',
    ],

    'labels' => [
        'service' => 'خدمة',
        'services' => 'الخدمات',
        'category' => 'الفئة',
        'categories' => 'الفئات',
        'consent_template' => 'نموذج الموافقة',
        'consent_templates' => 'نماذج الموافقة',
    ],

    'fields' => [
        'name' => 'الاسم',
        'description' => 'الوصف',
        'category' => 'الفئة',
        'parent_category' => 'الفئة الرئيسية',
        'duration' => 'المدة (دقائق)',
        'buffer_time' => 'وقت الفاصل (دقائق)',
        'base_price' => 'السعر الأساسي',
        'recommended_sessions' => 'عدد الجلسات الموصى به',
        'session_interval_days' => 'الفترة بين الجلسات (أيام)',
        'fitzpatrick_min' => 'أدنى نوع فيتزباتريك',
        'fitzpatrick_max' => 'أقصى نوع فيتزباتريك',
        'contraindications' => 'موانع الاستعمال',
        'pre_care' => 'تعليمات ما قبل الخدمة',
        'post_care' => 'تعليمات ما بعد الخدمة',
        'is_active' => 'نشط',
        'requires_consent' => 'يتطلب موافقة',
        'consent_template' => 'نموذج الموافقة',
        'equipment_required' => 'المعدات المطلوبة',
        'qualified_staff' => 'الموظفون المؤهلون',
        'service_rooms' => 'غرف الخدمة',
        'required_equipment' => 'المعدات المطلوبة',
        'allowed_days' => 'الأيام المسموح بها',
        'allowed_time_start' => 'وقت البدء',
        'allowed_time_end' => 'وقت الانتهاء',
        'min_advance_hours' => 'الحد الأدنى للحجز المسبق',
        'max_advance_days' => 'الحد الأقصى للحجز المسبق',
        'blackout_dates' => 'تواريخ الحظر',
        'blackout_dates_help' => 'أدخل التواريخ التي لا يمكن حجز هذه الخدمة فيها (التنسيق: YYYY-MM-DD)',
        'hours' => 'ساعات',
        'days' => 'أيام',
    ],

    'tabs' => [
        'scheduling' => 'الجدولة والموارد',
    ],

    'sections' => [
        'qualified_staff' => 'الموظفون المؤهلون',
        'qualified_staff_description' => 'الموظفون المؤهلون لتقديم هذه الخدمة',
        'rooms' => 'غرف الخدمة',
        'rooms_description' => 'الغرف التي يمكن تقديم هذه الخدمة فيها',
        'required_equipment' => 'المعدات المطلوبة',
        'required_equipment_description' => 'عناصر المعدات المحددة المطلوبة لهذه الخدمة',
        'time_restrictions' => 'قيود الفترات الزمنية',
        'time_restrictions_description' => 'تكوين متى يمكن حجز هذه الخدمة',
    ],

    'staff' => [
        'practitioner' => 'الممارس',
        'name' => 'الاسم',
        'job_title' => 'المسمى الوظيفي',
        'email' => 'البريد الإلكتروني',
        'phone' => 'الهاتف',
    ],

    'rooms' => [
        'room' => 'الغرفة',
        'branch' => 'الفرع',
        'is_primary' => 'الغرفة الرئيسية',
        'is_primary_help' => 'الغرفة المفضلة لهذه الخدمة',
        'priority' => 'الأولوية',
        'priority_help' => 'رقم أقل = أولوية أعلى للغرف الاحتياطية',
        'type' => 'نوع الغرفة',
        'set_as_primary' => 'تعيين كرئيسية',
        'primary_updated' => 'تم تحديث الغرفة الرئيسية بنجاح',
    ],

    'equipment' => [
        'equipment' => 'المعدات',
        'code' => 'الكود',
        'name' => 'الاسم',
        'type' => 'النوع',
        'branch' => 'الفرع',
        'status' => 'الحالة',
        'is_mandatory' => 'إلزامي',
        'is_mandatory_help' => 'إذا تم التفعيل، يجب أن تكون هذه المعدات متاحة لحجز الخدمة',
        'mark_optional' => 'تعيين كاختياري',
        'mark_mandatory' => 'تعيين كإلزامي',
    ],

    'consent' => [
        'name' => 'اسم النموذج',
        'content' => 'المحتوى',
        'version' => 'الإصدار',
        'is_active' => 'نشط',
        'valid_days' => 'صالح لمدة (أيام)',
    ],

    'pricing' => [
        'branch' => 'الفرع',
        'price' => 'السعر',
        'is_active' => 'نشط',
    ],

    'messages' => [
        'created' => 'تم إضافة الخدمة بنجاح.',
        'updated' => 'تم تحديث الخدمة بنجاح.',
        'deleted' => 'تم حذف الخدمة بنجاح.',
    ],

    'category_tree' => [
        'title' => 'شجرة الفئات',
        'navigation' => 'شجرة الفئات',
        'tree_view' => 'هيكل الفئات',
        'list_view' => 'عرض القائمة',
        'drag_hint' => 'اسحب الفئات لإعادة ترتيبها أو تداخلها',
        'create_category' => 'إنشاء فئة',
        'no_categories' => 'لا توجد فئات بعد',
        'create_first' => 'أنشئ أول فئة خدمة للبدء.',
        'subcategories' => 'فئات فرعية',
        'active' => 'نشط',
        'inactive' => 'غير نشط',
        'click_activate' => 'انقر للتفعيل',
        'click_deactivate' => 'انقر للإلغاء',
        'reordered' => 'تم إعادة ترتيب الفئات بنجاح',
        'activated' => 'تم تفعيل الفئة',
        'deactivated' => 'تم إلغاء تفعيل الفئة',
    ],
];
