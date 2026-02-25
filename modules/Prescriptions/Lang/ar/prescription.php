<?php

return [
    // Navigation & Labels
    'prescription' => 'وصفة طبية',
    'prescriptions' => 'الوصفات الطبية',
    'years' => 'سنة',

    // Sections
    'sections' => [
        'prescription_details' => 'تفاصيل الوصفة',
        'medications' => 'الأدوية',
        'status' => 'الحالة',
        'notes' => 'ملاحظات',
        'patient_info' => 'معلومات المريض',
        'prescription_info' => 'معلومات الوصفة',
        'dates' => 'التواريخ',
        'previous' => 'الوصفات السابقة',
        'new_prescription' => 'إنشاء وصفة جديدة',
    ],

    // Fields
    'fields' => [
        'prescription_number' => 'رقم الوصفة',
        'patient' => 'المريض',
        'prescriber' => 'الطبيب المعالج',
        'branch' => 'الفرع',
        'diagnosis' => 'التشخيص',
        'notes' => 'ملاحظات',
        'valid_until' => 'صالحة حتى',
        'status' => 'الحالة',
        'issued_at' => 'تاريخ الإصدار',
        'created' => 'تاريخ الإنشاء',
        'printed' => 'تمت الطباعة',
        'print_count' => 'عدد مرات الطباعة',
        'medications' => 'الأدوية',
        'medication_count' => 'عدد الأدوية',
        'phone' => 'الهاتف',
        'age' => 'العمر',
        'gender' => 'الجنس',
        'cancellation_reason' => 'سبب الإلغاء',

        // Medication fields
        'medication_name' => 'اسم الدواء',
        'generic_name' => 'الاسم العلمي',
        'form' => 'الشكل الدوائي',
        'dosage' => 'الجرعة',
        'dosage_unit' => 'الوحدة',
        'frequency' => 'التكرار',
        'duration' => 'المدة',
        'duration_unit' => 'وحدة المدة',
        'quantity' => 'الكمية',
        'route' => 'طريقة الاستخدام',
        'instructions' => 'التعليمات',
        'special_instructions' => 'تعليمات خاصة',
        'refills' => 'إعادة الصرف',
    ],

    // Statuses
    'statuses' => [
        'draft' => 'مسودة',
        'finalized' => 'معتمدة',
        'cancelled' => 'ملغاة',
    ],

    // Actions
    'actions' => [
        'add_medication' => 'إضافة دواء',
        'finalize' => 'اعتماد وطباعة',
        'print' => 'طباعة',
        'download' => 'تحميل',
        'cancel' => 'إلغاء',
        'save_draft' => 'حفظ كمسودة',
    ],

    // Filters
    'filters' => [
        'from' => 'من',
        'until' => 'إلى',
        'expired_only' => 'المنتهية فقط',
        'valid_only' => 'الصالحة فقط',
    ],

    // Modals
    'modals' => [
        'finalize_heading' => 'اعتماد الوصفة',
        'finalize_description' => 'هل أنت متأكد من اعتماد هذه الوصفة؟ لن تتمكن من تعديلها بعد الاعتماد.',
    ],

    // Messages
    'messages' => [
        'finalized' => 'تم اعتماد الوصفة بنجاح.',
        'cancelled' => 'تم إلغاء الوصفة.',
        'not_editable' => 'لا يمكن تعديل هذه الوصفة.',
        'no_medications' => 'يرجى إضافة دواء واحد على الأقل.',
        'error' => 'حدث خطأ أثناء إنشاء الوصفة.',
    ],

    // Placeholders
    'placeholders' => [
        'auto_generated' => 'يتم إنشاؤه تلقائياً',
        'additional_instructions' => 'تعليمات إضافية للمريض...',
        'no_diagnosis' => 'لم يتم تحديد تشخيص',
        'no_notes' => 'لا توجد ملاحظات',
        'not_issued' => 'لم تصدر بعد',
        'diagnosis' => 'أدخل التشخيص أو الحالة...',
        'special_notes' => 'أي ملاحظات خاصة لهذا الدواء...',
        'no_medications' => 'لم تتم إضافة أدوية بعد. انقر أدناه للإضافة.',
    ],

    // PDF
    'pdf' => [
        'title' => 'وصفة طبية',
        'phone' => 'الهاتف',
        'license' => 'رقم الترخيص',
        'date' => 'التاريخ',
        'patient_name' => 'اسم المريض',
        'patient_code' => 'رقم المريض',
        'age' => 'العمر',
        'gender' => 'الجنس',
        'diagnosis' => 'التشخيص',
        'medications' => 'الأدوية',
        'dosage' => 'الجرعة',
        'frequency' => 'التكرار',
        'duration' => 'المدة',
        'quantity' => 'الكمية',
        'route' => 'الطريقة',
        'instructions' => 'التعليمات',
        'refills' => 'إعادة الصرف',
        'special_note' => 'ملاحظة',
        'additional_notes' => 'ملاحظات إضافية',
        'valid_until' => 'صالحة حتى',
        'expired' => 'انتهت صلاحية هذه الوصفة',
        'signature' => 'التوقيع',
        'cancelled' => 'ملغاة',
        'footer_text' => 'هذه الوصفة صالحة للفترة المحددة فقط. يرجى استشارة طبيبك لأي استفسارات.',
    ],

    // Frequencies
    'frequencies' => [
        'once_daily' => 'مرة يومياً',
        'twice_daily' => 'مرتين يومياً',
        'three_times_daily' => '٣ مرات يومياً',
        'four_times_daily' => '٤ مرات يومياً',
        'every_4_hours' => 'كل ٤ ساعات',
        'every_6_hours' => 'كل ٦ ساعات',
        'every_8_hours' => 'كل ٨ ساعات',
        'every_12_hours' => 'كل ١٢ ساعة',
        'as_needed' => 'عند الحاجة',
        'weekly' => 'أسبوعياً',
        'twice_weekly' => 'مرتين أسبوعياً',
        'monthly' => 'شهرياً',
        'other' => 'أخرى',
    ],

    // Routes
    'routes' => [
        'oral' => 'عن طريق الفم',
        'topical' => 'موضعي',
        'injection' => 'حقن',
        'intravenous' => 'وريدي',
        'intramuscular' => 'عضلي',
        'subcutaneous' => 'تحت الجلد',
        'inhalation' => 'استنشاق',
        'sublingual' => 'تحت اللسان',
        'transdermal' => 'عبر الجلد',
        'rectal' => 'شرجي',
        'ophthalmic' => 'للعين',
        'otic' => 'للأذن',
        'nasal' => 'للأنف',
        'vaginal' => 'مهبلي',
        'other' => 'أخرى',
    ],

    // Forms
    'forms' => [
        'tablet' => 'أقراص',
        'capsule' => 'كبسولات',
        'syrup' => 'شراب',
        'suspension' => 'معلق',
        'solution' => 'محلول',
        'cream' => 'كريم',
        'ointment' => 'مرهم',
        'gel' => 'جل',
        'lotion' => 'لوشن',
        'drops' => 'قطرات',
        'injection' => 'حقن',
        'inhaler' => 'بخاخ',
        'patch' => 'لصقات',
        'suppository' => 'تحاميل',
        'powder' => 'بودرة',
        'spray' => 'رذاذ',
        'other' => 'أخرى',
    ],

    // Instructions
    'instructions' => [
        'before_meal' => 'قبل الأكل',
        'after_meal' => 'بعد الأكل',
        'with_food' => 'مع الطعام',
        'empty_stomach' => 'على معدة فارغة',
        'at_bedtime' => 'قبل النوم',
        'in_morning' => 'في الصباح',
        'with_water' => 'مع الكثير من الماء',
        'without_water' => 'بدون ماء',
        'chew' => 'يمضغ قبل البلع',
        'swallow_whole' => 'يبلع كاملاً',
        'dissolve' => 'يذوب في الفم',
        'apply_affected' => 'يوضع على المنطقة المصابة',
        'as_directed' => 'حسب التوجيهات',
    ],

    // Duration units
    'duration_units' => [
        'days' => 'أيام',
        'weeks' => 'أسابيع',
        'months' => 'أشهر',
    ],

    // Dosage units
    'dosage_units' => [
        'mg' => 'ملغ',
        'g' => 'غ',
        'ml' => 'مل',
        'mcg' => 'ميكروغرام',
        'IU' => 'وحدة دولية',
        'unit' => 'وحدة',
        'puff' => 'بخة',
        'drop' => 'قطرة',
        'application' => 'استخدام',
    ],

    // Medicine Catalog
    'medicine_catalog' => 'كتالوج الأدوية',
    'medicine' => 'دواء',
    'medicines' => 'الأدوية',

    'catalog' => [
        'quick_add' => 'إضافة سريعة من الكتالوج',
        'select_medicine' => 'اختر دواء من الكتالوج...',
        'quick_add_help' => 'اختر دواء لملء تفاصيل الوصفة تلقائياً',
        'create_medicine' => 'إضافة دواء جديد',
        'sections' => [
            'basic_info' => 'المعلومات الأساسية',
            'strength' => 'التركيز والشكل',
            'default_prescription' => 'إعدادات الوصفة الافتراضية',
            'default_prescription_desc' => 'سيتم تعبئة هذه القيم تلقائياً عند اختيار هذا الدواء',
            'clinical_info' => 'المعلومات السريرية',
            'status' => 'الحالة والإعدادات',
        ],
        'fields' => [
            'brand_name' => 'الاسم التجاري',
            'generic_name' => 'الاسم العلمي',
            'manufacturer' => 'الشركة المصنعة',
            'category' => 'الفئة',
            'drug_class' => 'التصنيف الدوائي',
            'strength' => 'التركيز',
            'strength_unit' => 'الوحدة',
            'description' => 'الوصف',
            'indications' => 'دواعي الاستعمال',
            'contraindications' => 'موانع الاستعمال',
            'side_effects' => 'الآثار الجانبية',
            'warnings' => 'التحذيرات',
            'is_active' => 'نشط',
            'is_controlled' => 'مادة خاضعة للرقابة',
            'is_controlled_help' => 'حدد إذا كان هذا الدواء مادة خاضعة للرقابة تتطلب معاملة خاصة',
            'requires_prescription' => 'يتطلب وصفة طبية',
            'is_system' => 'افتراضي من النظام',
        ],
    ],

    // Categories
    'categories' => [
        'antibiotic' => 'مضاد حيوي',
        'analgesic' => 'مسكن للألم',
        'antipyretic' => 'خافض للحرارة',
        'anti_inflammatory' => 'مضاد للالتهاب',
        'antihistamine' => 'مضاد للهيستامين / الحساسية',
        'antacid' => 'مضاد للحموضة',
        'antihypertensive' => 'خافض لضغط الدم',
        'antidiabetic' => 'مضاد للسكري',
        'cardiovascular' => 'القلب والأوعية الدموية',
        'respiratory' => 'الجهاز التنفسي',
        'dermatological' => 'الجلدية',
        'ophthalmic' => 'العيون',
        'otic' => 'الأذن',
        'vitamin' => 'الفيتامينات والمكملات',
        'muscle_relaxant' => 'مرخي للعضلات',
        'antidepressant' => 'مضاد للاكتئاب',
        'anxiolytic' => 'مضاد للقلق',
        'antiemetic' => 'مضاد للغثيان',
        'laxative' => 'ملين',
        'antidiarrheal' => 'مضاد للإسهال',
        'antifungal' => 'مضاد للفطريات',
        'antiviral' => 'مضاد للفيروسات',
        'hormone' => 'هرمونات',
        'immunosuppressant' => 'مثبط للمناعة',
        'other' => 'أخرى',
    ],
];
