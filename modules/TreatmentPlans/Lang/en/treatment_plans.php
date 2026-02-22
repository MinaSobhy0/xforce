<?php

return [
    // Navigation & Labels
    'navigation_label' => 'Treatment Plans',
    'model_label' => 'Treatment Plan',
    'plural_label' => 'Treatment Plans',

    // Sections
    'sections' => [
        'basic_info' => 'Basic Information',
        'plan_details' => 'Plan Details',
        'dates' => 'Dates',
        'package_info' => 'Package Information',
        'services' => 'Services',
        'progress' => 'Progress',
        'notes' => 'Notes',
        'appointments' => 'Appointments',
    ],

    // Fields
    'fields' => [
        'code' => 'Code',
        'name' => 'Name',
        'name_en' => 'Name (English)',
        'name_ar' => 'Name (Arabic)',
        'description' => 'Description',
        'description_en' => 'Description (English)',
        'description_ar' => 'Description (Arabic)',
        'patient' => 'Patient',
        'branch' => 'Branch',
        'status' => 'Status',
        'source' => 'Source',
        'start_date' => 'Start Date',
        'target_end_date' => 'Target End Date',
        'actual_end_date' => 'Actual End Date',
        'recommended_package' => 'Recommended Package',
        'package_subscription' => 'Package Subscription',
        'notes' => 'Notes',
        'internal_notes' => 'Internal Notes',
        'created_by' => 'Created By',
        'created_at' => 'Created At',
        'activated_at' => 'Activated At',
        'completed_at' => 'Completed At',
        'cancelled_at' => 'Cancelled At',
        'cancellation_reason' => 'Cancellation Reason',

        // Item fields
        'service' => 'Service',
        'recommended_sessions' => 'Recommended Sessions',
        'completed_sessions' => 'Completed Sessions',
        'remaining_sessions' => 'Remaining Sessions',
        'session_interval_days' => 'Session Interval (Days)',
        'preferred_practitioner' => 'Preferred Practitioner',
        'preferred_day_of_week' => 'Preferred Days',
        'preferred_time_slot' => 'Preferred Time',
        'sort_order' => 'Sort Order',

        // Appointment fields
        'appointment' => 'Appointment',
        'session_number' => 'Session #',
        'date' => 'Date',
        'time' => 'Time',
        'practitioner' => 'Practitioner',
    ],

    // Statuses
    'statuses' => [
        'draft' => 'Draft',
        'active' => 'Active',
        'paused' => 'Paused',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ],

    // Item statuses
    'item_statuses' => [
        'pending' => 'Pending',
        'in_progress' => 'In Progress',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ],

    // Sources
    'sources' => [
        'manual' => 'Manual Entry',
        'consultation' => 'From Consultation',
    ],

    // Time slots
    'time_slots' => [
        'morning' => 'Morning (9AM - 12PM)',
        'afternoon' => 'Afternoon (12PM - 5PM)',
        'evening' => 'Evening (5PM - 9PM)',
    ],

    // Days of week
    'days_of_week' => [
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    ],

    // Actions
    'actions' => [
        'create' => 'Create Treatment Plan',
        'edit' => 'Edit Treatment Plan',
        'view' => 'View Treatment Plan',
        'delete' => 'Delete Treatment Plan',
        'activate' => 'Activate',
        'pause' => 'Pause',
        'resume' => 'Resume',
        'complete' => 'Mark Complete',
        'cancel' => 'Cancel',
        'add_item' => 'Add Service',
        'remove_item' => 'Remove Service',
        'book_appointment' => 'Book Appointment',
        'buy_package' => 'Buy Package',
        'link_package' => 'Link Package',
    ],

    // Messages
    'messages' => [
        'created' => 'Treatment plan created successfully.',
        'updated' => 'Treatment plan updated successfully.',
        'deleted' => 'Treatment plan deleted successfully.',
        'activated' => 'Treatment plan activated successfully.',
        'paused' => 'Treatment plan paused successfully.',
        'resumed' => 'Treatment plan resumed successfully.',
        'completed' => 'Treatment plan marked as completed.',
        'cancelled' => 'Treatment plan cancelled.',
        'item_added' => 'Service added to treatment plan.',
        'item_removed' => 'Service removed from treatment plan.',
        'package_linked' => 'Package subscription linked to treatment plan.',
        'cannot_edit' => 'This treatment plan cannot be edited.',
        'cannot_transition' => 'Cannot change status to :status.',
    ],

    // Progress
    'progress' => [
        'overall' => 'Overall Progress',
        'sessions_completed' => ':completed of :total sessions completed',
        'percentage' => ':percent% complete',
        'days_remaining' => ':days days remaining',
        'overdue' => 'Overdue by :days days',
        'items_needing_scheduling' => ':count services need scheduling',
    ],

    // Widgets & Stats
    'stats' => [
        'total_plans' => 'Total Plans',
        'active_plans' => 'Active Plans',
        'completed_plans' => 'Completed Plans',
        'completion_rate' => 'Completion Rate',
    ],

    // Package section
    'package' => [
        'recommended' => 'Recommended Package',
        'not_purchased' => 'Not Purchased',
        'purchased' => 'Purchased',
        'sessions_remaining' => ':count sessions remaining',
        'save_amount' => 'Save :amount',
        'buy_now' => 'Buy Package',
        'no_recommendation' => 'No package recommended for this plan.',
    ],

    // Relation managers
    'items' => [
        'title' => 'Plan Services',
        'empty' => 'No services added to this plan yet.',
        'add' => 'Add Service',
    ],

    'plan_appointments' => [
        'title' => 'Plan Appointments',
        'empty' => 'No appointments scheduled yet.',
        'add' => 'Schedule Appointment',
    ],

    // Filters
    'filters' => [
        'status' => 'Status',
        'patient' => 'Patient',
        'branch' => 'Branch',
        'date_range' => 'Date Range',
        'created_by' => 'Created By',
        'has_package' => 'Has Package',
    ],

    // Tabs
    'tabs' => [
        'overview' => 'Overview',
        'services' => 'Services',
        'appointments' => 'Appointments',
        'history' => 'History',
    ],

    // Tooltips
    'tooltips' => [
        'session_interval' => 'Recommended days between sessions',
        'preferred_time' => 'Patient\'s preferred appointment time',
        'progress_bar' => 'Progress towards completing all sessions',
    ],

    // Confirmations
    'confirmations' => [
        'activate' => 'Are you sure you want to activate this treatment plan?',
        'cancel' => 'Are you sure you want to cancel this treatment plan? This action cannot be undone.',
        'complete' => 'Are you sure you want to mark this treatment plan as completed?',
        'delete' => 'Are you sure you want to delete this treatment plan?',
    ],
];
