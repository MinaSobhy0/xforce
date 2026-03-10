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
        'years' => 'yrs',
        'duration' => 'Duration',
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
        'sell_product' => 'Sell Product',
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
        'status' => 'Status',
        'medications' => 'Current Medications',
        'conditions' => 'Medical Conditions',
        'allergies' => 'Critical Allergies',
        'contraindications' => 'Contraindications',
        'no_history' => 'No medical history recorded',
        'no_profile' => 'No medical profile found',
        'view_full_profile' => 'Full Profile',
        'create_profile' => 'Create Medical Profile',
        'pregnant' => 'Pregnant',
        'breastfeeding' => 'Breastfeeding',
        'critical_allergies' => 'Critical Allergies',
        'has_contraindications' => 'Has Contraindications',
        'no_allergies' => 'No known allergies',
        'no_medications' => 'No current medications',
        'no_contraindications' => 'No contraindications',
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
        'take_photo' => 'Take Photo',
        'choose_file' => 'Gallery',
        'selected' => 'Selected',
        'uploading' => 'Uploading...',
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
        'no_plan' => 'No treatment plan assigned',
        'pending_delivery' => 'Pending',
        'current' => 'Current',
        'not_started' => 'Not Started',
        'in_progress' => 'In Progress',
        'cancelled' => 'Cancelled',
        'completed' => 'Completed',
        'delivered' => 'Delivered',
        'package_item' => 'Package',
        'legend' => [
            'package' => 'From Package',
            'individual' => 'Individual Service',
            'product' => 'Product',
        ],
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
        'start_another_session' => 'Start Another Session',
    ],

    // Modals
    'modals' => [
        'complete_session' => 'Complete Session',
        'complete_session_desc' => 'Are you sure you want to complete this session? This will mark the appointment as completed.',
        'apply_discount' => 'Apply Discount',
        'add_to_plan' => 'Add to Treatment Plan',
        'start_another_session' => 'Start Another Service Session',
    ],

    // Start another session
    'start_another' => [
        'service' => 'Service',
        'action' => 'What would you like to do?',
        'complete_current' => 'Complete current session & start new',
        'complete_current_desc' => 'Complete this session and immediately start the new service',
        'keep_open' => 'Keep current session open',
        'keep_open_desc' => 'Start the new service while keeping this session open',
        'assign_doctor' => 'Assign to another doctor',
        'assign_doctor_desc' => 'Create the appointment and assign it to another qualified doctor',
        'select_doctor' => 'Select Doctor',
        'no_other_doctors' => 'No other qualified doctors available for this service',
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
        'assign_to_doctor' => 'Assign to Doctor',
        'current_doctor' => 'Current Doctor',
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
        'session_assigned' => 'Session assigned successfully',
        'session_assigned_body' => ':service assigned to another doctor',
        'cannot_start_session' => 'Cannot start session for this service',
        'not_qualified_for_service' => 'You are not qualified to perform this service',
        'error' => 'An error occurred',
        'no_visit' => 'No active visit found',
        'package_already_pending' => 'This package is already pending purchase',
        'package_added' => 'Package added to checkout',
        'package_added_body' => ':package will be invoiced at checkout',
        'package_removed' => 'Package removed from checkout',
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
        // Shot tracking
        'shots_remaining' => 'Shots Remaining',
        'shots_this_session' => 'Shots This Session',
        'energy_delivered' => 'Energy (J)',
        'low_shots_warning' => 'Low shots - consider maintenance',
        // Maintenance
        'maintenance_due' => 'Maintenance Due',
        'next_maintenance' => 'Next Maintenance',
        'last_maintenance' => 'Last Maintenance',
        // Dynamic parameters
        'no_tracking_params' => 'No tracking parameters configured',
        'cumulative_hint' => 'This value accumulates across sessions',
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

    // Invoice section
    'invoice' => [
        'title' => 'Invoice Summary',
        'item' => 'Item',
        'qty' => 'Qty',
        'unit_price' => 'Unit Price',
        'discount' => 'Discount',
        'total' => 'Total',
        'subtotal' => 'Subtotal',
        'grand_total' => 'Grand Total',
        'service_session' => 'Service Session',
        'active_session' => 'Active Session',
        'product_sold' => 'Product (Sold)',
        'service_discount' => 'Service Discount',
        'overall_discount' => 'Overall Discount',
        'no_discount' => 'No Discount',
        'percentage' => 'Percentage',
        'fixed_amount' => 'Fixed Amount',
        'discount_reason' => 'Discount Reason',
        'discount_reason_placeholder' => 'e.g., Loyalty discount, First visit...',
        'apply_discount' => 'Apply Discount',
        'discount_applied' => 'Discount Applied',
        'package_session' => 'Package Session',
        'plan_product' => 'Treatment Plan Product',
        'covered_by_package' => 'Covered by Package',
        'no_billable_items' => 'No billable items',
        'edit_price' => 'Edit Price',
        'save_price' => 'Save',
        'cancel_edit' => 'Cancel',
        'other_visit_items' => 'Other items in visit :code',
        'visit_checkout_note' => 'Full invoice will be generated at checkout',
    ],

    // View mode
    'view_mode' => [
        'title' => 'Viewing Completed Session',
        'description' => 'This session has been completed and is read-only',
        'completed_on' => 'Completed on',
        'back_to_dashboard' => 'Back to Dashboard',
    ],

    // Previous sessions
    'previous_sessions' => [
        'title' => 'Previous Sessions',
        'view' => 'View',
        'no_previous' => 'No previous sessions for this service',
    ],
];
