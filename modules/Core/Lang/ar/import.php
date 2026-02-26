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
        'failed_title' => 'فشل الاستيراد',
        'failed_body' => 'تعذر إكمال الاستيراد. يرجى التحقق من الملف والمحاولة مرة أخرى.',
        'success_title' => 'تم الاستيراد بنجاح',
        'no_data' => 'لم يتم العثور على بيانات في الملف المرفوع.',
        'invalid_file' => 'تعذر قراءة الملف المرفوع. يرجى التحقق من تنسيق الملف.',
    ],

    // Validation messages
    'validation' => [
        'missing_mappings' => 'تعيينات مطلوبة مفقودة',
        'required_mapping' => 'الحقل ":column" مطلوب ويجب تعيينه إلى عمود.',
        'field_required' => 'هذا الحقل مطلوب.',
        'field_string' => 'يجب أن يكون هذا الحقل نصاً.',
        'field_max' => 'يجب ألا يتجاوز هذا الحقل :max حرفاً.',
        'no_file' => 'يرجى رفع ملف للاستيراد.',
        'invalid_format' => 'تنسيق الملف غير مدعوم. يرجى استخدام ملفات CSV أو Excel.',
    ],

    // Error messages
    'errors' => [
        'row_failed' => 'صف :row: :message',
        'column_not_found' => 'العمود ":column" غير موجود في الملف.',
        'invalid_value' => 'قيمة غير صالحة للحقل ":field".',
        'relationship_not_found' => 'تعذر العثور على :model بالقيمة ":value".',
        'duplicate_entry' => 'تم العثور على إدخال مكرر للحقل ":field".',
        'database_error' => 'خطأ في قاعدة البيانات: :message',
        'unknown_error' => 'حدث خطأ غير معروف أثناء معالجة الصف :row.',
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
