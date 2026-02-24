<?php

return [
    'title' => 'Treatment Session',
    'heading' => 'Treatment Session',
    'session_of' => 'Session :current of :total',

    // Info bar
    'info' => [
        'service' => 'Service',
        'time' => 'Time',
        'room' => 'Room',
        'session_number' => 'Session :current of :total',
    ],

    // Sections
    'sections' => [
        'medical_info' => 'Medical Information',
        'notes' => 'Session Notes',
        'photos' => 'Photos',
        'current_plan' => 'Current Treatment Plan',
        'previous_visits' => 'Previous Visits',
        'create_plan' => 'Create Treatment Plan',
    ],

    // Alerts
    'alerts' => [
        'allergies' => 'Allergies',
        'contraindications' => 'Contraindications',
    ],

    // Patient
    'patient' => [
        'code' => 'Patient Code',
        'name' => 'Name',
        'phone' => 'Phone',
        'age' => 'Age',
        'years' => 'years',
    ],

    // Medical
    'medical' => [
        'fitzpatrick' => 'Skin Type',
        'blood_type' => 'Blood Type',
        'bmi' => 'BMI',
        'smoker' => 'Smoker',
        'medications' => 'Current Medications',
        'conditions' => 'Medical Conditions',
        'no_history' => 'No medical history recorded',
    ],

    // Notes
    'notes' => [
        'placeholder' => 'Enter session notes...',
        'add' => 'Add Note',
        'session_note' => 'Session Note',
        'no_notes' => 'No notes recorded',
    ],

    // Photos
    'photos' => [
        'upload' => 'Upload',
        'select_area' => 'Body area...',
        'no_photos' => 'No photos recorded',
    ],

    // Treatment Plan
    'plan' => [
        'progress' => 'Progress',
        'sessions' => 'sessions',
        'name' => 'Plan Name',
        'name_placeholder' => 'e.g., Laser Hair Removal - Full Body',
        'services' => 'Services',
        'select_service' => 'Select service...',
        'days' => 'days',
        'add_service' => 'Add Service',
        'notes' => 'Notes',
        'create' => 'Create Plan',
    ],

    // Previous visits
    'previous' => [
        'no_visits' => 'No previous visits',
    ],

    // Actions
    'actions' => [
        'complete_session' => 'Complete Session',
        'back_to_dashboard' => 'Back to Dashboard',
    ],

    // Modals
    'modals' => [
        'complete_session' => 'Complete Session',
        'complete_session_desc' => 'Are you sure you want to complete this session? This will mark the appointment as completed.',
    ],

    // Messages
    'messages' => [
        'appointment_not_found' => 'Appointment not found',
        'session_not_active' => 'This session is not active',
        'session_completed' => 'Session completed successfully',
        'note_required' => 'Please enter a note',
        'note_added' => 'Note added successfully',
        'photo_required' => 'Please select a photo',
        'photo_uploaded' => 'Photo uploaded successfully',
        'plan_created' => 'Treatment plan created successfully',
        'plan_creation_failed' => 'Failed to create treatment plan',
    ],
];
