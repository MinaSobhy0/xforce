<?php

return [
    // Navigation
    'navigation' => 'لوحة الطبيب',
    'title' => 'لوحة الطبيب',
    'heading' => 'جلسات اليوم',

    // Admin selector
    'admin' => [
        'viewing_as' => 'عرض لوحة',
        'viewing_other' => 'عرض كطبيب آخر',
        'select_practitioner' => 'اختر الطبيب',
    ],

    // Date selector
    'date' => [
        'label' => 'التاريخ',
        'today' => 'اليوم',
        'yesterday' => 'أمس',
        'tomorrow' => 'غداً',
        'back_to_today' => 'العودة لليوم',
        'past_date' => 'عرض تاريخ سابق',
        'past_appointment' => 'موعد سابق',
        'view_only' => 'عرض فقط',
    ],

    // Statistics
    'stats' => [
        'waiting' => 'بالانتظار',
        'in_progress' => 'جاري',
        'completed' => 'مكتمل',
        'upcoming' => 'قادم',
        'checked_in_desc' => 'مرضى بالانتظار',
        'in_progress_desc' => 'جلسات نشطة',
        'completed_desc' => 'جلسات مكتملة اليوم',
        'upcoming_desc' => 'مجدولة لليوم',
    ],

    // Queue
    'queue' => [
        'title' => 'قائمة المواعيد',
        'checked_in' => 'تم الحضور',
        'in_progress' => 'جاري',
        'confirmed' => 'مؤكد',
        'scheduled' => 'مجدول',
        'completed' => 'مكتمل',
        'empty' => 'لا توجد مواعيد مجدولة لليوم',
        'active' => 'نشط',
    ],

    // Actions
    'actions' => [
        'start' => 'ابدأ',
        'resume' => 'استئناف',
        'complete' => 'إنهاء',
        'check_in' => 'تسجيل حضور',
        'confirm' => 'تأكيد',
        'back_to_queue' => 'العودة للقائمة',
        'view' => 'عرض',
        'reschedule' => 'إعادة جدولة',
    ],

    // Status labels
    'status' => [
        'missed' => 'فائت',
    ],

    // Tabs
    'tabs' => [
        'info' => 'معلومات المريض',
        'medical' => 'التاريخ الطبي',
        'photos' => 'الصور',
        'notes' => 'الملاحظات',
        'plan' => 'خطة العلاج',
    ],

    // Workspace
    'workspace' => [
        'no_session' => 'لا توجد جلسة نشطة',
        'select_patient' => 'اختر مريضاً من القائمة لبدء الجلسة',
    ],

    // Patient Info
    'patient' => [
        'code' => 'كود المريض',
        'age' => 'العمر',
        'years' => 'سنة',
        'phone' => 'الهاتف',
        'member_since' => 'عضو منذ',
        'allergies' => 'الحساسية',
        'contraindications' => 'موانع الاستعمال',
        'medications' => 'الأدوية الحالية',
    ],

    // Medical History
    'medical' => [
        'fitzpatrick' => 'نوع البشرة',
        'blood_type' => 'فصيلة الدم',
        'bmi' => 'مؤشر كتلة الجسم',
        'smoker' => 'مدخن',
        'conditions' => 'الحالات الطبية',
        'previous_treatments' => 'العلاجات التجميلية السابقة',
        'notes' => 'الملاحظات الطبية',
        'no_history' => 'لم يتم تسجيل تاريخ طبي',
    ],

    // Photos
    'photos' => [
        'upload' => 'رفع صورة',
        'file' => 'الصورة',
        'type' => 'النوع',
        'body_area' => 'منطقة الجسم',
        'select_area' => 'اختر المنطقة...',
        'upload_btn' => 'رفع',
        'no_photos' => 'لم يتم تسجيل صور',
    ],

    // Notes
    'notes' => [
        'add' => 'إضافة ملاحظة الجلسة',
        'placeholder' => 'اكتب ملاحظات الجلسة هنا...',
        'save' => 'حفظ الملاحظة',
        'no_notes' => 'لم يتم تسجيل ملاحظات',
    ],

    // Treatment Plan
    'plan' => [
        'active_plans' => 'خطط العلاج النشطة',
        'progress' => 'التقدم',
        'sessions' => 'جلسات',
        'create' => 'إنشاء خطة علاج',
        'name' => 'اسم الخطة',
        'name_placeholder' => 'مثال: إزالة الشعر بالليزر - كامل الجسم',
        'services' => 'الخدمات',
        'select_service' => 'اختر الخدمة...',
        'interval' => 'الفاصل',
        'days' => 'أيام',
        'add_service' => 'إضافة خدمة',
        'notes' => 'ملاحظات',
        'notes_placeholder' => 'ملاحظات إضافية لخطة العلاج...',
        'create_btn' => 'إنشاء الخطة',
    ],

    // Messages
    'messages' => [
        'cannot_start' => 'لا يمكن بدء الجلسة',
        'must_be_checked_in' => 'يجب تسجيل حضور المريض أولاً',
        'must_be_confirmed' => 'يجب تأكيد الموعد أولاً',
        'cannot_resume' => 'لا يمكن استئناف الجلسة',
        'cannot_complete' => 'لا يمكن إنهاء الجلسة',
        'session_started' => 'بدأت الجلسة',
        'session_completed' => 'اكتملت الجلسة',
        'confirm_complete' => 'هل أنت متأكد من رغبتك في إنهاء هذه الجلسة؟',
        'note_required' => 'الرجاء إدخال ملاحظة',
        'note_added' => 'تمت إضافة الملاحظة',
        'photo_required' => 'الرجاء اختيار صورة للرفع',
        'photo_uploaded' => 'تم رفع الصورة',
        'plan_created' => 'تم إنشاء خطة العلاج',
        'plan_creation_failed' => 'فشل إنشاء خطة العلاج',
        'cannot_check_in' => 'لا يمكن تسجيل حضور المريض',
        'patient_checked_in' => 'تم تسجيل حضور المريض',
        'cannot_confirm' => 'لا يمكن تأكيد الموعد',
        'appointment_confirmed' => 'تم تأكيد الموعد',
        'cannot_reschedule' => 'لا يمكن إعادة الجدولة',
        'appointment_already_started' => 'هذا الموعد بدأ بالفعل أو اكتمل',
    ],
];
