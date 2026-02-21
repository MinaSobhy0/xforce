<?php

return [
    'module_name' => 'Services',
    'module_description' => 'Service catalog and consent template management',

    'navigation' => [
        'services' => 'Services',
        'categories' => 'Categories',
        'consent_templates' => 'Consent Templates',
    ],

    'labels' => [
        'service' => 'Service',
        'services' => 'Services',
        'category' => 'Category',
        'categories' => 'Categories',
        'consent_template' => 'Consent Template',
        'consent_templates' => 'Consent Templates',
    ],

    'fields' => [
        'name' => 'Name',
        'description' => 'Description',
        'category' => 'Category',
        'parent_category' => 'Parent Category',
        'duration' => 'Duration (minutes)',
        'buffer_time' => 'Buffer Time (minutes)',
        'base_price' => 'Base Price',
        'recommended_sessions' => 'Recommended Sessions',
        'session_interval_days' => 'Session Interval (days)',
        'fitzpatrick_min' => 'Min Fitzpatrick Type',
        'fitzpatrick_max' => 'Max Fitzpatrick Type',
        'contraindications' => 'Contraindications',
        'pre_care' => 'Pre-care Instructions',
        'post_care' => 'Post-care Instructions',
        'is_active' => 'Active',
        'requires_consent' => 'Requires Consent',
        'consent_template' => 'Consent Template',
        'equipment_required' => 'Equipment Required',
        'qualified_staff' => 'Qualified Staff',
        'service_rooms' => 'Service Rooms',
        'required_equipment' => 'Required Equipment',
        'allowed_days' => 'Allowed Days',
        'allowed_time_start' => 'Start Time',
        'allowed_time_end' => 'End Time',
        'min_advance_hours' => 'Minimum Advance Booking',
        'max_advance_days' => 'Maximum Advance Booking',
        'blackout_dates' => 'Blackout Dates',
        'blackout_dates_help' => 'Enter dates when this service cannot be booked (format: YYYY-MM-DD)',
        'hours' => 'hours',
        'days' => 'days',
    ],

    'tabs' => [
        'scheduling' => 'Scheduling & Resources',
    ],

    'sections' => [
        'qualified_staff' => 'Qualified Staff',
        'qualified_staff_description' => 'Staff members who are qualified to perform this service',
        'rooms' => 'Service Rooms',
        'rooms_description' => 'Rooms where this service can be performed',
        'required_equipment' => 'Required Equipment',
        'required_equipment_description' => 'Specific equipment items required for this service',
        'time_restrictions' => 'Time Slot Restrictions',
        'time_restrictions_description' => 'Configure when this service can be booked',
    ],

    'staff' => [
        'practitioner' => 'Practitioner',
        'name' => 'Name',
        'job_title' => 'Job Title',
        'email' => 'Email',
        'phone' => 'Phone',
        'add_staff' => 'Add Staff',
    ],

    'rooms' => [
        'room' => 'Room',
        'branch' => 'Branch',
        'is_primary' => 'Primary Room',
        'is_primary_help' => 'The preferred room for this service',
        'priority' => 'Priority',
        'priority_help' => 'Lower number = higher priority for backup rooms',
        'type' => 'Room Type',
        'set_as_primary' => 'Set as Primary',
        'primary_updated' => 'Primary room updated successfully',
        'add_room' => 'Add Room',
    ],

    'equipment' => [
        'equipment' => 'Equipment',
        'code' => 'Code',
        'name' => 'Name',
        'type' => 'Type',
        'branch' => 'Branch',
        'status' => 'Status',
        'is_mandatory' => 'Mandatory',
        'is_mandatory_help' => 'If enabled, this equipment must be available to book the service',
        'mark_optional' => 'Mark as Optional',
        'mark_mandatory' => 'Mark as Mandatory',
        'add_equipment' => 'Add Equipment',
    ],

    'actions' => [
        'remove' => 'Remove',
        'remove_selected' => 'Remove Selected',
    ],

    'consent' => [
        'name' => 'Template Name',
        'content' => 'Content',
        'version' => 'Version',
        'is_active' => 'Active',
        'valid_days' => 'Valid for (days)',
    ],

    'pricing' => [
        'branch' => 'Branch',
        'price' => 'Price',
        'is_active' => 'Active',
    ],

    'messages' => [
        'created' => 'Service created successfully.',
        'updated' => 'Service updated successfully.',
        'deleted' => 'Service deleted successfully.',
    ],

    'category_tree' => [
        'title' => 'Category Tree',
        'navigation' => 'Category Tree',
        'tree_view' => 'Category Structure',
        'list_view' => 'List View',
        'drag_hint' => 'Drag categories to reorder or nest them',
        'create_category' => 'Create Category',
        'no_categories' => 'No categories yet',
        'create_first' => 'Create your first service category to get started.',
        'subcategories' => 'subcategories',
        'active' => 'Active',
        'inactive' => 'Inactive',
        'click_activate' => 'Click to activate',
        'click_deactivate' => 'Click to deactivate',
        'reordered' => 'Categories reordered successfully',
        'activated' => 'Category activated',
        'deactivated' => 'Category deactivated',
    ],
];
