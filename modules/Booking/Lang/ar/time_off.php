<?php

return [
    'navigation' => 'الإجازات',
    'singular' => 'طلب إجازة',
    'plural' => 'طلبات الإجازات',

    'sections' => [
        'request' => 'تفاصيل الطلب',
        'period' => 'الفترة',
        'details' => 'تفاصيل إضافية',
    ],

    'fields' => [
        'practitioner' => 'المختص',
        'staff' => 'الموظف',
        'branch' => 'الفرع',
        'branch_help' => 'اتركه فارغاً لتطبيقه على جميع الفروع',
        'type' => 'النوع',
        'time_off_type' => 'نوع الإجازة',
        'legacy_type' => 'النوع (قديم)',
        'legacy_type_help' => 'استخدم فقط إذا لم يتم تكوين أنواع الإجازات',
        'start_date' => 'تاريخ البدء',
        'end_date' => 'تاريخ الانتهاء',
        'start_time' => 'وقت البدء',
        'end_time' => 'وقت الانتهاء',
        'is_full_day' => 'يوم كامل',
        'period' => 'الفترة',
        'days' => 'الأيام',
        'days_requested' => 'الأيام المطلوبة',
        'days_requested_help' => 'عدّل إذا اختلفت عن أيام التقويم (مثل نصف يوم)',
        'remaining_days' => ':days يوم متبقي',
        'reason' => 'السبب',
        'status' => 'الحالة',
        'approved_by' => 'تمت الموافقة بواسطة',
        'rejection_reason' => 'سبب الرفض',
        'notes' => 'ملاحظات',
        'created_at' => 'تاريخ الطلب',
    ],

    'filters' => [
        'from' => 'من تاريخ',
        'until' => 'إلى تاريخ',
        'pending_only' => 'المعلقة فقط',
    ],

    'actions' => [
        'approve' => 'موافقة',
        'reject' => 'رفض',
        'cancel' => 'إلغاء',
    ],

    'all_branches' => 'جميع الفروع',

    // Time Off Types
    'types' => [
        'navigation' => 'أنواع الإجازات',
        'singular' => 'نوع إجازة',
        'plural' => 'أنواع الإجازات',

        'sections' => [
            'basic' => 'المعلومات الأساسية',
            'settings' => 'الإعدادات والقواعد',
        ],

        'fields' => [
            'name' => 'الاسم',
            'code' => 'الكود',
            'description' => 'الوصف',
            'color' => 'اللون',
            'is_paid' => 'إجازة مدفوعة',
            'requires_approval' => 'تتطلب موافقة',
            'default_days' => 'الأيام الافتراضية/السنة',
            'max_days_per_request' => 'الحد الأقصى للأيام لكل طلب',
            'min_days_notice' => 'الحد الأدنى للإشعار المسبق',
            'allow_half_day' => 'السماح بنصف يوم',
            'allow_partial_day' => 'السماح بيوم جزئي',
            'is_active' => 'نشط',
            'sort_order' => 'ترتيب العرض',
        ],

        'help' => [
            'code' => 'معرف فريد (مثل: ANNUAL, SICK, PERSONAL)',
            'is_paid' => 'ما إذا كان هذا النوع من الإجازة مدفوعاً',
            'default_days' => 'التخصيص الافتراضي عند إنشاء تخصيصات جديدة',
            'max_days' => 'الحد الأقصى للأيام المسموح بها لكل طلب (اتركه فارغاً للسماح بغير محدود)',
            'min_notice' => 'الحد الأدنى للأيام المطلوبة مسبقاً للطلب',
            'partial_day' => 'السماح بطلب ساعات محددة خلال اليوم',
        ],
    ],

    // Time Off Allocations
    'allocations' => [
        'navigation' => 'تخصيصات الإجازات',
        'singular' => 'تخصيص إجازة',
        'plural' => 'تخصيصات الإجازات',

        'sections' => [
            'allocation' => 'تفاصيل التخصيص',
            'days' => 'الأيام',
        ],

        'fields' => [
            'practitioner' => 'المختص',
            'type' => 'نوع الإجازة',
            'year' => 'السنة',
            'allocated_days' => 'الأيام المخصصة',
            'used_days' => 'الأيام المستخدمة',
            'carried_over' => 'المنقولة',
            'remaining_days' => 'الأيام المتبقية',
            'notes' => 'ملاحظات',
        ],

        'help' => [
            'carried_over' => 'الأيام المنقولة من السنة السابقة',
            'used_days' => 'يتم حسابها تلقائياً من الطلبات الموافق عليها',
        ],
    ],
];
