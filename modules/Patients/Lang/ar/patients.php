<?php

return [
    // Module
    'module_name' => 'المرضى',
    'module_description' => 'إدارة علاقات المرضى والتاريخ الطبي',

    // Navigation
    'navigation' => [
        'patients' => 'المرضى',
        'all_patients' => 'جميع المرضى',
        'new_patient' => 'مريض جديد',
    ],

    // Labels
    'labels' => [
        'patient' => 'مريض',
        'patients' => 'المرضى',
        'patient_code' => 'كود المريض',
        'personal_info' => 'المعلومات الشخصية',
        'contact_info' => 'معلومات الاتصال',
        'medical_history' => 'التاريخ الطبي',
        'consent_forms' => 'نماذج الموافقة',
        'photos' => 'الصور',
        'notes' => 'الملاحظات',
        'activity' => 'النشاط',
    ],

    // Fields
    'fields' => [
        'code' => 'الكود',
        'first_name' => 'الاسم الأول',
        'last_name' => 'اسم العائلة',
        'full_name' => 'الاسم الكامل',
        'email' => 'البريد الإلكتروني',
        'phone' => 'الهاتف',
        'secondary_phone' => 'هاتف ثانوي',
        'date_of_birth' => 'تاريخ الميلاد',
        'age' => 'العمر',
        'gender' => 'الجنس',
        'national_id' => 'الرقم القومي',
        'address' => 'العنوان',
        'city' => 'المدينة',
        'country' => 'الدولة',
        'occupation' => 'المهنة',
        'emergency_contact' => 'جهة اتصال طوارئ',
        'emergency_phone' => 'هاتف الطوارئ',
        'referral_source' => 'مصدر الإحالة',
        'referred_by' => 'تمت الإحالة بواسطة',
        'status' => 'الحالة',
        'tags' => 'الوسوم',
        'notes' => 'ملاحظات',
        'branch' => 'الفرع',
        'created_at' => 'تاريخ التسجيل',
        'last_visit' => 'آخر زيارة',
    ],

    // Medical History
    'medical' => [
        'fitzpatrick_type' => 'نوع البشرة (فيتزباتريك)',
        'blood_type' => 'فصيلة الدم',
        'allergies' => 'الحساسية',
        'medications' => 'الأدوية الحالية',
        'medical_conditions' => 'الحالات الطبية',
        'previous_treatments' => 'العلاجات السابقة',
        'contraindications' => 'موانع الاستعمال',
        'skin_concerns' => 'مشاكل البشرة',
        'pregnancy_status' => 'حالة الحمل',
        'breastfeeding' => 'الرضاعة',
    ],

    // Consent Forms
    'consent' => [
        'form' => 'نموذج موافقة',
        'forms' => 'نماذج الموافقة',
        'signed_at' => 'تاريخ التوقيع',
        'signed_by' => 'التوقيع بواسطة',
        'witness' => 'الشاهد',
        'signature' => 'التوقيع',
        'valid_until' => 'صالح حتى',
        'download' => 'تحميل',
        'sign_new' => 'توقيع نموذج جديد',
    ],

    // Photos
    'photos' => [
        'photo' => 'صورة',
        'photos' => 'الصور',
        'type' => 'النوع',
        'body_area' => 'منطقة الجسم',
        'taken_at' => 'تاريخ الالتقاط',
        'taken_by' => 'التقطت بواسطة',
        'before' => 'قبل',
        'after' => 'بعد',
        'during' => 'أثناء',
        'consultation' => 'استشارة',
        'upload' => 'رفع صورة',
    ],

    // Notes
    'notes' => [
        'note' => 'ملاحظة',
        'notes' => 'الملاحظات',
        'type' => 'النوع',
        'clinical' => 'طبية',
        'administrative' => 'إدارية',
        'follow_up' => 'متابعة',
        'complaint' => 'شكوى',
        'content' => 'المحتوى',
        'created_by' => 'بواسطة',
        'add_note' => 'إضافة ملاحظة',
    ],

    // Gender options
    'gender_options' => [
        'male' => 'ذكر',
        'female' => 'أنثى',
        'other' => 'آخر',
    ],

    // Status options
    'status_options' => [
        'active' => 'نشط',
        'inactive' => 'غير نشط',
        'blocked' => 'محظور',
        'deceased' => 'متوفى',
    ],

    // Actions
    'actions' => [
        'create' => 'إضافة مريض',
        'edit' => 'تعديل المريض',
        'delete' => 'حذف المريض',
        'view' => 'عرض المريض',
        'export' => 'تصدير المرضى',
        'import' => 'استيراد المرضى',
        'merge' => 'دمج المرضى',
        'send_message' => 'إرسال رسالة',
        'book_appointment' => 'حجز موعد',
    ],

    // Messages
    'messages' => [
        'created' => 'تم إضافة المريض بنجاح.',
        'updated' => 'تم تحديث بيانات المريض بنجاح.',
        'deleted' => 'تم حذف المريض بنجاح.',
        'not_found' => 'المريض غير موجود.',
        'consent_required' => 'يجب توقيع نموذج الموافقة قبل العلاج.',
        'photo_uploaded' => 'تم رفع الصورة بنجاح.',
        'note_added' => 'تمت إضافة الملاحظة بنجاح.',
    ],

    // Filters
    'filters' => [
        'all' => 'جميع المرضى',
        'active' => 'المرضى النشطين',
        'new_this_month' => 'الجدد هذا الشهر',
        'returning' => 'المرضى العائدين',
        'with_upcoming' => 'لديهم مواعيد قادمة',
    ],
];
