<?php

return [
    'navigation' => [
        'plans' => 'Commission Plans',
    ],

    'labels' => [
        'plan' => 'Commission Plan',
        'plans' => 'Commission Plans',
        'service_rules' => 'Service Rules',
        'assigned_staff' => 'Assigned Staff',
    ],

    'sections' => [
        'plan_details' => 'Plan Details',
        'default_commission' => 'Default Commission',
        'default_commission_description' => 'Default commission applied when no specific service rule matches.',
    ],

    'fields' => [
        'name' => 'Name',
        'description' => 'Description',
        'commission_type' => 'Commission Type',
        'percentage' => 'Percentage',
        'flat_amount' => 'Flat Amount',
        'tier_from' => 'Revenue From',
        'tier_to' => 'Revenue To',
        'default_value' => 'Default',
        'assigned_staff' => 'Assigned Staff',
        'service_rules' => 'Service Rules',
        'is_active' => 'Active',
        'created_at' => 'Created',
        'service' => 'Service',
        'category' => 'Category',
        'applies_to' => 'Applies To',
        'value' => 'Value',
    ],

    'commission_types' => [
        'percentage' => 'Percentage',
        'flat' => 'Flat Amount',
        'tiered' => 'Tiered',
    ],

    'help' => [
        'service_specific' => 'Select a specific service for this rule, or leave empty for category-level rule.',
        'category_fallback' => 'Select a category. If no service is selected, this rule applies to all services in the category.',
        'tiered_requires_rules' => 'Tiered commission requires configuring Service Rules with revenue ranges.',
        'tiered_title' => 'Tiered Commission',
        'tiered_description' => 'For tiered commission, the default will be 0%. You must add Service Rules below to define commission tiers based on revenue ranges. Each tier specifies a revenue range (from/to) and a percentage.',
    ],

    'messages' => [
        'no_plan_assigned' => 'No commission plan assigned.',
    ],
];
