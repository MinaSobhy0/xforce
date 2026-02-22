<?php

return [
    'navigation' => [
        'plans' => 'خطط العمولة',
    ],

    'labels' => [
        'plan' => 'خطة العمولة',
        'plans' => 'خطط العمولة',
        'service_rules' => 'قواعد الخدمات',
        'assigned_staff' => 'الموظفون المعينون',
    ],

    'sections' => [
        'plan_details' => 'تفاصيل الخطة',
        'default_commission' => 'العمولة الافتراضية',
        'default_commission_description' => 'العمولة الافتراضية المطبقة عند عدم وجود قاعدة خدمة محددة.',
    ],

    'fields' => [
        'name' => 'الاسم',
        'description' => 'الوصف',
        'commission_type' => 'نوع العمولة',
        'percentage' => 'النسبة المئوية',
        'flat_amount' => 'المبلغ الثابت',
        'tier_from' => 'الإيرادات من',
        'tier_to' => 'الإيرادات إلى',
        'default_value' => 'الافتراضي',
        'assigned_staff' => 'الموظفون المعينون',
        'service_rules' => 'قواعد الخدمات',
        'is_active' => 'نشط',
        'created_at' => 'تاريخ الإنشاء',
        'service' => 'الخدمة',
        'category' => 'الفئة',
        'applies_to' => 'ينطبق على',
        'value' => 'القيمة',
    ],

    'commission_types' => [
        'percentage' => 'نسبة مئوية',
        'flat' => 'مبلغ ثابت',
        'tiered' => 'متدرج',
    ],

    'help' => [
        'service_specific' => 'اختر خدمة محددة لهذه القاعدة، أو اتركها فارغة لقاعدة على مستوى الفئة.',
        'category_fallback' => 'اختر فئة. إذا لم يتم اختيار خدمة، تطبق هذه القاعدة على جميع الخدمات في الفئة.',
    ],

    'messages' => [
        'no_plan_assigned' => 'لم يتم تعيين خطة عمولة.',
    ],
];
