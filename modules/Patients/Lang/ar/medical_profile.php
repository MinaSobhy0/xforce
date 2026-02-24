<?php

return [
    'navigation_label' => 'الملف الطبي',
    'title' => 'الملف الطبي',
    'heading' => 'الملف الطبي',

    'tabs' => [
        'overview' => 'نظرة عامة',
        'allergies' => 'الحساسية',
        'medications' => 'الأدوية',
        'contraindications' => 'موانع الاستعمال',
        'history' => 'التاريخ الطبي',
        'skin' => 'تقييم البشرة',
        'lifestyle' => 'نمط الحياة',
    ],

    'sections' => [
        'basic_info' => 'المعلومات الأساسية',
        'quick_stats' => 'إحصائيات سريعة',
        'critical_alerts' => 'تنبيهات حرجة',
        'review_status' => 'حالة المراجعة',
        'allergies' => 'الحساسية',
        'medications' => 'الأدوية الحالية',
        'contraindications' => 'موانع الاستعمال',
        'medical_history' => 'التاريخ الطبي',
        'skin_assessment' => 'تقييم البشرة',
        'lifestyle' => 'معلومات نمط الحياة',
    ],

    'fields' => [
        'blood_type' => 'فصيلة الدم',
        'fitzpatrick_type' => 'نوع البشرة (فيتزباتريك)',
        'is_pregnant' => 'حامل',
        'is_breastfeeding' => 'مرضعة',
    ],

    // Blood Types
    'blood_types' => [
        'A+' => 'A+',
        'A-' => 'A-',
        'B+' => 'B+',
        'B-' => 'B-',
        'AB+' => 'AB+',
        'AB-' => 'AB-',
        'O+' => 'O+',
        'O-' => 'O-',
    ],

    // Fitzpatrick Types
    'fitzpatrick_types' => [
        '1' => 'النوع الأول',
        '2' => 'النوع الثاني',
        '3' => 'النوع الثالث',
        '4' => 'النوع الرابع',
        '5' => 'النوع الخامس',
        '6' => 'النوع السادس',
    ],

    'fitzpatrick_descriptions' => [
        '1' => 'بشرة فاتحة جداً، تحترق دائماً، لا تسمر أبداً',
        '2' => 'بشرة فاتحة، تحترق بسهولة، تسمر قليلاً',
        '3' => 'بشرة متوسطة، تحترق أحياناً، تسمر تدريجياً',
        '4' => 'بشرة زيتونية، نادراً تحترق، تسمر بسهولة',
        '5' => 'بشرة بنية، نادراً جداً تحترق، تسمر بسهولة كبيرة',
        '6' => 'بشرة بنية داكنة/سوداء، لا تحترق أبداً، تسمر بسهولة كبيرة',
    ],

    // Allergy Section
    'allergy' => [
        'type' => 'نوع الحساسية',
        'allergen' => 'المادة المسببة',
        'severity' => 'الشدة',
        'reaction' => 'رد الفعل',
        'discovered_date' => 'تاريخ الاكتشاف',
        'is_confirmed' => 'مؤكدة',
        'show_alert' => 'إظهار التنبيه',
    ],

    'allergy_types' => [
        'drug' => 'دواء/عقار',
        'food' => 'طعام',
        'environmental' => 'بيئية',
        'topical' => 'موضعية/منتجات البشرة',
        'metal' => 'معادن',
        'latex' => 'لاتكس',
        'other' => 'أخرى',
    ],

    'allergy_severities' => [
        'mild' => 'خفيفة',
        'moderate' => 'متوسطة',
        'severe' => 'شديدة',
        'life_threatening' => 'مهددة للحياة',
    ],

    // Medication Section
    'medication' => [
        'name' => 'اسم الدواء',
        'generic_name' => 'الاسم العلمي',
        'dosage' => 'الجرعة',
        'frequency' => 'التكرار',
        'route' => 'طريقة الإعطاء',
        'reason' => 'السبب',
        'start_date' => 'تاريخ البدء',
        'end_date' => 'تاريخ الانتهاء',
        'is_ongoing' => 'مستمر',
        'affects_treatment' => 'يؤثر على علاج الليزر/IPL',
        'treatment_implications' => 'التأثيرات على العلاج',
        'prescribing_doctor' => 'الطبيب المعالج',
        'is_otc' => 'بدون وصفة طبية',
    ],

    'medication_routes' => [
        'oral' => 'فموي',
        'topical' => 'موضعي',
        'injection' => 'حقن',
        'inhalation' => 'استنشاق',
        'sublingual' => 'تحت اللسان',
        'transdermal' => 'عبر الجلد',
        'other' => 'أخرى',
    ],

    'medication_frequencies' => [
        'once_daily' => 'مرة يومياً',
        'twice_daily' => 'مرتين يومياً',
        'three_times_daily' => '3 مرات يومياً',
        'four_times_daily' => '4 مرات يومياً',
        'as_needed' => 'عند الحاجة',
        'weekly' => 'أسبوعياً',
        'other' => 'أخرى',
    ],

    // Contraindication Section
    'contraindication' => [
        'type' => 'النوع',
        'name' => 'الاسم',
        'description' => 'الوصف',
        'affected_services' => 'الخدمات المتأثرة',
        'start_date' => 'تاريخ البدء',
        'end_date' => 'تاريخ الانتهاء',
        'is_active' => 'نشط',
        'source' => 'المصدر',
        'block_booking' => 'منع حجز المواعيد',
        'show_booking_alert' => 'إظهار تنبيه الحجز',
    ],

    'contraindication_types' => [
        'absolute' => 'مطلق (العلاج ممنوع)',
        'relative' => 'نسبي (العلاج بحذر)',
        'temporary' => 'مؤقت (محدد بوقت)',
    ],

    'contraindication_sources' => [
        'patient_reported' => 'أبلغ عنه المريض',
        'doctor_identified' => 'حدده الطبيب',
        'system_derived' => 'مشتق من النظام',
    ],

    // Medical History Section
    'history' => [
        'type' => 'النوع',
        'name' => 'الحالة/الحدث',
        'description' => 'الوصف',
        'onset_date' => 'تاريخ البدء',
        'resolved_date' => 'تاريخ الشفاء',
        'is_ongoing' => 'حالة مستمرة',
        'severity' => 'الشدة',
        'family_relationship' => 'صلة القرابة',
        'affects_treatment' => 'يؤثر على العلاج',
        'treatment_implications' => 'التأثيرات على العلاج',
        'verified' => 'تم التحقق بواسطة الطبيب',
    ],

    'history_types' => [
        'medical_condition' => 'حالة طبية',
        'surgery' => 'عملية جراحية',
        'hospitalization' => 'دخول مستشفى',
        'family_history' => 'تاريخ عائلي',
        'social_history' => 'تاريخ اجتماعي',
    ],

    'history_severities' => [
        'mild' => 'خفيفة',
        'moderate' => 'متوسطة',
        'severe' => 'شديدة',
    ],

    'family_relationships' => [
        'mother' => 'الأم',
        'father' => 'الأب',
        'sibling' => 'أخ/أخت',
        'grandparent' => 'جد/جدة',
        'aunt_uncle' => 'عم/عمة/خال/خالة',
        'other' => 'أخرى',
    ],

    // Skin Assessment Section
    'skin' => [
        'fitzpatrick_type' => 'نوع فيتزباتريك',
        'skin_type' => 'نوع البشرة',
        'sensitivity' => 'الحساسية',
        'texture' => 'الملمس',
        'pore_size' => 'حجم المسام',
        'skin_tone' => 'لون البشرة',
        'hydration_level' => 'مستوى الترطيب',
        'elasticity' => 'المرونة',
        'pigmentation' => 'التصبغ',
        'acne_severity' => 'شدة حب الشباب',
        'aging_level' => 'مستوى الشيخوخة',
        'sun_damage_level' => 'مستوى أضرار الشمس',
        'current_conditions' => 'الحالات الحالية',
        'previous_conditions' => 'الحالات السابقة',
        'aging_signs' => 'علامات الشيخوخة',
        'areas_of_concern' => 'مناطق الاهتمام',
        'patient_goals' => 'أهداف المريض',
        'clinical_observations' => 'الملاحظات السريرية',
        'recommendations' => 'التوصيات',
        'assessed_by' => 'تم التقييم بواسطة',
        'assessed_date' => 'تاريخ التقييم',
    ],

    'skin_oily_types' => [
        'dry' => 'جافة',
        'normal' => 'عادية',
        'oily' => 'دهنية',
        'combination' => 'مختلطة',
    ],

    'skin_sensitivity_levels' => [
        'low' => 'منخفضة',
        'normal' => 'عادية',
        'high' => 'عالية',
        'very_high' => 'عالية جداً',
    ],

    'skin_texture_levels' => [
        'smooth' => 'ناعمة',
        'slightly_rough' => 'خشنة قليلاً',
        'rough' => 'خشنة',
        'very_rough' => 'خشنة جداً',
    ],

    'skin_pore_sizes' => [
        'small' => 'صغيرة',
        'medium' => 'متوسطة',
        'large' => 'كبيرة',
        'very_large' => 'كبيرة جداً',
    ],

    'skin_tone_levels' => [
        'even' => 'موحدة',
        'slightly_uneven' => 'غير موحدة قليلاً',
        'uneven' => 'غير موحدة',
        'very_uneven' => 'غير موحدة جداً',
    ],

    'skin_aging_levels' => [
        'none' => 'لا يوجد',
        'early' => 'علامات مبكرة',
        'moderate' => 'متوسطة',
        'advanced' => 'متقدمة',
    ],

    'skin_sun_damage_levels' => [
        'none' => 'لا يوجد',
        'mild' => 'خفيف',
        'moderate' => 'متوسط',
        'severe' => 'شديد',
    ],

    'skin_conditions' => [
        'acne' => 'حب الشباب',
        'rosacea' => 'الوردية',
        'eczema' => 'الإكزيما',
        'psoriasis' => 'الصدفية',
        'melasma' => 'الكلف',
        'hyperpigmentation' => 'فرط التصبغ',
        'vitiligo' => 'البهاق',
        'scarring' => 'الندوب',
        'seborrheic_dermatitis' => 'التهاب الجلد الدهني',
        'keratosis_pilaris' => 'التقرن الشعري',
    ],

    'skin_aging_signs' => [
        'fine_lines' => 'خطوط رفيعة',
        'wrinkles' => 'تجاعيد',
        'sagging' => 'ترهل',
        'volume_loss' => 'فقدان الحجم',
        'age_spots' => 'بقع الشيخوخة',
        'dull_skin' => 'بشرة باهتة',
        'neck_lines' => 'خطوط الرقبة',
        'crow_feet' => 'خطوط العين',
    ],

    'skin_areas_of_concern' => [
        'face' => 'الوجه',
        'forehead' => 'الجبهة',
        'cheeks' => 'الخدود',
        'nose' => 'الأنف',
        'chin' => 'الذقن',
        'neck' => 'الرقبة',
        'chest' => 'الصدر',
        'hands' => 'اليدين',
        'back' => 'الظهر',
    ],

    // Lifestyle Section
    'lifestyle' => [
        'smoking_status' => 'حالة التدخين',
        'smoking_frequency' => 'معدل التدخين',
        'smoking_years' => 'سنوات التدخين',
        'smoking_quit_date' => 'تاريخ الإقلاع',
        'alcohol_status' => 'استهلاك الكحول',
        'alcohol_frequency' => 'معدل الكحول',
        'exercise_level' => 'مستوى التمارين',
        'exercise_details' => 'تفاصيل التمارين',
        'sun_exposure' => 'التعرض للشمس',
        'uses_sunscreen' => 'يستخدم واقي الشمس',
        'uses_tanning_beds' => 'يستخدم أسرة التسمير',
        'sleep_hours' => 'ساعات النوم',
        'sleep_issues' => 'مشاكل النوم',
        'diet_type' => 'نوع الحمية',
        'dietary_restrictions' => 'القيود الغذائية',
        'occupational_exposures' => 'التعرضات المهنية',
    ],

    'smoking_status_options' => [
        'never' => 'لم يدخن أبداً',
        'former' => 'مدخن سابق',
        'current' => 'مدخن حالي',
    ],

    'alcohol_status_options' => [
        'never' => 'لا يشرب أبداً',
        'occasional' => 'أحياناً',
        'regular' => 'منتظم',
        'heavy' => 'كثير',
    ],

    'exercise_level_options' => [
        'sedentary' => 'خامل',
        'light' => 'خفيف (1-2 أيام/أسبوع)',
        'moderate' => 'متوسط (3-4 أيام/أسبوع)',
        'active' => 'نشط (5+ أيام/أسبوع)',
        'very_active' => 'نشط جداً (يومياً)',
    ],

    'sun_exposure_options' => [
        'minimal' => 'قليل',
        'moderate' => 'متوسط',
        'frequent' => 'متكرر',
        'excessive' => 'مفرط',
    ],

    // Stats
    'stats' => [
        'allergies' => 'الحساسية',
        'medications' => 'الأدوية النشطة',
        'contraindications' => 'موانع الاستعمال النشطة',
        'conditions' => 'الحالات الطبية',
        'skin_assessments' => 'تقييمات البشرة',
    ],

    // Actions
    'actions' => [
        'back_to_patient' => 'العودة للمريض',
        'mark_reviewed' => 'وضع علامة كمراجع',
        'save_profile' => 'حفظ الملف',
        'add_allergy' => 'إضافة حساسية',
        'add_medication' => 'إضافة دواء',
        'add_contraindication' => 'إضافة مانع',
        'add_history' => 'إضافة تاريخ',
        'add_skin_assessment' => 'إضافة تقييم البشرة',
        'save_lifestyle' => 'حفظ نمط الحياة',
        'delete' => 'حذف',
        'edit' => 'تعديل',
        'view' => 'عرض',
    ],

    // Messages
    'messages' => [
        'patient_required' => 'معرف المريض مطلوب',
        'patient_not_found' => 'المريض غير موجود',
        'profile_saved' => 'تم حفظ الملف بنجاح',
        'marked_reviewed' => 'تم وضع علامة كمراجع',
        'allergy_added' => 'تمت إضافة الحساسية بنجاح',
        'allergy_deleted' => 'تم حذف الحساسية',
        'medication_added' => 'تمت إضافة الدواء بنجاح',
        'medication_deleted' => 'تم حذف الدواء',
        'contraindication_added' => 'تمت إضافة مانع الاستعمال بنجاح',
        'contraindication_deleted' => 'تم حذف مانع الاستعمال',
        'history_added' => 'تمت إضافة التاريخ الطبي بنجاح',
        'history_deleted' => 'تم حذف التاريخ الطبي',
        'skin_assessment_added' => 'تمت إضافة تقييم البشرة بنجاح',
        'skin_assessment_deleted' => 'تم حذف تقييم البشرة',
        'lifestyle_saved' => 'تم حفظ معلومات نمط الحياة',
    ],

    // Labels
    'alerts_count' => ':count تنبيه(ات)',
    'needs_review' => 'يحتاج مراجعة',
    'last_reviewed' => 'آخر مراجعة',
    'never_reviewed' => 'لم تتم مراجعتها',
    'latest' => 'الأحدث',
    'recorded' => 'مسجل',
    'active' => 'نشط',
    'ongoing' => 'مستمر',
    'affects_treatment' => 'يؤثر على العلاج',
    'blocks_booking' => 'يمنع الحجز',
    'treatment_considerations' => 'اعتبارات العلاج',

    // Empty States
    'no_allergies' => 'لا توجد حساسية مسجلة',
    'no_medications' => 'لا توجد أدوية مسجلة',
    'no_contraindications' => 'لا توجد موانع مسجلة',
    'no_history' => 'لا يوجد تاريخ طبي مسجل',
    'no_skin_assessment' => 'لم يتم إجراء تقييم للبشرة',
    'no_lifestyle' => 'لا توجد معلومات نمط حياة مسجلة',
];
