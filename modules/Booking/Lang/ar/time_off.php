<?php

return [
    'navigation' => 'الإجازات',
    'singular' => 'طلب إجازة',
    'plural' => 'طلبات الإجازات',
    'days_remaining' => 'يوم متبقي',
    'hours_remaining' => 'ساعة متبقية',
    'remaining_this_month' => 'متبقي هذا الشهر',
    'remaining_this_year' => 'متبقي هذا العام',
    'unknown_type' => 'نوع غير معروف',

    // Request units
    'request_units' => [
        'day' => 'أيام',
        'half_day' => 'نصف يوم',
        'hour' => 'ساعات',
    ],

    'request_units_singular' => [
        'day' => 'يوم',
        'half_day' => 'نصف يوم',
        'hour' => 'ساعة',
    ],

    // Allocation periods
    'allocation_periods' => [
        'yearly' => 'سنوي',
        'monthly' => 'شهري',
    ],

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
        'branch_auto' => 'يتم ملؤه تلقائياً من الموظف',
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
        'duration' => 'المدة',
        'days_requested' => 'الأيام المطلوبة',
        'days_requested_help' => 'عدّل إذا اختلفت عن أيام التقويم (مثل نصف يوم)',
        'hours_requested' => 'الساعات المطلوبة',
        'hours_requested_help' => 'يتم حساب الساعات تلقائياً من نطاق الوقت',
        'remaining_days' => ':days يوم متبقي',
        'remaining_this_month' => ':value متبقي هذا الشهر',
        'remaining_this_year' => ':value متبقي هذا العام',
        'select_staff_first' => 'اختر موظفاً أولاً',
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

    'messages' => [
        'approved' => 'تمت الموافقة على طلب الإجازة',
        'rejected' => 'تم رفض طلب الإجازة',
        'cancelled' => 'تم إلغاء طلب الإجازة',
        'cannot_edit_non_pending' => 'يمكن تعديل طلبات الإجازات المعلقة فقط',
    ],

    'all_branches' => 'جميع الفروع',

    'tabs' => [
        'my_time_off' => 'إجازاتي',
        'pending' => 'بانتظار الموافقة',
        'approved' => 'الموافق عليها',
        'all' => 'جميع الطلبات',
    ],

    // Time Off Types
    'types' => [
        'navigation' => 'أنواع الإجازات',
        'singular' => 'نوع إجازة',
        'plural' => 'أنواع الإجازات',

        'sections' => [
            'basic' => 'المعلومات الأساسية',
            'unit_settings' => 'إعدادات الوحدة والتخصيص',
            'settings' => 'الموافقة والقواعد',
        ],

        'fields' => [
            'name' => 'الاسم',
            'code' => 'الكود',
            'description' => 'الوصف',
            'color' => 'اللون',
            'is_paid' => 'إجازة مدفوعة',
            'requires_approval' => 'تتطلب موافقة',
            'request_unit' => 'وحدة الطلب',
            'allocation_period' => 'فترة التخصيص',
            'hours_per_day' => 'ساعات العمل اليومية',
            'default_allocation' => 'التخصيص الافتراضي',
            'default_days' => 'الأيام الافتراضية/السنة',
            'default_hours' => 'الساعات الافتراضية',
            'max_per_request' => 'الحد الأقصى للطلب',
            'max_days_per_request' => 'الحد الأقصى للأيام لكل طلب',
            'max_hours_per_request' => 'الحد الأقصى للساعات لكل طلب',
            'min_days_notice' => 'الحد الأدنى للإشعار المسبق',
            'allow_half_day' => 'السماح بنصف يوم',
            'allow_partial_day' => 'السماح بيوم جزئي',
            'is_active' => 'نشط',
            'sort_order' => 'ترتيب العرض',
            'approval_type' => 'من يمكنه الموافقة',
            'approval_roles' => 'أدوار الموافقة',
            'approval_users' => 'مستخدمو الموافقة',
        ],

        'approval_types' => [
            'any' => 'أي مدير',
            'roles' => 'أدوار محددة فقط',
            'users' => 'مستخدمون محددون فقط',
            'roles_or_users' => 'أدوار أو مستخدمون محددون',
        ],

        'help' => [
            'code' => 'معرف فريد (مثل: ANNUAL, SICK, EXCUSE)',
            'is_paid' => 'ما إذا كان هذا النوع من الإجازة مدفوعاً',
            'request_unit' => 'وحدة طلب الإجازة (أيام أو نصف أيام أو ساعات)',
            'allocation_period' => 'متى يتم تجديد التخصيص (سنوياً أو شهرياً)',
            'hours_per_day' => 'ساعات العمل القياسية في اليوم للتحويل',
            'default_allocation_yearly' => 'التخصيص الافتراضي لكل سنة',
            'default_allocation_monthly' => 'التخصيص الافتراضي لكل شهر',
            'default_days' => 'التخصيص الافتراضي عند إنشاء تخصيصات جديدة',
            'max_per_request' => 'الحد الأقصى المسموح به لكل طلب (اتركه فارغاً للسماح بغير محدود)',
            'max_days' => 'الحد الأقصى للأيام المسموح بها لكل طلب (اتركه فارغاً للسماح بغير محدود)',
            'min_notice' => 'الحد الأدنى للأيام المطلوبة مسبقاً للطلب',
            'partial_day' => 'السماح بطلب ساعات محددة خلال اليوم',
            'approval_roles' => 'اختر الأدوار التي يمكنها الموافقة على هذا النوع من الإجازات',
            'approval_users' => 'اختر المستخدمين الذين يمكنهم الموافقة على هذا النوع من الإجازات',
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
            'hours' => 'الساعات',
        ],

        'fields' => [
            'practitioner' => 'المختص',
            'type' => 'نوع الإجازة',
            'year' => 'السنة',
            'month' => 'الشهر',
            'period' => 'الفترة',
            'allocated' => 'المخصص',
            'allocated_days' => 'الأيام المخصصة',
            'allocated_hours' => 'الساعات المخصصة',
            'used' => 'المستخدم',
            'used_days' => 'الأيام المستخدمة',
            'used_hours' => 'الساعات المستخدمة',
            'carried_over' => 'المنقول',
            'carried_over_hours' => 'الساعات المنقولة',
            'remaining' => 'المتبقي',
            'remaining_days' => 'الأيام المتبقية',
            'remaining_hours' => 'الساعات المتبقية',
            'notes' => 'ملاحظات',
        ],

        'help' => [
            'carried_over' => 'المنقول من الفترة السابقة',
            'used_days' => 'يتم حسابها تلقائياً من الطلبات الموافق عليها',
            'month' => 'مطلوب لأنواع التخصيص الشهري',
        ],

        'bulk' => [
            'button' => 'تخصيص جماعي',
            'title' => 'التخصيص الجماعي',
            'submit' => 'إنشاء التخصيصات',
            'select_staff' => 'اختر الموظفين',
            'select_staff_help' => 'اختر الموظفين الذين تريد التخصيص لهم',
            'all_staff' => 'تحديد جميع الموظفين',
            'all_months' => 'جميع الـ 12 شهر',
            'all_months_help' => 'إنشاء تخصيصات لجميع أشهر السنة الـ 12 دفعة واحدة',
            'select_months' => 'اختر الأشهر',
            'amount_help' => 'المبلغ المراد تخصيصه (يستخدم الافتراضي إذا لم يتم التغيير)',
            'skip_existing' => 'تجاوز التخصيصات الموجودة',
            'skip_existing_help' => 'إذا تم التفعيل، سيتم تجاوز الموظفين الذين لديهم تخصيص بالفعل',
            'success' => 'اكتمل التخصيص الجماعي',
            'success_message' => 'تم إنشاء :created تخصيص، تم تجاوز :skipped موجود.',
        ],
    ],
];
