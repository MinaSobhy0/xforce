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
        'years' => 'سنة',
        'duration' => 'المدة',
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
        'sell_product' => 'بيع منتج',
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
        'status' => 'الحالة',
        'medications' => 'الأدوية الحالية',
        'conditions' => 'الحالات الطبية',
        'allergies' => 'حساسية خطيرة',
        'contraindications' => 'موانع الاستعمال',
        'no_history' => 'لم يتم تسجيل تاريخ طبي',
        'no_profile' => 'لم يتم العثور على ملف طبي',
        'view_full_profile' => 'الملف الكامل',
        'create_profile' => 'إنشاء ملف طبي',
        'pregnant' => 'حامل',
        'breastfeeding' => 'مرضعة',
        'critical_allergies' => 'حساسية خطيرة',
        'has_contraindications' => 'يوجد موانع استعمال',
        'no_allergies' => 'لا توجد حساسية معروفة',
        'no_medications' => 'لا توجد أدوية حالية',
        'no_contraindications' => 'لا توجد موانع استعمال',
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
        'take_photo' => 'التقاط صورة',
        'choose_file' => 'المعرض',
        'selected' => 'المحدد',
        'uploading' => 'جاري الرفع...',
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
        'no_plan' => 'لا توجد خطة علاج مخصصة',
        'pending_delivery' => 'قيد التسليم',
        'current' => 'الحالي',
        'not_started' => 'لم تبدأ',
        'in_progress' => 'جارية',
        'cancelled' => 'ملغاة',
        'completed' => 'مكتمل',
        'delivered' => 'تم التسليم',
        'package_item' => 'باقة',
        'legend' => [
            'package' => 'من الباقة',
            'individual' => 'خدمة فردية',
            'product' => 'منتج',
        ],
    ],

    // Previous visits
    'previous' => [
        'no_visits' => 'لا توجد زيارات سابقة',
    ],

    // Actions
    'actions' => [
        'complete_session' => 'إنهاء الجلسة',
        'back_to_dashboard' => 'العودة للوحة',
        'apply_discount' => 'تطبيق خصم',
        'apply' => 'تطبيق',
        'add_to_plan' => 'إضافة للخطة',
        'start_session' => 'بدء الجلسة',
        'start_another_session' => 'بدء جلسة أخرى',
    ],

    // Modals
    'modals' => [
        'complete_session' => 'إنهاء الجلسة',
        'complete_session_desc' => 'هل أنت متأكد من إنهاء هذه الجلسة؟ سيتم تحديث حالة الموعد إلى مكتمل.',
        'apply_discount' => 'تطبيق خصم',
        'add_to_plan' => 'إضافة لخطة العلاج',
        'start_another_session' => 'بدء جلسة خدمة أخرى',
    ],

    // Start another session
    'start_another' => [
        'service' => 'الخدمة',
        'action' => 'ماذا تريد أن تفعل؟',
        'complete_current' => 'إنهاء الجلسة الحالية وبدء الجديدة',
        'complete_current_desc' => 'إنهاء هذه الجلسة وبدء الخدمة الجديدة فوراً',
        'keep_open' => 'إبقاء الجلسة الحالية مفتوحة',
        'keep_open_desc' => 'بدء الخدمة الجديدة مع إبقاء هذه الجلسة مفتوحة',
        'assign_doctor' => 'تعيين لطبيب آخر',
        'assign_doctor_desc' => 'إنشاء الموعد وتعيينه لطبيب مؤهل آخر',
        'select_doctor' => 'اختر الطبيب',
        'no_other_doctors' => 'لا يوجد أطباء مؤهلين آخرين لهذه الخدمة',
    ],

    // Plan modal
    'plan_modal' => [
        'mode' => 'نوع الخطة',
        'add_to_existing' => 'إضافة لخطة موجودة',
        'create_new' => 'إنشاء خطة جديدة',
        'select_plan' => 'اختر خطة العلاج',
        'plan_name' => 'اسم الخطة',
        'items' => 'العناصر المراد إضافتها',
        'item_type' => 'النوع',
        'service' => 'خدمة',
        'product' => 'منتج',
        'package' => 'باقة',
        'sessions' => 'الجلسات',
        'quantity' => 'الكمية',
        'interval' => 'الفاصل',
        'price' => 'السعر',
        'original_price' => 'السعر الأصلي',
        'discount_type' => 'الخصم',
        'no_discount' => 'بدون خصم',
        'percentage' => 'نسبة مئوية',
        'fixed_amount' => 'مبلغ ثابت',
        'discount_value' => 'قيمة الخصم',
        'final_price' => 'السعر النهائي',
        'assign_to_doctor' => 'تعيين لطبيب',
        'current_doctor' => 'الطبيب الحالي',
    ],

    // Discount
    'discount' => [
        'type' => 'نوع الخصم',
        'percentage' => 'نسبة الخصم',
        'amount' => 'قيمة الخصم',
        'reason' => 'السبب (اختياري)',
        'reason_placeholder' => 'مثال: مريض جديد، خصم ولاء...',
        'preview' => 'معاينة السعر',
        'original_price' => 'السعر الأصلي',
        'discount_amount' => 'الخصم',
        'final_price' => 'السعر النهائي',
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
        'discount_applied' => 'تم تطبيق الخصم بنجاح',
        'discount_applied_body' => 'تم تطبيق خصم :amount. السعر النهائي: :final',
        'items_added_to_plan' => 'تمت إضافة العناصر لخطة العلاج بنجاح',
        'add_to_plan_failed' => 'فشل إضافة العناصر لخطة العلاج',
        'session_started' => 'تم بدء الجلسة بنجاح',
        'session_resumed' => 'تم استئناف الجلسة',
        'session_assigned' => 'تم تعيين الجلسة بنجاح',
        'session_assigned_body' => 'تم تعيين :service لطبيب آخر',
        'cannot_start_session' => 'لا يمكن بدء جلسة لهذه الخدمة',
        'not_qualified_for_service' => 'أنت غير مؤهل لتقديم هذه الخدمة',
        'error' => 'حدث خطأ',
        'no_visit' => 'لا توجد زيارة نشطة',
        'package_already_pending' => 'هذه الباقة مضافة مسبقاً للشراء',
        'package_added' => 'تمت إضافة الباقة للدفع',
        'package_added_body' => 'سيتم فوترة :package عند الدفع',
        'package_removed' => 'تمت إزالة الباقة من الدفع',
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
        // Shot tracking
        'shots_remaining' => 'النبضات المتبقية',
        'shots_this_session' => 'نبضات هذه الجلسة',
        'energy_delivered' => 'الطاقة (جول)',
        'low_shots_warning' => 'نبضات منخفضة - يُنصح بالصيانة',
        // Maintenance
        'maintenance_due' => 'صيانة مستحقة',
        'next_maintenance' => 'الصيانة القادمة',
        'last_maintenance' => 'آخر صيانة',
        // Dynamic parameters
        'no_tracking_params' => 'لم يتم تكوين معايير التتبع',
        'cumulative_hint' => 'هذه القيمة تتراكم عبر الجلسات',
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

    // Invoice section
    'invoice' => [
        'title' => 'ملخص الفاتورة',
        'item' => 'العنصر',
        'qty' => 'الكمية',
        'unit_price' => 'سعر الوحدة',
        'discount' => 'الخصم',
        'total' => 'الإجمالي',
        'subtotal' => 'المجموع الفرعي',
        'grand_total' => 'المجموع الكلي',
        'service_session' => 'جلسة الخدمة',
        'active_session' => 'جلسة نشطة',
        'product_sold' => 'منتج (مُباع)',
        'service_discount' => 'خصم الخدمة',
        'overall_discount' => 'خصم إجمالي',
        'no_discount' => 'بدون خصم',
        'percentage' => 'نسبة مئوية',
        'fixed_amount' => 'مبلغ ثابت',
        'discount_reason' => 'سبب الخصم',
        'discount_reason_placeholder' => 'مثال: خصم ولاء، زيارة أولى...',
        'apply_discount' => 'تطبيق الخصم',
        'discount_applied' => 'تم تطبيق الخصم',
        'package_session' => 'جلسة الباقة',
        'plan_product' => 'منتج خطة العلاج',
        'covered_by_package' => 'مغطاة بالباقة',
        'no_billable_items' => 'لا توجد عناصر قابلة للفوترة',
        'edit_price' => 'تعديل السعر',
        'save_price' => 'حفظ',
        'cancel_edit' => 'إلغاء',
        'other_visit_items' => 'عناصر أخرى في الزيارة :code',
        'visit_checkout_note' => 'سيتم إنشاء الفاتورة الكاملة عند الدفع',
    ],

    // View mode
    'view_mode' => [
        'title' => 'عرض جلسة مكتملة',
        'description' => 'هذه الجلسة مكتملة وللعرض فقط',
        'completed_on' => 'اكتملت في',
        'back_to_dashboard' => 'العودة للوحة التحكم',
    ],

    // Previous sessions
    'previous_sessions' => [
        'title' => 'الجلسات السابقة',
        'view' => 'عرض',
        'no_previous' => 'لا توجد جلسات سابقة لهذه الخدمة',
    ],
];
