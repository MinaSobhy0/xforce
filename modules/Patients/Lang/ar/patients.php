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
        'phone_country_code' => 'كود الدولة',
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
        'portal_access_enabled' => 'الوصول إلى بوابة المريض',
        'tags' => 'الوسوم',
        'notes' => 'ملاحظات',
        'branch' => 'الفرع',
        'created_at' => 'تاريخ التسجيل',
        'last_visit' => 'آخر زيارة',
    ],

    // Help Text
    'help' => [
        'portal_access_enabled' => 'السماح لهذا المريض بتسجيل الدخول وحجز المواعيد عبر بوابة المريض',
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
        'sun_response' => 'استجابة الشمس',
        'skin_characteristics' => 'خصائص البشرة',
        'sun_exposure' => 'مستوى التعرض للشمس',
        'sun_levels' => [
            'minimal' => 'قليل',
            'moderate' => 'متوسط',
            'high' => 'عالي',
        ],
    ],

    // Sections
    'sections' => [
        'skin_assessment' => 'تقييم البشرة',
        'personal_info' => 'المعلومات الشخصية',
        'contact_info' => 'معلومات الاتصال',
        'medical_history' => 'التاريخ الطبي',
    ],

    // Fitzpatrick Types
    'fitzpatrick' => [
        'type_i' => 'النوع الأول',
        'type_ii' => 'النوع الثاني',
        'type_iii' => 'النوع الثالث',
        'type_iv' => 'النوع الرابع',
        'type_v' => 'النوع الخامس',
        'type_vi' => 'النوع السادس',
        'desc_i' => 'بشرة فاتحة جداً، تحترق دائماً، لا تسمر أبداً',
        'desc_ii' => 'بشرة فاتحة، تحترق بسهولة، تسمر قليلاً',
        'desc_iii' => 'بشرة متوسطة، تحترق أحياناً، تسمر بشكل موحد',
        'desc_iv' => 'بشرة زيتونية، نادراً تحترق، تسمر بسهولة',
        'desc_v' => 'بشرة بنية، نادراً جداً تحترق',
        'desc_vi' => 'بشرة بنية داكنة/سوداء، لا تحترق أبداً',
        'char_i' => 'بيضاء فاتحة جداً، غالباً مع نمش',
        'char_ii' => 'بيضاء إلى بيج فاتح',
        'char_iii' => 'بيج إلى بني فاتح',
        'char_iv' => 'بني فاتح إلى زيتوني',
        'char_v' => 'بنية',
        'char_vi' => 'بني داكن إلى أسود',
        'sun_i' => 'تحترق دائماً، لا تسمر أبداً',
        'sun_ii' => 'تحترق بسهولة، تسمر بصعوبة',
        'sun_iii' => 'أحياناً حروق خفيفة، تسمر بشكل موحد',
        'sun_iv' => 'نادراً تحترق، تسمر بسهولة',
        'sun_v' => 'نادراً جداً تحترق، تسمر بسهولة كبيرة',
        'sun_vi' => 'لا تحترق أبداً، تصبغ عميق',
    ],

    // Consent Forms
    'consent' => [
        'form' => 'نموذج موافقة',
        'forms' => 'نماذج الموافقة',
        'signed_at' => 'تاريخ التوقيع',
        'signed_by' => 'التوقيع بواسطة',
        'witness' => 'الشاهد',
        'signature' => 'التوقيع',
        'typed_signature' => 'التوقيع المكتوب',
        'valid_until' => 'صالح حتى',
        'download' => 'تحميل',
        'sign_new' => 'توقيع نموذج جديد',
        'sign_here' => 'وقع هنا',
        'clear_signature' => 'مسح',
        'signature_captured' => 'تم التقاط التوقيع',
        'awaiting_signature' => 'في انتظار التوقيع',
        'signature_instruction' => 'ارسم توقيعك باستخدام الماوس أو إصبعك. سيتم حفظ التوقيع تلقائياً.',
        'type_full_name' => 'اكتب اسمك الكامل',
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
        'medical_profile' => 'الملف الطبي',
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
        'inactive_days' => 'غير نشط (+:days يوم)',
    ],

    // إحصائيات
    'stats' => [
        'total_registered' => 'إجمالي المرضى المسجلين',
        'growth_from_last_month' => ':growth% من الشهر الماضي',
        'of_total' => ':percent% من الإجمالي',
        'recent_visitors' => 'الزوار الأخيرون',
        'visited_last_days' => 'زاروا خلال آخر :days يوم',
    ],

    // الرصيد
    'balance' => [
        'title' => 'الرصيد',
        'owes' => 'مستحق',
        'credit' => 'رصيد دائن',
        'settled' => 'مسدد',
        'outstanding_balance' => 'الرصيد المستحق',
        'patient_has_balance' => 'هذا المريض لديه رصيد مستحق',
        'patient_has_credit' => 'هذا المريض لديه رصيد دائن',
        'pay_balance' => 'دفع الرصيد',
        'view_ledger' => 'عرض كشف الحساب',
    ],

    // التحقق
    'validation' => [
        'phone_exists' => 'يوجد مريض بهذا الرقم بالفعل.',
    ],
];
