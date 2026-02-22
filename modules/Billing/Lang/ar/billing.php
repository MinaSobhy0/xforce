<?php

return [
    'invoice' => 'فاتورة',
    'invoices' => 'الفواتير',
    'payment' => 'دفعة',
    'payments' => 'المدفوعات',
    'tax_rate' => 'معدل الضريبة',
    'tax_rates' => 'معدلات الضرائب',

    'statuses' => [
        'draft' => 'مسودة',
        'issued' => 'صادرة',
        'partially_paid' => 'مدفوعة جزئياً',
        'paid' => 'مدفوعة',
        'overdue' => 'متأخرة',
        'cancelled' => 'ملغاة',
        'refunded' => 'مستردة',
    ],

    'types' => [
        'standard' => 'فاتورة قياسية',
        'credit_note' => 'إشعار دائن',
        'proforma' => 'فاتورة أولية',
    ],

    'payment_methods' => [
        'cash' => 'نقداً',
        'card' => 'بطاقة',
        'bank_transfer' => 'تحويل بنكي',
        'wallet' => 'محفظة رقمية',
        'gift_card' => 'بطاقة هدايا',
        'insurance' => 'تأمين',
        'installment' => 'تقسيط',
        'online' => 'دفع إلكتروني',
    ],

    'actions' => [
        'issue' => 'إصدار الفاتورة',
        'record_payment' => 'تسجيل دفعة',
        'cancel' => 'إلغاء الفاتورة',
        'print' => 'طباعة',
        'download_pdf' => 'تحميل PDF',
    ],

    'tabs' => [
        'all' => 'الكل',
        'today' => 'اليوم',
        'cash' => 'نقدي',
        'bank' => 'تحويل بنكي',
    ],

    'messages' => [
        'invoice_issued' => 'تم إصدار الفاتورة بنجاح',
        'payment_recorded' => 'تم تسجيل الدفعة بنجاح',
        'invoice_cancelled' => 'تم إلغاء الفاتورة',
    ],

    // PDF Invoice
    'pdf' => [
        'invoice' => 'فاتورة',
        'date' => 'التاريخ',
        'due_date' => 'تاريخ الاستحقاق',
        'bill_to' => 'فاتورة إلى',
        'branch' => 'الفرع',
        'phone' => 'الهاتف',
        'email' => 'البريد الإلكتروني',
        'tax_number' => 'الرقم الضريبي',
        'description' => 'الوصف',
        'qty' => 'الكمية',
        'unit_price' => 'سعر الوحدة',
        'discount' => 'الخصم',
        'tax' => 'الضريبة',
        'total' => 'الإجمالي',
        'subtotal' => 'المجموع الفرعي',
        'paid' => 'المدفوع',
        'balance_due' => 'المبلغ المستحق',
        'payment_history' => 'سجل المدفوعات',
        'method' => 'طريقة الدفع',
        'reference' => 'المرجع',
        'amount' => 'المبلغ',
        'notes' => 'ملاحظات',
        'thank_you' => 'شكراً لتعاملكم معنا!',
    ],

    // أخطاء تكامل الدفع
    'errors' => [
        'gift_card_not_found' => 'بطاقة الهدايا غير موجودة',
        'gift_card_not_redeemable' => 'لا يمكن استخدام بطاقة الهدايا (الحالة: :status)',
        'nothing_to_pay' => 'لا يوجد مبلغ للدفع',
        'no_patient_on_invoice' => 'الفاتورة غير مرتبطة بمريض',
        'no_loyalty_points' => 'لا توجد نقاط ولاء متاحة للمريض',
        'insufficient_points' => 'نقاط الولاء غير كافية (المطلوب: :requested، المتاح: :available)',
    ],

    // ملاحظات الدفع
    'notes' => [
        'gift_card_payment' => 'دفع عبر بطاقة الهدايا :code',
        'loyalty_points_payment' => 'دفع عبر :points نقطة ولاء',
        'member_discount_applied' => 'تم تطبيق خصم العضوية (فئة :tier، :percentage%)',
    ],

    // نقاط الولاء
    'loyalty_points' => 'نقاط الولاء',
    'points_balance' => 'رصيد النقاط',
    'points_value' => 'قيمة النقاط',
    'use_points' => 'استخدام النقاط',

    // خصم العضوية
    'member_discount' => 'خصم العضوية',
    'membership_tier' => 'فئة العضوية',
];
