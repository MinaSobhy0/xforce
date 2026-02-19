<?php

return [
    // Module info
    'module_name' => 'Core',
    'module_description' => 'Core system functionality including tenant management and system settings',

    // Tenants
    'tenant' => 'Tenant',
    'tenants' => 'Tenants',
    'tenant_name' => 'Tenant Name',
    'tenant_slug' => 'Slug',
    'tenant_status' => 'Status',
    'tenant_plan' => 'Subscription Plan',
    'tenant_created' => 'Created',
    'tenant_trial_ends' => 'Trial Ends',

    // Tenant Statuses
    'status_active' => 'Active',
    'status_inactive' => 'Inactive',
    'status_suspended' => 'Suspended',
    'status_trial' => 'Trial',

    // Settings
    'settings' => 'Settings',
    'system_settings' => 'System Settings',
    'general_settings' => 'General Settings',
    'save_settings' => 'Save Settings',
    'settings_saved' => 'Settings saved successfully',

    // Modules
    'modules' => 'Modules',
    'module_management' => 'Module Management',
    'activate_module' => 'Activate Module',
    'deactivate_module' => 'Deactivate Module',
    'module_activated' => 'Module activated successfully',
    'module_deactivated' => 'Module deactivated successfully',
    'module_required_dependency' => 'Cannot deactivate: other modules depend on this',

    // Actions
    'create' => 'Create',
    'edit' => 'Edit',
    'delete' => 'Delete',
    'view' => 'View',
    'save' => 'Save',
    'cancel' => 'Cancel',
    'confirm' => 'Confirm',
    'search' => 'Search',
    'filter' => 'Filter',
    'export' => 'Export',
    'import' => 'Import',

    // Common
    'name' => 'Name',
    'description' => 'Description',
    'status' => 'Status',
    'actions' => 'Actions',
    'created_at' => 'Created At',
    'updated_at' => 'Updated At',
    'yes' => 'Yes',
    'no' => 'No',
    'all' => 'All',
    'none' => 'None',

    // Branches
    'branch' => 'Branch',
    'branches' => 'Branches',

    // Rooms
    'room' => 'Room',
    'rooms' => 'Rooms',
    'room_details' => 'Room Details',
    'room_type' => 'Room Type',
    'room_types' => [
        'treatment' => 'Treatment Room',
        'consultation' => 'Consultation Room',
        'waiting' => 'Waiting Area',
        'reception' => 'Reception',
        'storage' => 'Storage',
        'staff' => 'Staff Room',
        'other' => 'Other',
    ],
    'capacity' => 'Capacity',
    'floor' => 'Floor',
    'color' => 'Color',
    'code' => 'Code',
    'active' => 'Active',
    'bookable' => 'Bookable',
    'bookable_help' => 'Can this room be used for appointments?',
    'sort_order' => 'Sort Order',
    'additional_settings' => 'Additional Settings',
    'setting_key' => 'Setting Key',
    'setting_value' => 'Setting Value',

    // Messages
    'confirm_delete' => 'Are you sure you want to delete this?',
    'record_created' => 'Record created successfully',
    'record_updated' => 'Record updated successfully',
    'record_deleted' => 'Record deleted successfully',
    'error_occurred' => 'An error occurred',
];
