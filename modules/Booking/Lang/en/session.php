<?php

return [
    'navigation_label' => 'Treatment Session',
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
        'pre_treatment_checklist' => 'Pre-Treatment Checklist',
        'equipment' => 'Equipment',
        'presets' => 'Parameter Presets',
        'parameters' => 'Treatment Parameters',
        'clinical_notes' => 'Clinical Documentation',
        'consumables' => 'Consumables',
        'products' => 'Products',
    ],

    // Alerts
    'alerts' => [
        'allergies' => 'Allergies',
        'contraindications' => 'Contraindications',
        'amr_resistance' => 'AMR Resistance',
        'critical' => 'CRITICAL',
        'mdro_flags' => 'MDRO Flags',
        'resistant_to' => 'Resistant to',
        'more' => 'more',
        'view_amr_history' => 'View full AMR history',
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
        'service' => 'Service',
        'sessions_count' => 'Sessions',
        'interval_days' => 'Interval',
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
        'apply_discount' => 'Apply Discount',
        'apply' => 'Apply',
        'add_to_plan' => 'Add to Plan',
        'start_session' => 'Start Session',
    ],

    // Modals
    'modals' => [
        'complete_session' => 'Complete Session',
        'complete_session_desc' => 'Are you sure you want to complete this session? This will mark the appointment as completed.',
        'apply_discount' => 'Apply Discount',
        'add_to_plan' => 'Add to Treatment Plan',
    ],

    // Plan modal
    'plan_modal' => [
        'mode' => 'Plan Mode',
        'add_to_existing' => 'Add to Existing Plan',
        'create_new' => 'Create New Plan',
        'select_plan' => 'Select Treatment Plan',
        'plan_name' => 'Plan Name',
        'items' => 'Items to Add',
        'item_type' => 'Type',
        'service' => 'Service',
        'product' => 'Product',
        'package' => 'Package',
        'sessions' => 'Sessions',
        'quantity' => 'Qty',
        'interval' => 'Interval',
        'price' => 'Price',
        'original_price' => 'Original Price',
        'discount_type' => 'Discount',
        'no_discount' => 'No Discount',
        'percentage' => 'Percentage',
        'fixed_amount' => 'Fixed Amount',
        'discount_value' => 'Discount Value',
        'final_price' => 'Final Price',
    ],

    // Discount
    'discount' => [
        'type' => 'Discount Type',
        'percentage' => 'Discount Percentage',
        'amount' => 'Discount Amount',
        'reason' => 'Reason (Optional)',
        'reason_placeholder' => 'e.g., First-time patient, Loyalty discount...',
        'preview' => 'Price Preview',
        'original_price' => 'Original Price',
        'discount_amount' => 'Discount',
        'final_price' => 'Final Price',
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
        'photo_deleted' => 'Photo deleted successfully',
        'plan_created' => 'Treatment plan created successfully',
        'plan_creation_failed' => 'Failed to create treatment plan',
        'preset_applied' => 'Preset applied successfully',
        'clinical_notes_saved' => 'Clinical notes saved',
        'checklist_incomplete' => 'Pre-treatment checklist incomplete',
        'complete_checklist_first' => 'Please complete all safety checklist items before finishing the session',
        'consumable_added' => 'Consumable added',
        'consumable_removed' => 'Consumable removed',
        'product_added' => 'Product added',
        'product_removed' => 'Product removed',
        'discount_applied' => 'Discount applied successfully',
        'discount_applied_body' => 'Discount of :amount applied. Final price: :final',
        'items_added_to_plan' => 'Items added to treatment plan successfully',
        'add_to_plan_failed' => 'Failed to add items to treatment plan',
        'session_started' => 'Session started successfully',
        'cannot_start_session' => 'Cannot start session for this service',
        'error' => 'An error occurred',
    ],

    // Pre-treatment checklist
    'checklist' => [
        'patient_identity_verified' => 'Patient identity verified',
        'consent_signed' => 'Consent form signed',
        'medical_history_reviewed' => 'Medical history reviewed',
        'contraindications_checked' => 'Contraindications checked',
        'allergies_confirmed' => 'Allergies confirmed',
        'test_patch_done' => 'Test patch completed',
        'eye_protection_provided' => 'Eye protection provided',
        'treatment_area_clean' => 'Treatment area cleaned',
    ],

    // Equipment
    'equipment' => [
        'select' => 'Select Equipment',
        'none' => 'No equipment selected',
        'metrics' => 'Session Metrics',
        'shots_used' => 'Shots Used',
        'energy' => 'Energy Delivered (J)',
        'devices' => 'device(s)',
        'preset' => 'Preset',
        'shots' => 'Shots',
        'energy_short' => 'Energy (J)',
        'add_equipment' => 'Add equipment...',
        'none_available' => 'No equipment available',
        'already_added' => 'Equipment already added',
        'added' => 'Equipment added',
        'has_params' => 'Params',
        'tracking_params' => 'Tracking Parameters',
    ],

    // Presets
    'presets' => [
        'default' => 'Default',
        'none' => 'No presets available for this service',
    ],

    // Clinical notes
    'clinical' => [
        'skin_reaction' => 'Skin Reaction',
        'pain_level' => 'Pain Level',
        'pain_none' => 'None',
        'pain_mild' => 'Mild',
        'pain_moderate' => 'Moderate',
        'pain_severe' => 'Severe',
        'observations' => 'Clinical Observations',
        'observations_placeholder' => 'Enter clinical observations, notes about treatment area, patient response, etc.',
        'save' => 'Save Notes',
    ],

    // Consumables
    'consumables' => [
        'select' => 'Select consumable...',
        'quantity' => 'Qty',
        'add' => 'Add',
        'none' => 'No consumables added',
        'total_cost' => 'Total Cost',
        'unit' => 'Unit',
    ],

    // Products
    'products' => [
        'select' => 'Select product...',
        'quantity' => 'Qty',
        'add' => 'Add',
        'none' => 'No products added',
        'total_value' => 'Total Value',
        'usage_type' => 'Usage',
        'applied' => 'Applied during treatment',
        'sold' => 'Sold to patient',
    ],
];
