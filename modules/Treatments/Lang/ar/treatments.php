<?php

return [
    'module_name' => 'العلاجات',
    'module_description' => 'كتالوج العلاجات وإدارة نماذج الموافقة',

    'navigation' => [
        'treatments' => 'العلاجات',
        'categories' => 'الفئات',
        'consent_templates' => 'نماذج الموافقة',
    ],

    'labels' => [
        'treatment' => 'علاج',
        'treatments' => 'العلاجات',
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
        'pre_care' => 'تعليمات ما قبل العلاج',
        'post_care' => 'تعليمات ما بعد العلاج',
        'is_active' => 'نشط',
        'requires_consent' => 'يتطلب موافقة',
        'consent_template' => 'نموذج الموافقة',
        'equipment_required' => 'المعدات المطلوبة',
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
        'created' => 'تم إضافة العلاج بنجاح.',
        'updated' => 'تم تحديث العلاج بنجاح.',
        'deleted' => 'تم حذف العلاج بنجاح.',
    ],

    'category_tree' => [
        'title' => 'شجرة الفئات',
        'navigation' => 'شجرة الفئات',
        'tree_view' => 'هيكل الفئات',
        'list_view' => 'عرض القائمة',
        'drag_hint' => 'اسحب الفئات لإعادة ترتيبها أو تداخلها',
        'create_category' => 'إنشاء فئة',
        'no_categories' => 'لا توجد فئات بعد',
        'create_first' => 'أنشئ أول فئة علاج للبدء.',
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
