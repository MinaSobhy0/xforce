<?php

return [
    // Navigation & Labels
    'prescription' => 'Prescription',
    'prescriptions' => 'Prescriptions',
    'years' => 'years',

    // Sections
    'sections' => [
        'prescription_details' => 'Prescription Details',
        'medications' => 'Medications',
        'status' => 'Status',
        'notes' => 'Notes',
        'patient_info' => 'Patient Information',
        'prescription_info' => 'Prescription Information',
        'dates' => 'Dates',
        'previous' => 'Previous Prescriptions',
        'new_prescription' => 'Create New Prescription',
    ],

    // Fields
    'fields' => [
        'prescription_number' => 'Prescription Number',
        'patient' => 'Patient',
        'prescriber' => 'Prescriber',
        'branch' => 'Branch',
        'diagnosis' => 'Diagnosis',
        'notes' => 'Notes',
        'valid_until' => 'Valid Until',
        'status' => 'Status',
        'issued_at' => 'Issued At',
        'created' => 'Created',
        'printed' => 'Printed',
        'print_count' => 'Print Count',
        'medications' => 'Medications',
        'medication_count' => 'Medication Count',
        'phone' => 'Phone',
        'age' => 'Age',
        'gender' => 'Gender',
        'cancellation_reason' => 'Cancellation Reason',

        // Medication fields
        'medication_name' => 'Medication Name',
        'generic_name' => 'Generic Name',
        'form' => 'Form',
        'dosage' => 'Dosage',
        'dosage_unit' => 'Unit',
        'frequency' => 'Frequency',
        'duration' => 'Duration',
        'duration_unit' => 'Duration Unit',
        'quantity' => 'Quantity',
        'route' => 'Route',
        'instructions' => 'Instructions',
        'special_instructions' => 'Special Instructions',
        'refills' => 'Refills Allowed',
    ],

    // Statuses
    'statuses' => [
        'draft' => 'Draft',
        'finalized' => 'Finalized',
        'cancelled' => 'Cancelled',
    ],

    // Actions
    'actions' => [
        'add_medication' => 'Add Medication',
        'finalize' => 'Finalize & Print',
        'print' => 'Print',
        'download' => 'Download',
        'cancel' => 'Cancel',
        'save_draft' => 'Save as Draft',
    ],

    // Filters
    'filters' => [
        'from' => 'From',
        'until' => 'Until',
        'expired_only' => 'Expired Only',
        'valid_only' => 'Valid Only',
    ],

    // Modals
    'modals' => [
        'finalize_heading' => 'Finalize Prescription',
        'finalize_description' => 'Are you sure you want to finalize this prescription? Once finalized, it cannot be edited.',
    ],

    // Messages
    'messages' => [
        'finalized' => 'Prescription has been finalized.',
        'cancelled' => 'Prescription has been cancelled.',
        'not_editable' => 'This prescription cannot be edited.',
        'no_medications' => 'Please add at least one medication.',
        'error' => 'An error occurred while creating the prescription.',
    ],

    // Placeholders
    'placeholders' => [
        'auto_generated' => 'Auto-generated',
        'additional_instructions' => 'Additional instructions for the patient...',
        'no_diagnosis' => 'No diagnosis specified',
        'no_notes' => 'No notes',
        'not_issued' => 'Not yet issued',
        'diagnosis' => 'Enter diagnosis or indication...',
        'special_notes' => 'Any special notes for this medication...',
        'no_medications' => 'No medications added yet. Click below to add.',
    ],

    // PDF
    'pdf' => [
        'title' => 'Medical Prescription',
        'phone' => 'Phone',
        'license' => 'License No.',
        'date' => 'Date',
        'patient_name' => 'Patient Name',
        'patient_code' => 'Patient Code',
        'age' => 'Age',
        'gender' => 'Gender',
        'diagnosis' => 'Diagnosis',
        'medications' => 'Medications',
        'dosage' => 'Dosage',
        'frequency' => 'Frequency',
        'duration' => 'Duration',
        'quantity' => 'Qty',
        'route' => 'Route',
        'instructions' => 'Instructions',
        'refills' => 'Refills',
        'special_note' => 'Note',
        'additional_notes' => 'Additional Notes',
        'valid_until' => 'Valid Until',
        'expired' => 'This prescription has expired',
        'signature' => 'Signature',
        'cancelled' => 'CANCELLED',
        'footer_text' => 'This prescription is valid for the specified period only. Please consult your doctor for any concerns.',
    ],

    // Frequencies
    'frequencies' => [
        'once_daily' => 'Once daily',
        'twice_daily' => 'Twice daily',
        'three_times_daily' => '3 times daily',
        'four_times_daily' => '4 times daily',
        'every_4_hours' => 'Every 4 hours',
        'every_6_hours' => 'Every 6 hours',
        'every_8_hours' => 'Every 8 hours',
        'every_12_hours' => 'Every 12 hours',
        'as_needed' => 'As needed (PRN)',
        'weekly' => 'Weekly',
        'twice_weekly' => 'Twice weekly',
        'monthly' => 'Monthly',
        'other' => 'Other',
    ],

    // Routes
    'routes' => [
        'oral' => 'Oral',
        'topical' => 'Topical',
        'injection' => 'Injection',
        'intravenous' => 'Intravenous (IV)',
        'intramuscular' => 'Intramuscular (IM)',
        'subcutaneous' => 'Subcutaneous (SC)',
        'inhalation' => 'Inhalation',
        'sublingual' => 'Sublingual',
        'transdermal' => 'Transdermal',
        'rectal' => 'Rectal',
        'ophthalmic' => 'Ophthalmic (Eye)',
        'otic' => 'Otic (Ear)',
        'nasal' => 'Nasal',
        'vaginal' => 'Vaginal',
        'other' => 'Other',
    ],

    // Forms
    'forms' => [
        'tablet' => 'Tablet',
        'capsule' => 'Capsule',
        'syrup' => 'Syrup',
        'suspension' => 'Suspension',
        'solution' => 'Solution',
        'cream' => 'Cream',
        'ointment' => 'Ointment',
        'gel' => 'Gel',
        'lotion' => 'Lotion',
        'drops' => 'Drops',
        'injection' => 'Injection',
        'inhaler' => 'Inhaler',
        'patch' => 'Patch',
        'suppository' => 'Suppository',
        'powder' => 'Powder',
        'spray' => 'Spray',
        'other' => 'Other',
    ],

    // Instructions
    'instructions' => [
        'before_meal' => 'Before meal',
        'after_meal' => 'After meal',
        'with_food' => 'With food',
        'empty_stomach' => 'On empty stomach',
        'at_bedtime' => 'At bedtime',
        'in_morning' => 'In the morning',
        'with_water' => 'With plenty of water',
        'without_water' => 'Without water',
        'chew' => 'Chew before swallowing',
        'swallow_whole' => 'Swallow whole',
        'dissolve' => 'Dissolve in mouth',
        'apply_affected' => 'Apply to affected area',
        'as_directed' => 'As directed',
    ],

    // Duration units
    'duration_units' => [
        'days' => 'Days',
        'weeks' => 'Weeks',
        'months' => 'Months',
    ],

    // Dosage units
    'dosage_units' => [
        'mg' => 'mg',
        'g' => 'g',
        'ml' => 'ml',
        'mcg' => 'mcg',
        'IU' => 'IU',
        'unit' => 'unit',
        'puff' => 'puff',
        'drop' => 'drop',
        'application' => 'application',
    ],

    // Medicine Catalog
    'medicine_catalog' => 'Medicine Catalog',
    'medicine' => 'Medicine',
    'medicines' => 'Medicines',

    'catalog' => [
        'quick_add' => 'Quick Add from Catalog',
        'select_medicine' => 'Select medicine from catalog...',
        'quick_add_help' => 'Select a medicine to auto-fill prescription details',
        'create_medicine' => 'Create New Medicine',
        'sections' => [
            'basic_info' => 'Basic Information',
            'strength' => 'Strength & Form',
            'default_prescription' => 'Default Prescription Settings',
            'default_prescription_desc' => 'These values will be pre-filled when selecting this medicine',
            'clinical_info' => 'Clinical Information',
            'status' => 'Status & Settings',
        ],
        'fields' => [
            'brand_name' => 'Brand Name',
            'generic_name' => 'Generic Name',
            'manufacturer' => 'Manufacturer',
            'category' => 'Category',
            'drug_class' => 'Drug Class',
            'strength' => 'Strength',
            'strength_unit' => 'Unit',
            'description' => 'Description',
            'indications' => 'Indications (What it treats)',
            'contraindications' => 'Contraindications (When not to use)',
            'side_effects' => 'Side Effects',
            'warnings' => 'Warnings',
            'is_active' => 'Active',
            'is_controlled' => 'Controlled Substance',
            'is_controlled_help' => 'Mark if this medicine is a controlled substance requiring special handling',
            'requires_prescription' => 'Requires Prescription',
            'is_system' => 'System Default',
        ],
    ],

    // Categories
    'categories' => [
        'antibiotic' => 'Antibiotic',
        'analgesic' => 'Analgesic / Pain Relief',
        'antipyretic' => 'Antipyretic / Fever',
        'anti_inflammatory' => 'Anti-inflammatory',
        'antihistamine' => 'Antihistamine / Allergy',
        'antacid' => 'Antacid / GI',
        'antihypertensive' => 'Antihypertensive',
        'antidiabetic' => 'Antidiabetic',
        'cardiovascular' => 'Cardiovascular',
        'respiratory' => 'Respiratory',
        'dermatological' => 'Dermatological / Skin',
        'ophthalmic' => 'Ophthalmic / Eye',
        'otic' => 'Otic / Ear',
        'vitamin' => 'Vitamins & Supplements',
        'muscle_relaxant' => 'Muscle Relaxant',
        'antidepressant' => 'Antidepressant',
        'anxiolytic' => 'Anxiolytic / Anti-anxiety',
        'antiemetic' => 'Antiemetic / Nausea',
        'laxative' => 'Laxative',
        'antidiarrheal' => 'Antidiarrheal',
        'antifungal' => 'Antifungal',
        'antiviral' => 'Antiviral',
        'hormone' => 'Hormones',
        'immunosuppressant' => 'Immunosuppressant',
        'other' => 'Other',
    ],
];
