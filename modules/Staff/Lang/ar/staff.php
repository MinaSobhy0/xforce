<?php

return [
    'navigation' => [
        'profiles' => 'ملفات الموظفين',
        'commissions' => 'العمولات',
    ],

    'labels' => [
        'profile' => 'ملف موظف',
        'profiles' => 'ملفات الموظفين',
        'commission' => 'عمولة',
        'commissions' => 'العمولات',
        'schedule_assignment' => 'تعيين جدول',
        'schedule_assignments' => 'تعيينات الجدول',
    ],

    'relation_managers' => [
        'schedule_assignments' => 'جداول العمل',
    ],

    'sections' => [
        'basic_info' => 'المعلومات الأساسية',
        'bio' => 'السيرة الذاتية والتخصصات',
        'compensation' => 'التعويضات',
        'commission' => 'العمولة',
        'commission_description' => 'قم بتعيين خطة عمولة لحساب العمولات على إيرادات الخدمات. يتم إدارة خطط العمولة في الإعدادات > خطط العمولة.',
        'settings' => 'الإعدادات',
    ],

    'fields' => [
        'name' => 'الاسم',
        'user' => 'المستخدم',
        'branch' => 'الفرع',
        'employee_number' => 'رقم الموظف',
        'job_title' => 'المسمى الوظيفي',
        'bio' => 'السيرة الذاتية',
        'specializations' => 'التخصصات',
        'base_salary' => 'الراتب الأساسي',
        'commission_type' => 'نوع العمولة',
        'commission_percentage' => 'نسبة العمولة',
        'commission' => 'العمولة',
        'hire_date' => 'تاريخ التعيين',
        'contract_end_date' => 'تاريخ انتهاء العقد',
        'is_active' => 'نشط',
        'pending_earnings' => 'قيد الانتظار',
        'treatment' => 'العلاج',
        'service' => 'الخدمة',
        'category' => 'الفئة',
        'type' => 'النوع',
        'flat_amount' => 'المبلغ الثابت',
        'percentage' => 'النسبة المئوية',
        'tier_from' => 'من المستوى',
        'tier_to' => 'إلى المستوى',
        'rule' => 'القاعدة',
        'date' => 'التاريخ',
        'appointment' => 'الموعد',
        'revenue' => 'الإيرادات',
        'rate' => 'المعدل',
        'amount' => 'المبلغ',
        'status' => 'الحالة',
        'approved_at' => 'تاريخ الموافقة',
        'paid_at' => 'تاريخ الدفع',
        'notes' => 'ملاحظات',
        'work_schedule' => 'جدول العمل',
        'schedule' => 'الجدول',
        'effective_from' => 'ساري من',
        'effective_until' => 'ساري حتى',
        'is_primary' => 'رئيسي',
        'is_primary_help' => 'تعيين كجدول رئيسي لهذا الموظف',
    ],

    'commission_types' => [
        'flat' => 'مبلغ ثابت',
        'percentage' => 'نسبة مئوية',
        'tiered' => 'متدرج',
    ],

    'statuses' => [
        'pending' => 'قيد الانتظار',
        'approved' => 'موافق عليه',
        'paid' => 'مدفوع',
        'cancelled' => 'ملغي',
    ],

    'actions' => [
        'approve' => 'موافقة',
        'cancel' => 'إلغاء',
        'approve_selected' => 'الموافقة على المحدد',
    ],

    'messages' => [
        'approved' => 'تمت الموافقة على العمولة بنجاح',
        'cancelled' => 'تم إلغاء العمولة',
        'approved_count' => 'تمت الموافقة على :count عمولة',
    ],

    'widgets' => [
        'pending_commissions' => 'العمولات المعلقة',
        'pending_value' => 'القيمة المعلقة',
        'approved_commissions' => 'العمولات الموافق عليها',
        'awaiting_payment' => 'في انتظار الدفع',
        'paid_this_month' => 'المدفوعة هذا الشهر',
        'commissions_paid' => 'عمولات مدفوعة',
    ],
];
