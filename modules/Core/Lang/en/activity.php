<?php

return [
    // Tab/Section title
    'title' => 'Activity Log',

    // Table columns
    'columns' => [
        'event' => 'Event',
        'description' => 'Description',
        'changed_by' => 'Changed By',
        'when' => 'When',
        'changes' => 'Changes',
    ],

    // Event types
    'events' => [
        'created' => 'Created',
        'updated' => 'Updated',
        'deleted' => 'Deleted',
        'restored' => 'Restored',
    ],

    // Filters
    'filters' => [
        'event_type' => 'Event Type',
        'changed_by' => 'Changed By',
        'from_date' => 'From Date',
        'to_date' => 'To Date',
        'from' => 'From',
        'to' => 'To',
    ],

    // View modal
    'view' => [
        'title' => 'Activity Details',
        'event_info' => 'Event Information',
        'changes' => 'Changes Made',
    ],

    // Empty state
    'empty' => [
        'heading' => 'No activity recorded',
        'description' => 'Activity will appear here when changes are made to this record.',
    ],

    // System user
    'system' => 'System',

    // Change display
    'changes_display' => [
        'old_value' => 'Old Value',
        'new_value' => 'New Value',
        'field' => 'Field',
        'no_changes' => 'No changes recorded',
    ],
];
