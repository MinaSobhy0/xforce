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

    'messages' => [
        'invoice_issued' => 'تم إصدار الفاتورة بنجاح',
        'payment_recorded' => 'تم تسجيل الدفعة بنجاح',
        'invoice_cancelled' => 'تم إلغاء الفاتورة',
    ],
];
