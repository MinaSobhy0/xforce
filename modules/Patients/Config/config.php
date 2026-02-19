<?php

return [
    'name' => 'Patients',

    /*
    |--------------------------------------------------------------------------
    | Patient Code Settings
    |--------------------------------------------------------------------------
    */
    'auto_generate_code' => env('PATIENTS_AUTO_GENERATE_CODE', true),
    'code_prefix' => env('PATIENTS_CODE_PREFIX', 'PAT'),

    /*
    |--------------------------------------------------------------------------
    | Consent Settings
    |--------------------------------------------------------------------------
    */
    'require_consent_before_treatment' => env('PATIENTS_REQUIRE_CONSENT', true),

    /*
    |--------------------------------------------------------------------------
    | Photo Settings
    |--------------------------------------------------------------------------
    */
    'photo_retention_days' => env('PATIENTS_PHOTO_RETENTION_DAYS', 365),
    'photo_max_size_kb' => env('PATIENTS_PHOTO_MAX_SIZE', 5120), // 5MB
    'allowed_photo_types' => ['jpg', 'jpeg', 'png', 'webp'],

    /*
    |--------------------------------------------------------------------------
    | Medical History Settings
    |--------------------------------------------------------------------------
    */
    'fitzpatrick_types' => [
        'I' => ['en' => 'Type I - Very fair skin, always burns', 'ar' => 'النوع الأول - بشرة فاتحة جداً، تحترق دائماً'],
        'II' => ['en' => 'Type II - Fair skin, burns easily', 'ar' => 'النوع الثاني - بشرة فاتحة، تحترق بسهولة'],
        'III' => ['en' => 'Type III - Medium skin, sometimes burns', 'ar' => 'النوع الثالث - بشرة متوسطة، تحترق أحياناً'],
        'IV' => ['en' => 'Type IV - Olive skin, rarely burns', 'ar' => 'النوع الرابع - بشرة زيتونية، نادراً ما تحترق'],
        'V' => ['en' => 'Type V - Brown skin, very rarely burns', 'ar' => 'النوع الخامس - بشرة سمراء، نادراً جداً ما تحترق'],
        'VI' => ['en' => 'Type VI - Dark brown/black skin, never burns', 'ar' => 'النوع السادس - بشرة داكنة/سوداء، لا تحترق'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Note Types
    |--------------------------------------------------------------------------
    */
    'note_types' => [
        'clinical' => ['en' => 'Clinical Note', 'ar' => 'ملاحظة طبية'],
        'administrative' => ['en' => 'Administrative Note', 'ar' => 'ملاحظة إدارية'],
        'follow_up' => ['en' => 'Follow-up Note', 'ar' => 'ملاحظة متابعة'],
        'complaint' => ['en' => 'Complaint', 'ar' => 'شكوى'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Referral Sources
    |--------------------------------------------------------------------------
    */
    'referral_sources' => [
        'walk_in' => ['en' => 'Walk-in', 'ar' => 'زيارة مباشرة'],
        'social_media' => ['en' => 'Social Media', 'ar' => 'وسائل التواصل'],
        'google' => ['en' => 'Google Search', 'ar' => 'بحث جوجل'],
        'friend_referral' => ['en' => 'Friend Referral', 'ar' => 'إحالة صديق'],
        'patient_referral' => ['en' => 'Patient Referral', 'ar' => 'إحالة مريض'],
        'doctor_referral' => ['en' => 'Doctor Referral', 'ar' => 'إحالة طبيب'],
        'advertisement' => ['en' => 'Advertisement', 'ar' => 'إعلان'],
        'other' => ['en' => 'Other', 'ar' => 'أخرى'],
    ],
];
