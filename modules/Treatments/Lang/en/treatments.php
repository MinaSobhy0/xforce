<?php

return [
    'module_name' => 'Treatments',
    'module_description' => 'Treatment catalog and consent template management',

    'navigation' => [
        'treatments' => 'Treatments',
        'categories' => 'Categories',
        'consent_templates' => 'Consent Templates',
    ],

    'labels' => [
        'treatment' => 'Treatment',
        'treatments' => 'Treatments',
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
        'created' => 'Treatment created successfully.',
        'updated' => 'Treatment updated successfully.',
        'deleted' => 'Treatment deleted successfully.',
    ],

    'category_tree' => [
        'title' => 'Category Tree',
        'navigation' => 'Category Tree',
        'tree_view' => 'Category Structure',
        'list_view' => 'List View',
        'drag_hint' => 'Drag categories to reorder or nest them',
        'create_category' => 'Create Category',
        'no_categories' => 'No categories yet',
        'create_first' => 'Create your first treatment category to get started.',
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
