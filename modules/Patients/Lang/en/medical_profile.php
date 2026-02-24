<?php

return [
    'navigation_label' => 'Medical Profile',
    'title' => 'Medical Profile',
    'heading' => 'Medical Profile',

    'tabs' => [
        'overview' => 'Overview',
        'allergies' => 'Allergies',
        'medications' => 'Medications',
        'contraindications' => 'Contraindications',
        'history' => 'Medical History',
        'skin' => 'Skin Assessment',
        'lifestyle' => 'Lifestyle',
    ],

    'sections' => [
        'basic_info' => 'Basic Information',
        'quick_stats' => 'Quick Stats',
        'critical_alerts' => 'Critical Alerts',
        'review_status' => 'Review Status',
        'allergies' => 'Allergies',
        'medications' => 'Current Medications',
        'contraindications' => 'Contraindications',
        'medical_history' => 'Medical History',
        'skin_assessment' => 'Skin Assessment',
        'lifestyle' => 'Lifestyle Information',
    ],

    'fields' => [
        'blood_type' => 'Blood Type',
        'fitzpatrick_type' => 'Fitzpatrick Skin Type',
        'is_pregnant' => 'Pregnant',
        'is_breastfeeding' => 'Breastfeeding',
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
        '1' => 'Type I',
        '2' => 'Type II',
        '3' => 'Type III',
        '4' => 'Type IV',
        '5' => 'Type V',
        '6' => 'Type VI',
    ],

    'fitzpatrick_descriptions' => [
        '1' => 'Very fair skin, always burns, never tans',
        '2' => 'Fair skin, burns easily, tans minimally',
        '3' => 'Medium skin, sometimes burns, tans gradually',
        '4' => 'Olive skin, rarely burns, tans easily',
        '5' => 'Brown skin, very rarely burns, tans very easily',
        '6' => 'Dark brown/black skin, never burns, tans very easily',
    ],

    // Allergy Section
    'allergy' => [
        'type' => 'Allergy Type',
        'allergen' => 'Allergen',
        'severity' => 'Severity',
        'reaction' => 'Reaction',
        'discovered_date' => 'Discovered Date',
        'is_confirmed' => 'Confirmed',
        'show_alert' => 'Show Alert',
    ],

    'allergy_types' => [
        'drug' => 'Drug/Medication',
        'food' => 'Food',
        'environmental' => 'Environmental',
        'topical' => 'Topical/Skincare',
        'metal' => 'Metal',
        'latex' => 'Latex',
        'other' => 'Other',
    ],

    'allergy_severities' => [
        'mild' => 'Mild',
        'moderate' => 'Moderate',
        'severe' => 'Severe',
        'life_threatening' => 'Life Threatening',
    ],

    // Medication Section
    'medication' => [
        'name' => 'Medication Name',
        'generic_name' => 'Generic Name',
        'dosage' => 'Dosage',
        'frequency' => 'Frequency',
        'route' => 'Route',
        'reason' => 'Reason',
        'start_date' => 'Start Date',
        'end_date' => 'End Date',
        'is_ongoing' => 'Ongoing',
        'affects_treatment' => 'Affects laser/IPL treatment',
        'treatment_implications' => 'Treatment Implications',
        'prescribing_doctor' => 'Prescribing Doctor',
        'is_otc' => 'Over-the-Counter',
    ],

    'medication_routes' => [
        'oral' => 'Oral',
        'topical' => 'Topical',
        'injection' => 'Injection',
        'inhalation' => 'Inhalation',
        'sublingual' => 'Sublingual',
        'transdermal' => 'Transdermal',
        'other' => 'Other',
    ],

    'medication_frequencies' => [
        'once_daily' => 'Once daily',
        'twice_daily' => 'Twice daily',
        'three_times_daily' => '3 times daily',
        'four_times_daily' => '4 times daily',
        'as_needed' => 'As needed (PRN)',
        'weekly' => 'Weekly',
        'other' => 'Other',
    ],

    // Contraindication Section
    'contraindication' => [
        'type' => 'Type',
        'name' => 'Name',
        'description' => 'Description',
        'affected_services' => 'Affected Services',
        'start_date' => 'Start Date',
        'end_date' => 'End Date',
        'is_active' => 'Active',
        'source' => 'Source',
        'block_booking' => 'Block appointment booking',
        'show_booking_alert' => 'Show Booking Alert',
    ],

    'contraindication_types' => [
        'absolute' => 'Absolute (Treatment prohibited)',
        'relative' => 'Relative (Treatment with caution)',
        'temporary' => 'Temporary (Time-limited)',
    ],

    'contraindication_sources' => [
        'patient_reported' => 'Patient Reported',
        'doctor_identified' => 'Doctor Identified',
        'system_derived' => 'System Derived',
    ],

    // Medical History Section
    'history' => [
        'type' => 'Type',
        'name' => 'Condition/Event',
        'description' => 'Description',
        'onset_date' => 'Onset Date',
        'resolved_date' => 'Resolved Date',
        'is_ongoing' => 'Ongoing condition',
        'severity' => 'Severity',
        'family_relationship' => 'Family Relationship',
        'affects_treatment' => 'Affects treatment',
        'treatment_implications' => 'Treatment Implications',
        'verified' => 'Verified by Doctor',
    ],

    'history_types' => [
        'medical_condition' => 'Medical Condition',
        'surgery' => 'Surgery',
        'hospitalization' => 'Hospitalization',
        'family_history' => 'Family History',
        'social_history' => 'Social History',
    ],

    'history_severities' => [
        'mild' => 'Mild',
        'moderate' => 'Moderate',
        'severe' => 'Severe',
    ],

    'family_relationships' => [
        'mother' => 'Mother',
        'father' => 'Father',
        'sibling' => 'Sibling',
        'grandparent' => 'Grandparent',
        'aunt_uncle' => 'Aunt/Uncle',
        'other' => 'Other',
    ],

    // Skin Assessment Section
    'skin' => [
        'fitzpatrick_type' => 'Fitzpatrick Type',
        'skin_type' => 'Skin Type',
        'sensitivity' => 'Sensitivity',
        'texture' => 'Texture',
        'pore_size' => 'Pore Size',
        'skin_tone' => 'Skin Tone',
        'hydration_level' => 'Hydration Level',
        'elasticity' => 'Elasticity',
        'pigmentation' => 'Pigmentation',
        'acne_severity' => 'Acne Severity',
        'aging_level' => 'Aging Level',
        'sun_damage_level' => 'Sun Damage Level',
        'current_conditions' => 'Current Conditions',
        'previous_conditions' => 'Previous Conditions',
        'aging_signs' => 'Aging Signs',
        'areas_of_concern' => 'Areas of Concern',
        'patient_goals' => 'Patient Goals',
        'clinical_observations' => 'Clinical Observations',
        'recommendations' => 'Recommendations',
        'assessed_by' => 'Assessed By',
        'assessed_date' => 'Assessment Date',
    ],

    'skin_oily_types' => [
        'dry' => 'Dry',
        'normal' => 'Normal',
        'oily' => 'Oily',
        'combination' => 'Combination',
    ],

    'skin_sensitivity_levels' => [
        'low' => 'Low',
        'normal' => 'Normal',
        'high' => 'High',
        'very_high' => 'Very High',
    ],

    'skin_texture_levels' => [
        'smooth' => 'Smooth',
        'slightly_rough' => 'Slightly Rough',
        'rough' => 'Rough',
        'very_rough' => 'Very Rough',
    ],

    'skin_pore_sizes' => [
        'small' => 'Small',
        'medium' => 'Medium',
        'large' => 'Large',
        'very_large' => 'Very Large',
    ],

    'skin_tone_levels' => [
        'even' => 'Even',
        'slightly_uneven' => 'Slightly Uneven',
        'uneven' => 'Uneven',
        'very_uneven' => 'Very Uneven',
    ],

    'skin_aging_levels' => [
        'none' => 'None',
        'early' => 'Early Signs',
        'moderate' => 'Moderate',
        'advanced' => 'Advanced',
    ],

    'skin_sun_damage_levels' => [
        'none' => 'None',
        'mild' => 'Mild',
        'moderate' => 'Moderate',
        'severe' => 'Severe',
    ],

    'skin_conditions' => [
        'acne' => 'Acne',
        'rosacea' => 'Rosacea',
        'eczema' => 'Eczema',
        'psoriasis' => 'Psoriasis',
        'melasma' => 'Melasma',
        'hyperpigmentation' => 'Hyperpigmentation',
        'vitiligo' => 'Vitiligo',
        'scarring' => 'Scarring',
        'seborrheic_dermatitis' => 'Seborrheic Dermatitis',
        'keratosis_pilaris' => 'Keratosis Pilaris',
    ],

    'skin_aging_signs' => [
        'fine_lines' => 'Fine Lines',
        'wrinkles' => 'Wrinkles',
        'sagging' => 'Sagging',
        'volume_loss' => 'Volume Loss',
        'age_spots' => 'Age Spots',
        'dull_skin' => 'Dull Skin',
        'neck_lines' => 'Neck Lines',
        'crow_feet' => 'Crow\'s Feet',
    ],

    'skin_areas_of_concern' => [
        'face' => 'Face',
        'forehead' => 'Forehead',
        'cheeks' => 'Cheeks',
        'nose' => 'Nose',
        'chin' => 'Chin',
        'neck' => 'Neck',
        'chest' => 'Chest',
        'hands' => 'Hands',
        'back' => 'Back',
    ],

    // Lifestyle Section
    'lifestyle' => [
        'smoking_status' => 'Smoking Status',
        'smoking_frequency' => 'Smoking Frequency',
        'smoking_years' => 'Years Smoking',
        'smoking_quit_date' => 'Quit Date',
        'alcohol_status' => 'Alcohol Consumption',
        'alcohol_frequency' => 'Alcohol Frequency',
        'exercise_level' => 'Exercise Level',
        'exercise_details' => 'Exercise Details',
        'sun_exposure' => 'Sun Exposure',
        'uses_sunscreen' => 'Uses Sunscreen',
        'uses_tanning_beds' => 'Uses Tanning Beds',
        'sleep_hours' => 'Sleep Hours',
        'sleep_issues' => 'Sleep Issues',
        'diet_type' => 'Diet Type',
        'dietary_restrictions' => 'Dietary Restrictions',
        'occupational_exposures' => 'Occupational Exposures',
    ],

    'smoking_status_options' => [
        'never' => 'Never Smoked',
        'former' => 'Former Smoker',
        'current' => 'Current Smoker',
    ],

    'alcohol_status_options' => [
        'never' => 'Never',
        'occasional' => 'Occasional',
        'regular' => 'Regular',
        'heavy' => 'Heavy',
    ],

    'exercise_level_options' => [
        'sedentary' => 'Sedentary',
        'light' => 'Light (1-2 days/week)',
        'moderate' => 'Moderate (3-4 days/week)',
        'active' => 'Active (5+ days/week)',
        'very_active' => 'Very Active (Daily)',
    ],

    'sun_exposure_options' => [
        'minimal' => 'Minimal',
        'moderate' => 'Moderate',
        'frequent' => 'Frequent',
        'excessive' => 'Excessive',
    ],

    // Stats
    'stats' => [
        'allergies' => 'Allergies',
        'medications' => 'Active Medications',
        'contraindications' => 'Active Contraindications',
        'conditions' => 'Medical Conditions',
        'skin_assessments' => 'Skin Assessments',
    ],

    // Actions
    'actions' => [
        'back_to_patient' => 'Back to Patient',
        'mark_reviewed' => 'Mark as Reviewed',
        'save_profile' => 'Save Profile',
        'add_allergy' => 'Add Allergy',
        'add_medication' => 'Add Medication',
        'add_contraindication' => 'Add Contraindication',
        'add_history' => 'Add History',
        'add_skin_assessment' => 'Add Skin Assessment',
        'save_lifestyle' => 'Save Lifestyle',
        'delete' => 'Delete',
        'edit' => 'Edit',
        'view' => 'View',
    ],

    // Messages
    'messages' => [
        'patient_required' => 'Patient ID is required',
        'patient_not_found' => 'Patient not found',
        'profile_saved' => 'Profile saved successfully',
        'marked_reviewed' => 'Profile marked as reviewed',
        'allergy_added' => 'Allergy added successfully',
        'allergy_deleted' => 'Allergy removed',
        'medication_added' => 'Medication added successfully',
        'medication_deleted' => 'Medication removed',
        'contraindication_added' => 'Contraindication added successfully',
        'contraindication_deleted' => 'Contraindication removed',
        'history_added' => 'Medical history added successfully',
        'history_deleted' => 'Medical history removed',
        'skin_assessment_added' => 'Skin assessment added successfully',
        'skin_assessment_deleted' => 'Skin assessment removed',
        'lifestyle_saved' => 'Lifestyle information saved',
    ],

    // Labels
    'alerts_count' => ':count Alert(s)',
    'needs_review' => 'Needs Review',
    'last_reviewed' => 'Last Reviewed',
    'never_reviewed' => 'Never reviewed',
    'latest' => 'Latest',
    'recorded' => 'recorded',
    'active' => 'Active',
    'ongoing' => 'Ongoing',
    'affects_treatment' => 'Affects Treatment',
    'blocks_booking' => 'Blocks Booking',
    'treatment_considerations' => 'Treatment Considerations',

    // Empty States
    'no_allergies' => 'No allergies recorded',
    'no_medications' => 'No medications recorded',
    'no_contraindications' => 'No contraindications recorded',
    'no_history' => 'No medical history recorded',
    'no_skin_assessment' => 'No skin assessment performed',
    'no_lifestyle' => 'No lifestyle information recorded',
];
