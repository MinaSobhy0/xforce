<?php

return [
    // Module
    'module_name' => 'Patients',
    'module_description' => 'Patient CRM and medical history management',

    // Navigation
    'navigation' => [
        'patients' => 'Patients',
        'all_patients' => 'All Patients',
        'new_patient' => 'New Patient',
    ],

    // Labels
    'labels' => [
        'patient' => 'Patient',
        'patients' => 'Patients',
        'patient_code' => 'Patient Code',
        'personal_info' => 'Personal Information',
        'contact_info' => 'Contact Information',
        'medical_history' => 'Medical History',
        'consent_forms' => 'Consent Forms',
        'photos' => 'Photos',
        'notes' => 'Notes',
        'activity' => 'Activity',
    ],

    // Fields
    'fields' => [
        'code' => 'Code',
        'first_name' => 'First Name',
        'last_name' => 'Last Name',
        'full_name' => 'Full Name',
        'email' => 'Email',
        'phone' => 'Phone',
        'secondary_phone' => 'Secondary Phone',
        'date_of_birth' => 'Date of Birth',
        'age' => 'Age',
        'gender' => 'Gender',
        'national_id' => 'National ID',
        'address' => 'Address',
        'city' => 'City',
        'country' => 'Country',
        'occupation' => 'Occupation',
        'emergency_contact' => 'Emergency Contact',
        'emergency_phone' => 'Emergency Phone',
        'referral_source' => 'Referral Source',
        'referred_by' => 'Referred By',
        'status' => 'Status',
        'tags' => 'Tags',
        'notes' => 'Notes',
        'branch' => 'Branch',
        'created_at' => 'Created At',
        'last_visit' => 'Last Visit',
    ],

    // Medical History
    'medical' => [
        'fitzpatrick_type' => 'Skin Type (Fitzpatrick)',
        'blood_type' => 'Blood Type',
        'allergies' => 'Allergies',
        'medications' => 'Current Medications',
        'medical_conditions' => 'Medical Conditions',
        'previous_treatments' => 'Previous Treatments',
        'contraindications' => 'Contraindications',
        'skin_concerns' => 'Skin Concerns',
        'pregnancy_status' => 'Pregnancy Status',
        'breastfeeding' => 'Breastfeeding',
        'sun_response' => 'Sun Response',
        'skin_characteristics' => 'Skin Characteristics',
        'sun_exposure' => 'Sun Exposure Level',
        'sun_levels' => [
            'minimal' => 'Minimal',
            'moderate' => 'Moderate',
            'high' => 'High',
        ],
    ],

    // Sections
    'sections' => [
        'skin_assessment' => 'Skin Assessment',
        'personal_info' => 'Personal Information',
        'contact_info' => 'Contact Information',
        'medical_history' => 'Medical History',
    ],

    // Fitzpatrick Types
    'fitzpatrick' => [
        'type_i' => 'Type I',
        'type_ii' => 'Type II',
        'type_iii' => 'Type III',
        'type_iv' => 'Type IV',
        'type_v' => 'Type V',
        'type_vi' => 'Type VI',
        'desc_i' => 'Very fair skin, always burns, never tans',
        'desc_ii' => 'Fair skin, burns easily, tans minimally',
        'desc_iii' => 'Medium skin, sometimes burns, tans uniformly',
        'desc_iv' => 'Olive skin, rarely burns, tans easily',
        'desc_v' => 'Brown skin, very rarely burns',
        'desc_vi' => 'Dark brown/black skin, never burns',
        'char_i' => 'Very light or pale white, often with freckles',
        'char_ii' => 'White to light beige',
        'char_iii' => 'Beige to light brown',
        'char_iv' => 'Light brown to olive',
        'char_v' => 'Brown',
        'char_vi' => 'Dark brown to black',
        'sun_i' => 'Always burns, never tans',
        'sun_ii' => 'Burns easily, tans with difficulty',
        'sun_iii' => 'Sometimes mild burn, tans uniformly',
        'sun_iv' => 'Rarely burns, tans with ease',
        'sun_v' => 'Very rarely burns, tans very easily',
        'sun_vi' => 'Never burns, deeply pigmented',
    ],

    // Consent Forms
    'consent' => [
        'form' => 'Consent Form',
        'forms' => 'Consent Forms',
        'signed_at' => 'Signed At',
        'signed_by' => 'Signed By',
        'witness' => 'Witness',
        'signature' => 'Signature',
        'typed_signature' => 'Typed Signature',
        'valid_until' => 'Valid Until',
        'download' => 'Download',
        'sign_new' => 'Sign New Form',
        'sign_here' => 'Sign here',
        'clear_signature' => 'Clear',
        'signature_captured' => 'Signature captured',
        'awaiting_signature' => 'Awaiting signature',
        'signature_instruction' => 'Draw your signature using your mouse or finger. The signature will be saved automatically.',
        'type_full_name' => 'Type your full legal name',
    ],

    // Photos
    'photos' => [
        'photo' => 'Photo',
        'photos' => 'Photos',
        'type' => 'Type',
        'body_area' => 'Body Area',
        'taken_at' => 'Taken At',
        'taken_by' => 'Taken By',
        'before' => 'Before',
        'after' => 'After',
        'during' => 'During',
        'consultation' => 'Consultation',
        'upload' => 'Upload Photo',
    ],

    // Notes
    'notes' => [
        'note' => 'Note',
        'notes' => 'Notes',
        'type' => 'Type',
        'clinical' => 'Clinical',
        'administrative' => 'Administrative',
        'follow_up' => 'Follow-up',
        'complaint' => 'Complaint',
        'content' => 'Content',
        'created_by' => 'Created By',
        'add_note' => 'Add Note',
    ],

    // Gender options
    'gender_options' => [
        'male' => 'Male',
        'female' => 'Female',
        'other' => 'Other',
    ],

    // Status options
    'status_options' => [
        'active' => 'Active',
        'inactive' => 'Inactive',
        'blocked' => 'Blocked',
        'deceased' => 'Deceased',
    ],

    // Actions
    'actions' => [
        'create' => 'Create Patient',
        'edit' => 'Edit Patient',
        'delete' => 'Delete Patient',
        'view' => 'View Patient',
        'export' => 'Export Patients',
        'import' => 'Import Patients',
        'merge' => 'Merge Patients',
        'send_message' => 'Send Message',
        'book_appointment' => 'Book Appointment',
        'medical_profile' => 'Medical Profile',
    ],

    // Messages
    'messages' => [
        'created' => 'Patient created successfully.',
        'updated' => 'Patient updated successfully.',
        'deleted' => 'Patient deleted successfully.',
        'not_found' => 'Patient not found.',
        'consent_required' => 'Consent form signature required before treatment.',
        'photo_uploaded' => 'Photo uploaded successfully.',
        'note_added' => 'Note added successfully.',
    ],

    // Filters
    'filters' => [
        'all' => 'All Patients',
        'active' => 'Active Patients',
        'new_this_month' => 'New This Month',
        'returning' => 'Returning Patients',
        'with_upcoming' => 'With Upcoming Appointments',
        'inactive_days' => 'Inactive (:days+ days)',
    ],

    // Stats Widget
    'stats' => [
        'total_registered' => 'Total registered patients',
        'growth_from_last_month' => ':growth% from last month',
        'of_total' => ':percent% of total',
        'recent_visitors' => 'Recent Visitors',
        'visited_last_days' => 'Visited in last :days days',
    ],

    // Balance
    'balance' => [
        'title' => 'Balance',
        'owes' => 'Outstanding',
        'credit' => 'Credit',
        'settled' => 'Settled',
        'outstanding_balance' => 'Outstanding Balance',
        'patient_has_balance' => 'This patient has an outstanding balance',
        'patient_has_credit' => 'This patient has credit',
        'pay_balance' => 'Pay Balance',
        'view_ledger' => 'View Ledger',
    ],
];
