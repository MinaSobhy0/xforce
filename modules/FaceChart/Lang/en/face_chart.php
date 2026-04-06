<?php

return [
    'title' => 'Face Chart',
    'subtitle' => '3D Face Procedure Mapping',

    'navigation' => 'Face Chart',
    'singular' => 'Marker',
    'plural' => 'Markers',

    'tabs' => [
        '3d_view' => '3D Chart',
        '2d_view' => '2D Annotation',
    ],

    'viewer' => [
        'title' => '3D Face Chart',
        'loading' => 'Loading 3D model...',
        'loading_error' => 'Failed to load 3D model',
        'controls' => [
            'rotate' => 'Drag to rotate',
            'zoom' => 'Scroll to zoom',
            'pan' => 'Right-click drag to pan',
            'reset' => 'Reset View',
        ],
        'edit_mode' => 'Edit Mode',
        'view_mode' => 'View Mode',
        'toggle_edit' => 'Toggle Edit Mode',
        'click_to_add' => 'Click on face to add marker',
        'drag_to_draw' => 'Drag from marker to set direction',
        'drawing_arrow' => 'Drawing injection direction...',
        'press_esc' => 'Press ESC to cancel',
        'historical_markers' => 'Historical Markers',
        'current_markers' => 'Current Session Markers',
    ],

    'marker' => [
        'add' => 'Add Marker',
        'edit' => 'Edit Marker',
        'delete' => 'Delete Marker',
        'details' => 'Marker Details',
        'new' => 'New Marker',
        'save' => 'Save Marker',
        'cancel' => 'Cancel',
    ],

    'fields' => [
        'marker_type' => 'Type',
        'face_region' => 'Region',
        'product_name' => 'Product',
        'units' => 'Dosage',
        'unit_type' => 'Unit',
        'color' => 'Color',
        'notes' => 'Notes',
        'notes_placeholder' => 'Add any notes about this injection point...',
        'direction' => 'Injection Direction',
        'depth' => 'Depth (mm)',
        'drag_instruction_title' => 'Drag to Draw Arrow',
        'drag_instruction_desc' => 'Close this modal and drag from the marker to set the injection direction visually.',
        'direction_set' => 'Direction Set',
        'clear_direction' => 'Clear direction',
        'use_preset' => 'Or use a preset direction...',
        'performed_at' => 'Date',
        'performed_by' => 'Performed By',
        'appointment' => 'Appointment',
        'service' => 'Service',
        'coordinates' => 'Coordinates',
    ],

    'regions' => [
        'forehead' => 'Forehead',
        'glabella' => 'Glabella',
        'temples' => 'Temples',
        'crow_feet' => "Crow's Feet",
        'upper_eyelid' => 'Upper Eyelid',
        'lower_eyelid' => 'Lower Eyelid',
        'nose' => 'Nose',
        'cheeks' => 'Cheeks',
        'nasolabial' => 'Nasolabial Folds',
        'upper_lip' => 'Upper Lip',
        'lower_lip' => 'Lower Lip',
        'marionette' => 'Marionette Lines',
        'chin' => 'Chin',
        'jawline' => 'Jawline',
        'neck' => 'Neck',
    ],

    'marker_types' => [
        'injection' => 'Injection',
        'filler_point' => 'Filler Point',
        'laser_spot' => 'Laser Spot',
        'thread_anchor' => 'Thread Anchor',
        'marking' => 'Marking',
    ],

    'unit_types' => [
        'units' => 'Units',
        'ml' => 'mL',
        'cc' => 'cc',
    ],

    'filters' => [
        'title' => 'Filters',
        'date_from' => 'From Date',
        'date_to' => 'To Date',
        'region' => 'Region',
        'type' => 'Type',
        'apply' => 'Apply Filters',
        'clear' => 'Clear Filters',
        'all_regions' => 'All Regions',
        'all_types' => 'All Types',
    ],

    'stats' => [
        'title' => 'Statistics',
        'total_markers' => 'Total Markers',
        'total_units' => 'Total Units',
        'appointments' => 'Appointments',
        'by_type' => 'By Type',
        'by_region' => 'By Region',
    ],

    'messages' => [
        'marker_added' => 'Marker added successfully',
        'marker_updated' => 'Marker updated successfully',
        'marker_deleted' => 'Marker deleted successfully',
        'cannot_edit_historical' => 'Cannot edit markers from previous appointments',
        'cannot_delete_historical' => 'Cannot delete markers from previous appointments',
        'no_markers' => 'No markers found',
        'no_appointment' => 'Start a treatment session to add markers',
    ],

    'confirm' => [
        'delete_marker' => 'Are you sure you want to delete this marker?',
    ],

    'legend' => [
        'title' => 'Legend',
        'historical' => 'Historical (read-only)',
        'current' => 'Current Session (editable)',
        'injection' => 'Injection/Botox',
        'laser' => 'Laser Treatment',
        'filler' => 'Filler',
        'thread' => 'Thread Lift',
    ],

    '2d' => [
        'title' => '2D Face Chart',
        'tools' => [
            'marker' => 'Add Marker',
            'text' => 'Text Annotation',
            'arrow' => 'Draw Arrow',
            'pen' => 'Free Drawing (Pen)',
            'select' => 'Select/Move',
            'eraser' => 'Tap to Delete',
            'color' => 'Drawing Color',
            'size' => 'Stroke Size',
            'delete' => 'Delete Selected',
            'clear' => 'Clear All',
        ],
        'text_annotation' => [
            'placeholder' => 'Click to edit text',
            'font_size' => 'Font Size',
            'font_color' => 'Text Color',
            'bold' => 'Bold',
            'italic' => 'Italic',
        ],
        'marker_count' => ':count markers',
        'markers_list' => 'Markers',
        'no_markers' => 'No markers yet. Click on the face image to add markers.',
        'unnamed_marker' => 'Unnamed Marker',
        'confirm_delete' => 'Are you sure you want to delete this marker?',
        'confirm_clear' => 'Are you sure you want to clear all editable annotations?',
    ],

    'statistics' => [
        'title' => 'Statistics',
        'total_markers' => 'Total Markers',
        'total_units' => 'Total Units',
        'appointments' => 'Appointments',
    ],
];
