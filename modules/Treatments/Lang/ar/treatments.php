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
];
