<?php

return [
    'navigation' => 'الدفع',
    'title' => 'دفع الزيارة',
    'heading' => 'الدفع - :code',

    'info' => [
        'check_in' => 'وقت الوصول',
        'duration' => 'المدة',
        'checked_in_by' => 'تسجيل بواسطة',
    ],

    'sections' => [
        'open_sessions' => 'جلسات مفتوحة',
        'completed_sessions' => 'جلسات مكتملة',
        'cancelled_sessions' => 'جلسات ملغاة',
        'products' => 'المنتجات المباعة',
        'summary' => 'ملخص الفاتورة',
    ],

    'session_actions' => [
        'complete' => 'إكمال الآن',
        'cancel' => 'إلغاء',
        'reschedule' => 'إعادة جدولة',
    ],

    'session_of' => 'الجلسة :current من :total',
    'package_covered' => 'الباقة',
    'discount' => 'خصم',

    'summary' => [
        'subtotal' => 'المجموع الفرعي',
        'package_sessions' => 'جلسات الباقة (:count)',
        'overall_discount' => 'الخصم الإجمالي',
        'discount' => 'الخصم',
        'total' => 'الإجمالي',
    ],

    'discount_types' => [
        'none' => 'بدون خصم',
        'percent' => 'نسبة مئوية',
        'fixed' => 'مبلغ ثابت',
    ],

    'discount_reason_placeholder' => 'سبب الخصم (اختياري)',

    'stats' => [
        'total_services' => 'الخدمات',
        'products_sold' => 'المنتجات',
    ],

    'actions' => [
        'back' => 'رجوع',
        'view_patient' => 'عرض المريض',
        'confirm_checkout' => 'إتمام الدفع',
        'generate_invoice' => 'إنشاء الفاتورة',
    ],

    'modals' => [
        'confirm_checkout' => 'تأكيد الدفع',
        'confirm_description' => 'إنشاء فاتورة بقيمة :total :currency؟',
        'open_sessions_warning' => ':count جلسة مفتوحة سيتم معالجتها وفقاً لاختياراتك.',
    ],

    'messages' => [
        'visit_not_found' => 'الزيارة غير موجودة',
        'already_invoiced' => 'تم إصدار فاتورة لهذه الزيارة مسبقاً',
        'visit_cancelled' => 'تم إلغاء هذه الزيارة',
        'discount_applied' => 'تم تطبيق الخصم',
        'checkout_success' => 'تم إتمام الدفع',
        'invoice_created' => 'تم إنشاء الفاتورة :code',
        'checkout_error' => 'فشل الدفع',
        'empty_visit' => 'لا توجد عناصر قابلة للفوترة في هذه الزيارة. أكمل أو أضف خدمات قبل الدفع.',
    ],

    'invoice_notes' => [
        'discount' => 'خصم: :reason',
        'checkout_discount' => 'خصم الدفع',
    ],
];
