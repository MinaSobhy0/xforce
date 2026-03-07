<?php

return [
    'navigation' => 'الزيارات',
    'singular' => 'زيارة',
    'plural' => 'الزيارات',

    'fields' => [
        'code' => 'كود الزيارة',
        'patient' => 'المريض',
        'branch' => 'الفرع',
        'check_in_at' => 'وقت الوصول',
        'check_out_at' => 'وقت المغادرة',
        'checked_in_by' => 'تسجيل بواسطة',
        'checked_out_by' => 'مغادرة بواسطة',
        'status' => 'الحالة',
        'source' => 'المصدر',
        'chief_complaint' => 'الشكوى الرئيسية',
        'total' => 'الإجمالي',
        'notes' => 'ملاحظات',
        'invoice' => 'الفاتورة',
        'appointments' => 'المواعيد',
        'products' => 'المنتجات',
        'duration' => 'المدة',
    ],

    'statuses' => [
        'open' => 'مفتوحة',
        'completed' => 'مكتملة',
        'invoiced' => 'تم الفوترة',
        'cancelled' => 'ملغاة',
    ],

    'sources' => [
        'walk_in' => 'حضور مباشر',
        'appointment' => 'موعد',
        'online' => 'حجز إلكتروني',
    ],

    'actions' => [
        'checkout' => 'الدفع',
        'view_invoice' => 'عرض الفاتورة',
        'cancel' => 'إلغاء الزيارة',
    ],

    'sections' => [
        'visit_info' => 'معلومات الزيارة',
        'appointments' => 'المواعيد',
        'products' => 'المنتجات المباعة',
        'billing' => 'ملخص الفوترة',
        'invoice_payments' => 'الفاتورة والمدفوعات',
        'payments' => 'سجل المدفوعات',
    ],

    'filters' => [
        'open_only' => 'المفتوحة فقط',
    ],

    'messages' => [
        'visit_created' => 'تم إنشاء الزيارة',
        'visit_cancelled' => 'تم إلغاء الزيارة',
        'checkout_required' => 'يرجى إتمام الدفع قبل المغادرة',
        'no_visits' => 'لا يوجد سجل زيارات',
    ],
];
