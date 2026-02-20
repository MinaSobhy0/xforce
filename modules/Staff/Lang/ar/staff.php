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
    ],

    'sections' => [
        'basic_info' => 'المعلومات الأساسية',
        'bio' => 'السيرة الذاتية والتخصصات',
        'compensation' => 'التعويضات',
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
];
