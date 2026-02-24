<?php

return [
    // Navigation
    'navigation' => 'Doctor Dashboard',
    'title' => 'Doctor Dashboard',
    'heading' => 'Today\'s Sessions',

    // Statistics
    'stats' => [
        'waiting' => 'Waiting',
        'in_progress' => 'In Progress',
        'completed' => 'Completed',
        'upcoming' => 'Upcoming',
        'checked_in_desc' => 'Patients checked in',
        'in_progress_desc' => 'Sessions active',
        'completed_desc' => 'Sessions done today',
        'upcoming_desc' => 'Scheduled for today',
    ],

    // Queue
    'queue' => [
        'title' => 'Appointment Queue',
        'checked_in' => 'Checked In',
        'in_progress' => 'In Progress',
        'confirmed' => 'Confirmed',
        'scheduled' => 'Scheduled',
        'completed' => 'Completed',
        'empty' => 'No appointments scheduled for today',
        'active' => 'Active',
    ],

    // Actions
    'actions' => [
        'start' => 'Start',
        'resume' => 'Resume',
        'complete' => 'Complete',
        'check_in' => 'Check In',
        'confirm' => 'Confirm',
        'back_to_queue' => 'Back to Queue',
    ],

    // Tabs
    'tabs' => [
        'info' => 'Patient Info',
        'medical' => 'Medical History',
        'photos' => 'Photos',
        'notes' => 'Notes',
        'plan' => 'Treatment Plan',
    ],

    // Workspace
    'workspace' => [
        'no_session' => 'No Active Session',
        'select_patient' => 'Select a patient from the queue to start a session',
    ],

    // Patient Info
    'patient' => [
        'code' => 'Patient Code',
        'age' => 'Age',
        'years' => 'years',
        'phone' => 'Phone',
        'member_since' => 'Member Since',
        'allergies' => 'Allergies',
        'contraindications' => 'Contraindications',
        'medications' => 'Current Medications',
    ],

    // Medical History
    'medical' => [
        'fitzpatrick' => 'Fitzpatrick Type',
        'blood_type' => 'Blood Type',
        'bmi' => 'BMI',
        'smoker' => 'Smoker',
        'conditions' => 'Medical Conditions',
        'previous_treatments' => 'Previous Cosmetic Treatments',
        'notes' => 'Medical Notes',
        'no_history' => 'No medical history recorded',
    ],

    // Photos
    'photos' => [
        'upload' => 'Upload Photo',
        'file' => 'Photo',
        'type' => 'Type',
        'body_area' => 'Body Area',
        'select_area' => 'Select area...',
        'upload_btn' => 'Upload',
        'no_photos' => 'No photos recorded',
    ],

    // Notes
    'notes' => [
        'add' => 'Add Session Note',
        'placeholder' => 'Type your session notes here...',
        'save' => 'Save Note',
        'no_notes' => 'No notes recorded',
    ],

    // Treatment Plan
    'plan' => [
        'active_plans' => 'Active Treatment Plans',
        'progress' => 'Progress',
        'sessions' => 'sessions',
        'create' => 'Create Treatment Plan',
        'name' => 'Plan Name',
        'name_placeholder' => 'e.g., Laser Hair Removal - Full Body',
        'services' => 'Services',
        'select_service' => 'Select service...',
        'interval' => 'Interval',
        'days' => 'days',
        'add_service' => 'Add Service',
        'notes' => 'Notes',
        'notes_placeholder' => 'Additional notes for the treatment plan...',
        'create_btn' => 'Create Plan',
    ],

    // Messages
    'messages' => [
        'cannot_start' => 'Cannot Start Session',
        'must_be_checked_in' => 'Patient must be checked in first',
        'must_be_confirmed' => 'Appointment must be confirmed first',
        'cannot_resume' => 'Cannot Resume Session',
        'cannot_complete' => 'Cannot Complete Session',
        'session_started' => 'Session Started',
        'session_completed' => 'Session Completed',
        'confirm_complete' => 'Are you sure you want to complete this session?',
        'note_required' => 'Please enter a note',
        'note_added' => 'Note Added',
        'photo_required' => 'Please select a photo to upload',
        'photo_uploaded' => 'Photo Uploaded',
        'plan_created' => 'Treatment Plan Created',
        'plan_creation_failed' => 'Failed to Create Treatment Plan',
        'cannot_check_in' => 'Cannot Check In Patient',
        'patient_checked_in' => 'Patient Checked In',
        'cannot_confirm' => 'Cannot Confirm Appointment',
        'appointment_confirmed' => 'Appointment Confirmed',
    ],
];
