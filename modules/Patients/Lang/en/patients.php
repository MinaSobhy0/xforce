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
    ],

    // Consent Forms
    'consent' => [
        'form' => 'Consent Form',
        'forms' => 'Consent Forms',
        'signed_at' => 'Signed At',
        'signed_by' => 'Signed By',
        'witness' => 'Witness',
        'signature' => 'Signature',
        'valid_until' => 'Valid Until',
        'download' => 'Download',
        'sign_new' => 'Sign New Form',
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
    ],
];
