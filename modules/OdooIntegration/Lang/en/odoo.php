<?php

return [
    // Navigation & Pages
    'connections' => 'Odoo Connections',
    'connection' => 'Odoo Connection',
    'pages' => [
        'dashboard' => 'Odoo Sync',
        'conflicts' => 'Sync Conflicts',
    ],

    // Sections
    'sections' => [
        'connection_details' => 'Connection Details',
        'authentication' => 'Authentication',
        'settings' => 'Settings',
        'entity_mapping' => 'Entity Mapping',
        'filters' => 'Filters',
        'recent_activity' => 'Recent Activity',
    ],

    // Fields
    'fields' => [
        'name' => 'Name',
        'code' => 'Code',
        'host' => 'Host URL',
        'port' => 'Port',
        'database' => 'Database Name',
        'protocol' => 'Protocol',
        'username' => 'Username',
        'password' => 'Password',
        'api_key' => 'API Key',
        'use_ssl' => 'Use SSL',
        'timeout' => 'Timeout',
        'rate_limit' => 'Rate Limit',
        'timezone' => 'Timezone',
        'is_active' => 'Active',
        'is_default' => 'Default Connection',
        'last_connected' => 'Last Connected',
        'last_sync' => 'Last Sync',
        'mappings' => 'Mappings',
        'local_model' => 'Local Model',
        'local_table' => 'Local Table',
        'odoo_model' => 'Odoo Model',
        'sync_direction' => 'Sync Direction',
        'sync_frequency' => 'Sync Frequency',
        'conflict_resolution' => 'Conflict Resolution',
        'batch_size' => 'Batch Size',
        'priority' => 'Priority',
        'filter_conditions' => 'Filter Conditions',
        'entity' => 'Entity',
        'records' => 'Records',
        'conflicts' => 'Conflicts',
        'sync_type' => 'Sync Type',
        'direction' => 'Direction',
        'status' => 'Status',
        'processed' => 'Processed',
        'created' => 'Created',
        'updated' => 'Updated',
        'failed' => 'Failed',
        'started_at' => 'Started At',
        'duration' => 'Duration',
        'triggered_by' => 'Triggered By',
        'conflict_type' => 'Conflict Type',
        'type' => 'Type',
        'local_id' => 'Local ID',
        'odoo_id' => 'Odoo ID',
        'resolution' => 'Resolution',
        'resolved_by' => 'Resolved By',
        'detected_at' => 'Detected At',
        'detected' => 'Detected',
        'notes' => 'Notes',
    ],

    // Helpers
    'helpers' => [
        'code' => 'Unique identifier for this connection (auto-generated if empty)',
        'api_key' => 'Optional API key for authentication (Odoo 14+)',
        'is_default' => 'Use this connection as the default for sync operations',
        'priority' => 'Lower numbers sync first (e.g., Users before Employees)',
        'filter_conditions' => 'Odoo domain filters (e.g., [["active", "=", true]])',
    ],

    // Units
    'units' => [
        'seconds' => 'seconds',
        'per_minute' => 'per minute',
    ],

    // Actions
    'actions' => [
        'test_connection' => 'Test Connection',
        'sync_all' => 'Sync All',
        'sync' => 'Sync',
        'full_sync' => 'Full Sync',
        'create_connection' => 'Create Connection',
        'view_errors' => 'View Errors',
        'view_diff' => 'View Differences',
        'keep_local' => 'Keep Local',
        'keep_odoo' => 'Keep Odoo',
        'dismiss' => 'Dismiss',
        'dismiss_all' => 'Dismiss All',
        'resolve' => 'Resolve',
    ],

    // Messages
    'messages' => [
        'connection_success' => 'Connection successful!',
        'connection_failed' => 'Connection failed',
        'sync_queued' => 'Sync job queued successfully',
        'full_sync_warning' => 'Full sync may take a while depending on data volume',
        'conflict_resolved' => 'Conflict resolved successfully',
        'conflict_dismissed' => 'Conflict dismissed',
        'conflicts_dismissed' => 'Selected conflicts dismissed',
    ],

    // Modals
    'modals' => [
        'sync_all_title' => 'Sync All Entities',
        'sync_all_description' => 'This will queue sync jobs for all active entity mappings. Continue?',
        'full_sync_title' => 'Full Sync',
        'full_sync_description' => 'This will perform a full sync, ignoring watermarks. This may take longer and process more records. Continue?',
        'conflict_diff_title' => 'Conflict Details',
    ],

    // Labels
    'labels' => [
        'all_entities' => 'All Entities',
        'records' => 'records',
        'local_data' => 'Local Data',
        'odoo_data' => 'Odoo Data',
        'changed_fields' => 'Changed Fields',
        'record_deleted' => 'Record was deleted',
    ],

    // Empty States
    'empty' => [
        'no_connections' => 'No Odoo Connections',
        'no_connections_description' => 'Get started by creating your first Odoo connection.',
        'no_recent_activity' => 'No recent sync activity',
        'no_conflicts' => 'No Pending Conflicts',
        'no_conflicts_description' => 'All sync conflicts have been resolved.',
        'no_errors' => 'No errors to display',
    ],

    // Stats
    'stats' => [
        'active_connections' => 'Active Connections',
        'connections_description' => 'Configured Odoo servers',
        'synced_today' => 'Synced Today',
        'synced_description' => 'Records processed',
        'total_synced' => 'Total Synced',
        'total_description' => 'Records linked to Odoo',
        'pending_conflicts' => 'Pending Conflicts',
        'conflicts_description' => 'Require manual resolution',
        'failed_today' => 'Failed Today',
        'failed_description' => 'Records with errors',
        'last_sync' => 'Last Sync',
        'no_sync_yet' => 'No sync completed yet',
    ],

    // Widgets
    'widgets' => [
        'pending_conflicts' => 'Pending Conflicts',
    ],
];
