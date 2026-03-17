<?php

return [
    // Navigation
    'equipment' => 'Equipment',
    'equipment_item' => 'Equipment',
    'equipment_types' => 'Equipment Types',
    'equipment_type' => 'Equipment Type',

    // Fields
    'name' => 'Name',
    'code' => 'Code',
    'type' => 'Type',
    'category' => 'Category',
    'manufacturer' => 'Manufacturer',
    'model' => 'Model',
    'branch' => 'Branch',
    'room' => 'Room',
    'serial_number' => 'Serial Number',
    'specifications' => 'Specifications',
    'image' => 'Image',
    'active' => 'Active',
    'units' => 'Units',

    // Status
    'status' => 'Status',
    'status_active' => 'Active',
    'status_maintenance' => 'Under Maintenance',
    'status_out_of_service' => 'Out of Service',
    'status_retired' => 'Retired',

    // Purchase & Depreciation
    'purchase_info' => 'Purchase Information',
    'purchase_date' => 'Purchase Date',
    'purchase_price' => 'Purchase Price',
    'warranty_expiry' => 'Warranty Expiry',
    'depreciation_years' => 'Depreciation Years',
    'depreciated_value' => 'Depreciated Value',

    // Shots
    'shot_counter' => 'Shot Counter',
    'max_shots' => 'Max Shots',
    'total_shots' => 'Total Shots',
    'shots' => 'Shots',
    'shots_remaining' => 'Shots Remaining',
    'shots_count' => 'Shots Count',
    'record_shots' => 'Record Shots',
    'energy_setting' => 'Energy Setting',
    'energy' => 'Energy',
    'spot_size' => 'Spot Size',
    'pulse_duration' => 'Pulse Duration',
    'pulse' => 'Pulse',
    'logged_at' => 'Logged At',

    // Maintenance
    'maintenance' => 'Maintenance',
    'maintenance_due' => 'Maintenance Due',
    'last_maintenance' => 'Last Maintenance',
    'next_maintenance' => 'Next Maintenance',
    'log_maintenance' => 'Log Maintenance',
    'maintenance_type' => 'Type',
    'description' => 'Description',
    'performed_by' => 'Performed By',
    'performed_at' => 'Performed At',
    'cost' => 'Cost',
    'next_due_date' => 'Next Due Date',
    'next_due' => 'Next Due',
    'parts_replaced' => 'Parts Replaced',

    // Other
    'details' => 'Details',
    'notes' => 'Notes',
    'specifications' => 'Specifications',
    'spec_name' => 'Specification',
    'spec_value' => 'Value',
    'add_spec' => 'Add Specification',
    'image' => 'Image',
    'auto_generated' => 'Auto-generated',
    'price_help' => 'Enter price in minor units',
    'years' => 'years',
    'max_shots_help' => 'Leave empty if not applicable',

    // Tracking
    'tracking_enabled' => 'Enable Parameter Tracking',
    'tracking_enabled_help' => 'Enable tracking of custom parameters during treatment sessions',

    // Navigation for templates
    'navigation' => [
        'parameter_templates' => 'Equipment Parameter Templates',
    ],

    // Labels
    'labels' => [
        'parameter_template' => 'Parameter Template',
        'parameter_templates' => 'Parameter Templates',
    ],

    // Template
    'template' => [
        'info' => 'Template Information',
        'name' => 'Template Name',
        'code' => 'Template Code',
        'code_help' => 'Unique code for this template (e.g., DIODE_LASER)',
        'category' => 'Equipment Category',
        'category_help' => 'Category of equipment this template is for',
        'parameters' => 'Parameters',
        'parameters_desc' => 'Define the parameters that will be collected during treatment sessions',
        'parameters_count' => 'parameters',
        'equipment_using' => 'Equipment Using',
        'system' => 'System',
        'updated' => 'Updated',
        'duplicate' => 'Duplicate',
        'new_parameter' => 'New Parameter',
        'select' => 'Parameter Template',
        'select_help' => 'Select a template to use predefined parameters',
        'none' => 'No template (custom parameters)',
        'apply' => 'Apply Template',
        'apply_confirm' => 'This will replace existing parameters from template. Continue?',
        'applied' => 'Template parameters applied successfully',

        // Type labels
        'types' => [
            'text' => 'Text',
            'number' => 'Number',
            'decimal' => 'Decimal',
            'select' => 'Dropdown',
            'boolean' => 'Yes/No',
            'textarea' => 'Long Text',
        ],

        // Category labels
        'categories' => [
            'energy' => 'Energy Settings',
            'timing' => 'Timing/Pulse',
            'spot' => 'Spot/Area',
            'cooling' => 'Cooling',
            'other' => 'Other',
        ],

        // Label fields
        'label_en' => 'Label (English)',
        'label_ar' => 'Label (Arabic)',
        'help_en' => 'Help Text (English)',
        'help_ar' => 'Help Text (Arabic)',
        'unit_help' => 'e.g., nm, J/cm², ms, %',

        // Source labels
        'source' => 'Source',
        'from_template' => 'Template',
        'manual' => 'Manual',

        // Import actions
        'import' => 'Import from Template',
        'import_confirm' => 'This will add parameters from the selected template. Continue?',
    ],

    // Parameters
    'parameters' => [
        'title' => 'Tracking Parameters',
        'basic_info' => 'Basic Information',
        'value_config' => 'Value Configuration',
        'tracking_config' => 'Tracking Configuration',

        'key' => 'Parameter Key',
        'key_help' => 'Unique identifier (e.g., fluence, pulse_width)',
        'name' => 'Display Name',
        'value_type' => 'Value Type',
        'unit' => 'Unit',
        'category' => 'Category',
        'description' => 'Description',
        'description_help' => 'Help text shown to staff during entry',

        'min_value' => 'Min Value',
        'max_value' => 'Max Value',
        'default_value' => 'Default Value',
        'step' => 'Step',
        'step_help' => 'Increment step for number inputs',
        'options' => 'Options',
        'option_value' => 'Value',
        'option_label' => 'Label',
        'add_option' => 'Add Option',

        'is_required' => 'Required',
        'is_required_help' => 'Must be filled to complete session',
        'is_cumulative' => 'Cumulative',
        'is_cumulative_help' => 'Values accumulate during session',
        'track_in_session' => 'Track in Session',
        'track_in_session_help' => 'Show in treatment session form',
        'display_order' => 'Display Order',
        'is_active' => 'Active',

        'type' => 'Type',
        'required' => 'Required',
        'track' => 'Track',
        'order' => 'Order',
        'active' => 'Active',
    ],

    'notifications' => [
        'editing_system_template' => 'Editing System Template',
        'editing_system_template_body' => 'You are editing a system template. Changes will affect all equipment using this template.',
    ],
];
