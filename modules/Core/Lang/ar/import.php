<?php

return [
    // Action labels
    'action_label' => 'استيراد',
    'label' => 'استيراد :model',

    // Modal
    'modal' => [
        'heading' => 'استيراد :label',
        'actions' => [
            'import' => 'استيراد',
            'download_template' => 'تحميل القالب',
        ],
        'form' => [
            'file' => [
                'label' => 'الملف',
                'placeholder' => 'اسحب وأسقط ملف CSV أو Excel هنا',
            ],
            'import_mode' => [
                'label' => 'وضع الاستيراد',
                'options' => [
                    'create_only' => 'إنشاء سجلات جديدة فقط',
                    'create_and_update' => 'إنشاء جديد وتحديث الموجود',
                    'update_only' => 'تحديث السجلات الموجودة فقط',
                ],
            ],
            'saved_mapping' => [
                'label' => 'التعيين المحفوظ',
                'placeholder' => 'اختر تعيين محفوظ...',
            ],
            'columns' => [
                'label' => 'تعيين الأعمدة',
            ],
            'skip_column' => '-- تخطي هذا الحقل --',
            'save_mapping' => [
                'label' => 'حفظ هذا التعيين للاستخدام المستقبلي',
            ],
            'mapping_name' => [
                'label' => 'اسم التعيين',
                'placeholder' => 'مثال: استيراد المنتجات v1',
            ],
        ],
    ],

    // Notifications
    'notifications' => [
        'completed_body' => 'اكتمل الاستيراد. تمت معالجة :count من :total صف بنجاح.',
        'failed_rows' => 'فشل استيراد :count صف.',
        'mapping_saved' => 'تم حفظ التعيين بنجاح.',
        'started' => [
            'title' => 'بدأ الاستيراد',
            'body' => 'بدأ استيراد :count صف وسيتم معالجتها في الخلفية.',
        ],
        'completed' => [
            'title' => 'اكتمل الاستيراد',
        ],
    ],

    // Field labels
    'fields' => [
        'sku' => 'رمز المنتج',
        'code' => 'الرمز',
        'name' => 'الاسم',
        'description' => 'الوصف',
        'category' => 'الفئة',
        'unit' => 'الوحدة',
        'cost_price' => 'سعر التكلفة',
        'sell_price' => 'سعر البيع',
        'price' => 'السعر',
        'reorder_point' => 'نقطة إعادة الطلب',
        'reorder_quantity' => 'كمية إعادة الطلب',
        'barcode' => 'الباركود',
        'is_consumable' => 'قابل للاستهلاك',
        'is_active' => 'نشط',
        'first_name' => 'الاسم الأول',
        'last_name' => 'الاسم الأخير',
        'email' => 'البريد الإلكتروني',
        'phone' => 'الهاتف',
        'secondary_phone' => 'هاتف ثانوي',
        'mobile' => 'الجوال',
        'date_of_birth' => 'تاريخ الميلاد',
        'gender' => 'الجنس',
        'national_id' => 'رقم الهوية',
        'address' => 'العنوان',
        'city' => 'المدينة',
        'country' => 'الدولة',
        'occupation' => 'المهنة',
        'emergency_contact_name' => 'اسم جهة اتصال الطوارئ',
        'emergency_contact_phone' => 'هاتف جهة اتصال الطوارئ',
        'emergency_contact_relation' => 'علاقة جهة اتصال الطوارئ',
        'referral_source' => 'مصدر الإحالة',
        'language' => 'اللغة',
        'tags' => 'الوسوم',
        'notes' => 'ملاحظات',
        'marketing_consent' => 'موافقة التسويق',
        'sms_consent' => 'موافقة الرسائل',
        'email_consent' => 'موافقة البريد',
        'whatsapp_consent' => 'موافقة واتساب',
        'contact_person' => 'جهة الاتصال',
        'tax_number' => 'الرقم الضريبي',
        'payment_terms_days' => 'شروط الدفع (أيام)',
        'currency_code' => 'رمز العملة',
        'duration_minutes' => 'المدة (دقائق)',
        'buffer_minutes' => 'وقت الفاصل (دقائق)',
        'recommended_sessions' => 'الجلسات الموصى بها',
        'session_interval_days' => 'الفترة بين الجلسات (أيام)',
        'requires_consent' => 'يتطلب موافقة',
        'is_bookable_online' => 'قابل للحجز عبر الإنترنت',
    ],

    // Preview & Validation
    'preview' => [
        'title' => 'معاينة الاستيراد',
        'valid_rows' => 'صفوف صالحة',
        'error_rows' => 'صفوف بها أخطاء',
        'will_create' => 'سيتم إنشاء',
        'will_update' => 'سيتم تحديث',
        'errors' => 'الأخطاء',
        'row' => 'صف :number',
        'skip_invalid' => 'تخطي الصفوف غير الصالحة',
        'abort_on_errors' => 'إيقاف عند الأخطاء',
        'download_error_report' => 'تحميل تقرير الأخطاء',
    ],

    // Progress
    'progress' => [
        'title' => 'جاري الاستيراد...',
        'created' => 'تم إنشاء',
        'updated' => 'تم تحديث',
        'skipped' => 'تم تخطي',
        'running_in_background' => 'يعمل في الخلفية...',
    ],
];
