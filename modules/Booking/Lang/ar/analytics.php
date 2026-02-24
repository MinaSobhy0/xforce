<?php

return [
    'navigation_label' => 'تحليلات العلاج',
    'title' => 'تحليلات العلاج',
    'heading' => 'تحليلات العلاج',
    'subheading' => 'تحليل جلسات العلاج واستخدام المعدات ومقاييس الأداء',

    // Filters
    'filters' => [
        'date_range' => 'الفترة الزمنية',
        'service' => 'الخدمة',
        'practitioner' => 'الممارس',
        'all_services' => 'جميع الخدمات',
        'all_practitioners' => 'جميع الممارسين',
    ],

    // Date ranges
    'date_ranges' => [
        '7_days' => 'آخر 7 أيام',
        '30_days' => 'آخر 30 يوم',
        '90_days' => 'آخر 90 يوم',
        '180_days' => 'آخر 6 أشهر',
        '1_year' => 'آخر سنة',
    ],

    // Metrics
    'metrics' => [
        'total_sessions' => 'إجمالي الجلسات',
        'completed_sessions' => 'الجلسات المكتملة',
        'completion_rate' => 'معدل الإكمال',
        'avg_duration' => 'متوسط المدة',
        'avg_pain_level' => 'متوسط مستوى الألم',
        'total_revenue' => 'إجمالي الإيرادات',
        'minutes' => 'دقيقة',
    ],

    // Sections
    'sections' => [
        'summary' => 'الملخص',
        'top_services' => 'أفضل الخدمات',
        'practitioner_performance' => 'أداء الممارسين',
        'equipment_utilization' => 'استخدام المعدات',
        'consumables_usage' => 'استخدام المستهلكات',
        'products_usage' => 'استخدام المنتجات',
        'session_trends' => 'اتجاهات الجلسات',
        'skin_reactions' => 'تفاعلات الجلد',
        'parameter_statistics' => 'إحصائيات المعايير',
    ],

    // Table headers
    'headers' => [
        'service' => 'الخدمة',
        'sessions' => 'الجلسات',
        'avg_duration' => 'متوسط المدة',
        'total_revenue' => 'الإيرادات',
        'practitioner' => 'الممارس',
        'completed' => 'مكتملة',
        'avg_pain' => 'متوسط الألم',
        'equipment' => 'المعدات',
        'total_shots' => 'إجمالي النبضات',
        'total_energy' => 'إجمالي الطاقة',
        'consumable' => 'المستهلك',
        'quantity_used' => 'الكمية المستخدمة',
        'total_cost' => 'إجمالي التكلفة',
        'product' => 'المنتج',
        'applied' => 'مطبق',
        'sold' => 'مباع',
        'total_value' => 'إجمالي القيمة',
        'date' => 'التاريخ',
        'count' => 'العدد',
        'reaction' => 'التفاعل',
        'percentage' => 'النسبة',
        'parameter' => 'المعيار',
        'min' => 'الأدنى',
        'max' => 'الأقصى',
        'avg' => 'المتوسط',
        'median' => 'الوسيط',
        'std_dev' => 'الانحراف المعياري',
        'unit' => 'الوحدة',
    ],

    // Skin reactions
    'skin_reactions' => [
        'none' => 'لا يوجد',
        'mild_erythema' => 'احمرار خفيف',
        'moderate_erythema' => 'احمرار متوسط',
        'severe_erythema' => 'احمرار شديد',
        'edema' => 'تورم',
        'blistering' => 'تقرحات',
        'hyperpigmentation' => 'فرط التصبغ',
        'hypopigmentation' => 'نقص التصبغ',
        'crusting' => 'تقشر',
        'other' => 'أخرى',
    ],

    // Empty states
    'empty' => [
        'no_data' => 'لا توجد بيانات للفترة المحددة',
        'no_services' => 'لم يتم العثور على خدمات',
        'no_practitioners' => 'لا توجد بيانات ممارسين',
        'no_equipment' => 'لم يتم تسجيل استخدام معدات',
        'no_consumables' => 'لم يتم استخدام مستهلكات',
        'no_products' => 'لم يتم استخدام منتجات',
        'no_parameters' => 'اختر خدمة لعرض إحصائيات المعايير',
        'no_reactions' => 'لم يتم تسجيل تفاعلات جلدية',
    ],

    // Actions
    'actions' => [
        'export' => 'تصدير التقرير',
        'refresh' => 'تحديث البيانات',
        'print' => 'طباعة التقرير',
    ],

    // Tooltips
    'tooltips' => [
        'completion_rate' => 'نسبة الجلسات المكتملة مقارنة بالمجدولة',
        'avg_duration' => 'متوسط مدة الجلسة بالدقائق',
        'avg_pain' => 'متوسط مستوى الألم المسجل (مقياس 0-10)',
    ],

    // Labels (used in various places)
    'labels' => [
        'sessions' => 'جلسات',
    ],

    // No data message
    'no_data' => 'لا توجد بيانات متاحة',

    // Equipment section
    'equipment' => [
        'name' => 'المعدات',
        'sessions' => 'الجلسات',
        'total_shots' => 'إجمالي النبضات',
        'avg_shots' => 'متوسط النبضات',
    ],

    // Consumables section
    'consumables' => [
        'product' => 'المنتج',
        'used' => 'المستخدم',
        'total_cost' => 'إجمالي التكلفة',
        'times' => 'مرات',
    ],

    // Products section
    'products' => [
        'name' => 'المنتج',
        'type' => 'النوع',
        'quantity' => 'الكمية',
        'revenue' => 'الإيرادات',
        'sold' => 'مباع',
        'applied' => 'مطبق',
    ],

    // Parameters section
    'parameters' => [
        'name' => 'المعيار',
        'count' => 'العدد',
        'min' => 'الأدنى',
        'max' => 'الأقصى',
        'avg' => 'المتوسط',
        'median' => 'الوسيط',
        'std_dev' => 'الانحراف المعياري',
    ],
];
