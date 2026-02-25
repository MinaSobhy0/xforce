<?php

return [
    'navigation_label' => 'جلسة العلاج',
    'title' => 'جلسة العلاج',
    'heading' => 'جلسة العلاج',
    'session_of' => 'الجلسة :current من :total',

    // Info bar
    'info' => [
        'service' => 'الخدمة',
        'time' => 'الوقت',
        'room' => 'الغرفة',
        'session_number' => 'الجلسة :current من :total',
    ],

    // Sections
    'sections' => [
        'medical_info' => 'المعلومات الطبية',
        'notes' => 'ملاحظات الجلسة',
        'photos' => 'الصور',
        'current_plan' => 'خطة العلاج الحالية',
        'previous_visits' => 'الزيارات السابقة',
        'create_plan' => 'إنشاء خطة علاج',
        'pre_treatment_checklist' => 'قائمة فحص ما قبل العلاج',
        'equipment' => 'المعدات',
        'presets' => 'إعدادات مسبقة',
        'parameters' => 'معايير العلاج',
        'clinical_notes' => 'التوثيق السريري',
        'consumables' => 'المستهلكات',
        'products' => 'المنتجات',
    ],

    // Alerts
    'alerts' => [
        'allergies' => 'الحساسية',
        'contraindications' => 'موانع الاستعمال',
        'amr_resistance' => 'مقاومة المضادات الحيوية',
        'critical' => 'حرج',
        'mdro_flags' => 'مقاومة متعددة الأدوية',
        'resistant_to' => 'مقاوم لـ',
        'more' => 'المزيد',
        'view_amr_history' => 'عرض سجل المقاومة الكامل',
    ],

    // Patient
    'patient' => [
        'code' => 'كود المريض',
        'name' => 'الاسم',
        'phone' => 'الهاتف',
        'age' => 'العمر',
        'years' => 'سنة',
    ],

    // Medical
    'medical' => [
        'fitzpatrick' => 'نوع البشرة',
        'blood_type' => 'فصيلة الدم',
        'bmi' => 'مؤشر كتلة الجسم',
        'smoker' => 'مدخن',
        'medications' => 'الأدوية الحالية',
        'conditions' => 'الحالات الطبية',
        'no_history' => 'لم يتم تسجيل تاريخ طبي',
    ],

    // Notes
    'notes' => [
        'placeholder' => 'أدخل ملاحظات الجلسة...',
        'add' => 'إضافة ملاحظة',
        'session_note' => 'ملاحظة الجلسة',
        'no_notes' => 'لم يتم تسجيل ملاحظات',
    ],

    // Photos
    'photos' => [
        'upload' => 'رفع',
        'select_area' => 'منطقة الجسم...',
        'no_photos' => 'لم يتم تسجيل صور',
    ],

    // Treatment Plan
    'plan' => [
        'progress' => 'التقدم',
        'sessions' => 'جلسات',
        'name' => 'اسم الخطة',
        'name_placeholder' => 'مثال: إزالة الشعر بالليزر - كامل الجسم',
        'services' => 'الخدمات',
        'service' => 'الخدمة',
        'sessions_count' => 'الجلسات',
        'interval_days' => 'الفاصل',
        'select_service' => 'اختر الخدمة...',
        'days' => 'أيام',
        'add_service' => 'إضافة خدمة',
        'notes' => 'ملاحظات',
        'create' => 'إنشاء الخطة',
    ],

    // Previous visits
    'previous' => [
        'no_visits' => 'لا توجد زيارات سابقة',
    ],

    // Actions
    'actions' => [
        'complete_session' => 'إنهاء الجلسة',
        'back_to_dashboard' => 'العودة للوحة',
    ],

    // Modals
    'modals' => [
        'complete_session' => 'إنهاء الجلسة',
        'complete_session_desc' => 'هل أنت متأكد من إنهاء هذه الجلسة؟ سيتم تحديث حالة الموعد إلى مكتمل.',
    ],

    // Messages
    'messages' => [
        'appointment_not_found' => 'الموعد غير موجود',
        'session_not_active' => 'هذه الجلسة غير نشطة',
        'session_completed' => 'تم إنهاء الجلسة بنجاح',
        'note_required' => 'الرجاء إدخال ملاحظة',
        'note_added' => 'تمت إضافة الملاحظة بنجاح',
        'photo_required' => 'الرجاء اختيار صورة',
        'photo_uploaded' => 'تم رفع الصورة بنجاح',
        'photo_deleted' => 'تم حذف الصورة بنجاح',
        'plan_created' => 'تم إنشاء خطة العلاج بنجاح',
        'plan_creation_failed' => 'فشل إنشاء خطة العلاج',
        'preset_applied' => 'تم تطبيق الإعداد المسبق بنجاح',
        'clinical_notes_saved' => 'تم حفظ الملاحظات السريرية',
        'checklist_incomplete' => 'قائمة الفحص غير مكتملة',
        'complete_checklist_first' => 'يرجى إكمال جميع عناصر قائمة السلامة قبل إنهاء الجلسة',
        'consumable_added' => 'تمت إضافة المستهلك',
        'consumable_removed' => 'تم حذف المستهلك',
        'product_added' => 'تمت إضافة المنتج',
        'product_removed' => 'تم حذف المنتج',
    ],

    // Pre-treatment checklist
    'checklist' => [
        'patient_identity_verified' => 'تم التحقق من هوية المريض',
        'consent_signed' => 'تم توقيع نموذج الموافقة',
        'medical_history_reviewed' => 'تمت مراجعة التاريخ الطبي',
        'contraindications_checked' => 'تم فحص موانع الاستعمال',
        'allergies_confirmed' => 'تم تأكيد الحساسية',
        'test_patch_done' => 'تم إجراء اختبار الرقعة',
        'eye_protection_provided' => 'تم توفير حماية العين',
        'treatment_area_clean' => 'تم تنظيف منطقة العلاج',
    ],

    // Equipment
    'equipment' => [
        'select' => 'اختر المعدات',
        'none' => 'لم يتم اختيار معدات',
        'metrics' => 'مقاييس الجلسة',
        'shots_used' => 'عدد النبضات',
        'energy' => 'الطاقة المستخدمة (جول)',
        'devices' => 'جهاز',
        'preset' => 'مسبق',
        'shots' => 'نبضات',
        'energy_short' => 'طاقة (ج)',
        'add_equipment' => 'إضافة جهاز...',
        'none_available' => 'لا توجد معدات متاحة',
        'already_added' => 'المعدات مضافة مسبقاً',
        'added' => 'تمت إضافة المعدات',
        'has_params' => 'معايير',
        'tracking_params' => 'معايير التتبع',
    ],

    // Presets
    'presets' => [
        'default' => 'افتراضي',
        'none' => 'لا توجد إعدادات مسبقة لهذه الخدمة',
    ],

    // Clinical notes
    'clinical' => [
        'skin_reaction' => 'تفاعل الجلد',
        'pain_level' => 'مستوى الألم',
        'pain_none' => 'لا يوجد',
        'pain_mild' => 'خفيف',
        'pain_moderate' => 'متوسط',
        'pain_severe' => 'شديد',
        'observations' => 'الملاحظات السريرية',
        'observations_placeholder' => 'أدخل الملاحظات السريرية، ملاحظات عن منطقة العلاج، استجابة المريض، إلخ.',
        'save' => 'حفظ الملاحظات',
    ],

    // Consumables
    'consumables' => [
        'select' => 'اختر مستهلك...',
        'quantity' => 'الكمية',
        'add' => 'إضافة',
        'none' => 'لم تتم إضافة مستهلكات',
        'total_cost' => 'إجمالي التكلفة',
        'unit' => 'الوحدة',
    ],

    // Products
    'products' => [
        'select' => 'اختر منتج...',
        'quantity' => 'الكمية',
        'add' => 'إضافة',
        'none' => 'لم تتم إضافة منتجات',
        'total_value' => 'إجمالي القيمة',
        'usage_type' => 'الاستخدام',
        'applied' => 'مطبق أثناء العلاج',
        'sold' => 'مُباع للمريض',
    ],
];
