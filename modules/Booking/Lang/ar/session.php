<?php

return [
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
    ],

    // Alerts
    'alerts' => [
        'allergies' => 'الحساسية',
        'contraindications' => 'موانع الاستعمال',
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
        'plan_created' => 'تم إنشاء خطة العلاج بنجاح',
        'plan_creation_failed' => 'فشل إنشاء خطة العلاج',
    ],
];
