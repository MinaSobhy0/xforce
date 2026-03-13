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
        'new_patient_commission' => 'عمولة المريض الجديد',
        'new_patient_commission_description' => 'عمولة خاصة للموعد الأول لمريض جديد.',
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
        'new_patient_enabled' => 'تفعيل عمولة المريض الجديد',
        'new_patient_commission' => 'مريض جديد',
    ],

    'commission_types' => [
        'percentage' => 'نسبة مئوية',
        'flat' => 'مبلغ ثابت',
        'tiered' => 'متدرج',
    ],

    'help' => [
        'service_specific' => 'اختر خدمة محددة لهذه القاعدة، أو اتركها فارغة لقاعدة على مستوى الفئة.',
        'category_fallback' => 'اختر فئة. إذا لم يتم اختيار خدمة، تطبق هذه القاعدة على جميع الخدمات في الفئة.',
        'tiered_requires_rules' => 'العمولة المتدرجة تتطلب إعداد قواعد الخدمات مع نطاقات الإيرادات.',
        'tiered_title' => 'العمولة المتدرجة',
        'tiered_description' => 'للعمولة المتدرجة، ستكون القيمة الافتراضية 0%. يجب إضافة قواعد الخدمات أدناه لتحديد مستويات العمولة بناءً على نطاقات الإيرادات. كل مستوى يحدد نطاق الإيرادات (من/إلى) ونسبة مئوية.',
    ],

    'messages' => [
        'no_plan_assigned' => 'لم يتم تعيين خطة عمولة.',
    ],
];
