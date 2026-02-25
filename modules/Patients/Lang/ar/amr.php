<?php

return [
    // AMR Summary
    'amr_summary' => 'ملخص مقاومة المضادات الحيوية',
    'last_test_date' => 'تاريخ آخر فحص',
    'mdro_flags' => 'مقاومة متعددة الأدوية',
    'known_organisms' => 'الكائنات الدقيقة المعروفة',
    'known_resistances' => 'المقاومات المعروفة',
    'known_sensitivities' => 'الحساسيات المعروفة',
    'alert_notes' => 'ملاحظات التنبيه',
    'has_critical_resistance' => 'مقاومة حرجة',

    // Test Details
    'date' => 'التاريخ',
    'specimen' => 'العينة',
    'organism' => 'الكائن الدقيق',
    'mdro' => 'مقاومة متعددة',
    'resistant' => 'مقاوم',
    'sensitive' => 'حساس',
    'verified' => 'موثق',
    'laboratory' => 'المختبر',

    // Form Fields
    'specimen_information' => 'معلومات العينة',
    'collection_date' => 'تاريخ الجمع',
    'result_date' => 'تاريخ النتيجة',
    'specimen_source' => 'مصدر العينة',
    'specimen_site' => 'موقع العينة',
    'specimen_site_placeholder' => 'مثال: الذراع الأيمن، جرح الساق اليسرى',
    'lab_accession_number' => 'رقم المختبر',
    'laboratory_name' => 'اسم المختبر',

    // Organism
    'organism_identification' => 'تحديد الكائن الدقيق',
    'organism_name' => 'اسم الكائن الدقيق',
    'organism_code' => 'رمز الكائن الدقيق',
    'custom_organism' => 'اسم كائن مخصص',
    'is_mdro' => 'كائن مقاوم لأدوية متعددة (MDRO)',
    'is_mdro_help' => 'حدد إذا كان هذا الكائن مقاوماً لعدة مضادات حيوية',
    'mdro_types' => 'أنواع المقاومة المتعددة',

    // Antibiotic Results
    'antibiotic_results' => 'نتائج حساسية المضادات الحيوية',
    'antibiotic' => 'المضاد الحيوي',
    'sensitivity' => 'الحساسية',
    'mic' => 'قيمة MIC',
    'mic_unit' => 'وحدة MIC',
    'add_antibiotic_result' => 'إضافة نتيجة مضاد حيوي',

    // Clinical Information
    'clinical_information' => 'المعلومات السريرية',
    'clinical_notes' => 'الملاحظات السريرية',
    'recommendations' => 'توصيات العلاج',

    // Actions
    'verify' => 'توثيق',
    'verify_test' => 'توثيق فحص AMR',
    'verify_description' => 'هل أنت متأكد من توثيق نتيجة هذا الفحص؟ سيتم تحديده كموثق سريرياً.',
    'test_verified' => 'تم توثيق فحص AMR بنجاح',

    // Filters
    'mdro_only' => 'المقاومة المتعددة فقط',

    // Specimen Sources
    'specimen_sources' => [
        'blood' => 'دم',
        'urine' => 'بول',
        'sputum' => 'بلغم',
        'wound' => 'جرح',
        'stool' => 'براز',
        'csf' => 'السائل النخاعي',
        'respiratory' => 'تنفسي',
        'tissue' => 'نسيج',
        'abscess' => 'خراج',
        'catheter' => 'قسطرة',
        'swab' => 'مسحة',
        'fluid' => 'سائل جسدي',
        'biopsy' => 'خزعة',
        'joint' => 'سائل المفصل',
        'eye' => 'عين',
        'other' => 'أخرى',
    ],

    // Sensitivity Levels
    'sensitivity_levels' => [
        'S' => 'حساس (S)',
        'I' => 'متوسط (I)',
        'R' => 'مقاوم (R)',
        'SDD' => 'حساس حسب الجرعة (SDD)',
        'NS' => 'غير حساس (NS)',
    ],

    // MDRO Types
    'mdro_types_list' => [
        'MRSA' => 'MRSA (المكورات العنقودية المقاومة للميثيسيلين)',
        'VRE' => 'VRE (المكورات المعوية المقاومة للفانكومايسين)',
        'ESBL' => 'ESBL (بيتا لاكتاماز واسعة الطيف)',
        'CRE' => 'CRE (البكتيريا المعوية المقاومة للكاربابينيم)',
        'MDRO' => 'كائن مقاوم لأدوية متعددة',
        'MRAB' => 'أسينيتوباكتر مقاوم لأدوية متعددة',
        'CRPA' => 'الزائفة الزنجارية المقاومة للكاربابينيم',
        'PRSP' => 'المكورات الرئوية المقاومة للبنسلين',
        'C_DIFF' => 'المطثية العسيرة (CDI)',
    ],

    // Antibiotic Classes
    'antibiotic_classes' => [
        'penicillins' => 'البنسلينات',
        'cephalosporins' => 'السيفالوسبورينات',
        'carbapenems' => 'الكاربابينيمات',
        'aminoglycosides' => 'الأمينوغليكوزيدات',
        'fluoroquinolones' => 'الفلوروكينولونات',
        'macrolides' => 'الماكروليدات',
        'glycopeptides' => 'الغليكوببتيدات',
        'tetracyclines' => 'التتراسيكلينات',
        'sulfonamides' => 'السلفوناميدات',
        'other' => 'أخرى',
    ],
];
