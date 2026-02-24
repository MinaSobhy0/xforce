<?php

return [
    'navigation_label' => 'Treatment Analytics',
    'title' => 'Treatment Analytics',
    'heading' => 'Treatment Analytics',
    'subheading' => 'Analyze treatment sessions, equipment usage, and performance metrics',

    // Filters
    'filters' => [
        'date_range' => 'Date Range',
        'service' => 'Service',
        'practitioner' => 'Practitioner',
        'all_services' => 'All Services',
        'all_practitioners' => 'All Practitioners',
    ],

    // Date ranges
    'date_ranges' => [
        '7_days' => 'Last 7 Days',
        '30_days' => 'Last 30 Days',
        '90_days' => 'Last 90 Days',
        '180_days' => 'Last 6 Months',
        '1_year' => 'Last Year',
    ],

    // Metrics
    'metrics' => [
        'total_sessions' => 'Total Sessions',
        'completed_sessions' => 'Completed Sessions',
        'completion_rate' => 'Completion Rate',
        'avg_duration' => 'Avg. Duration',
        'avg_pain_level' => 'Avg. Pain Level',
        'total_revenue' => 'Total Revenue',
        'minutes' => 'min',
    ],

    // Sections
    'sections' => [
        'summary' => 'Summary',
        'top_services' => 'Top Services',
        'practitioner_performance' => 'Practitioner Performance',
        'equipment_utilization' => 'Equipment Utilization',
        'consumables_usage' => 'Consumables Usage',
        'products_usage' => 'Products Usage',
        'session_trends' => 'Session Trends',
        'skin_reactions' => 'Skin Reactions',
        'parameter_statistics' => 'Parameter Statistics',
    ],

    // Table headers
    'headers' => [
        'service' => 'Service',
        'sessions' => 'Sessions',
        'avg_duration' => 'Avg. Duration',
        'total_revenue' => 'Revenue',
        'practitioner' => 'Practitioner',
        'completed' => 'Completed',
        'avg_pain' => 'Avg. Pain',
        'equipment' => 'Equipment',
        'total_shots' => 'Total Shots',
        'total_energy' => 'Total Energy',
        'consumable' => 'Consumable',
        'quantity_used' => 'Qty Used',
        'total_cost' => 'Total Cost',
        'product' => 'Product',
        'applied' => 'Applied',
        'sold' => 'Sold',
        'total_value' => 'Total Value',
        'date' => 'Date',
        'count' => 'Count',
        'reaction' => 'Reaction',
        'percentage' => 'Percentage',
        'parameter' => 'Parameter',
        'min' => 'Min',
        'max' => 'Max',
        'avg' => 'Average',
        'median' => 'Median',
        'std_dev' => 'Std Dev',
        'unit' => 'Unit',
    ],

    // Skin reactions
    'skin_reactions' => [
        'none' => 'None',
        'mild_erythema' => 'Mild Erythema',
        'moderate_erythema' => 'Moderate Erythema',
        'severe_erythema' => 'Severe Erythema',
        'edema' => 'Edema',
        'blistering' => 'Blistering',
        'hyperpigmentation' => 'Hyperpigmentation',
        'hypopigmentation' => 'Hypopigmentation',
        'crusting' => 'Crusting',
        'other' => 'Other',
    ],

    // Empty states
    'empty' => [
        'no_data' => 'No data available for the selected period',
        'no_services' => 'No services found',
        'no_practitioners' => 'No practitioner data',
        'no_equipment' => 'No equipment usage recorded',
        'no_consumables' => 'No consumables used',
        'no_products' => 'No products used',
        'no_parameters' => 'Select a service to view parameter statistics',
        'no_reactions' => 'No skin reactions recorded',
    ],

    // Actions
    'actions' => [
        'export' => 'Export Report',
        'refresh' => 'Refresh Data',
        'print' => 'Print Report',
    ],

    // Tooltips
    'tooltips' => [
        'completion_rate' => 'Percentage of sessions completed vs total scheduled',
        'avg_duration' => 'Average session duration in minutes',
        'avg_pain' => 'Average pain level reported (0-10 scale)',
    ],

    // Labels (used in various places)
    'labels' => [
        'sessions' => 'sessions',
    ],

    // No data message
    'no_data' => 'No data available',

    // Equipment section
    'equipment' => [
        'name' => 'Equipment',
        'sessions' => 'Sessions',
        'total_shots' => 'Total Shots',
        'avg_shots' => 'Avg Shots',
    ],

    // Consumables section
    'consumables' => [
        'product' => 'Product',
        'used' => 'Used',
        'total_cost' => 'Total Cost',
        'times' => 'times',
    ],

    // Products section
    'products' => [
        'name' => 'Product',
        'type' => 'Type',
        'quantity' => 'Quantity',
        'revenue' => 'Revenue',
        'sold' => 'Sold',
        'applied' => 'Applied',
    ],

    // Parameters section
    'parameters' => [
        'name' => 'Parameter',
        'count' => 'Count',
        'min' => 'Min',
        'max' => 'Max',
        'avg' => 'Average',
        'median' => 'Median',
        'std_dev' => 'Std Dev',
    ],
];
