<?php

return [
    // Action labels
    'action_label' => 'Import',
    'label' => 'Import :model',

    // Modal
    'modal' => [
        'heading' => 'Import :label',
        'actions' => [
            'import' => 'Import',
            'download_template' => 'Download Template',
        ],
        'form' => [
            'file' => [
                'label' => 'File',
                'placeholder' => 'Drag and drop your CSV or Excel file here',
            ],
            'import_mode' => [
                'label' => 'Import Mode',
                'options' => [
                    'create_only' => 'Create new records only',
                    'create_and_update' => 'Create new and update existing',
                    'update_only' => 'Update existing records only',
                ],
            ],
            'saved_mapping' => [
                'label' => 'Saved Mapping',
                'placeholder' => 'Select a saved mapping...',
            ],
            'columns' => [
                'label' => 'Column Mapping',
            ],
            'skip_column' => '-- Skip this field --',
            'save_mapping' => [
                'label' => 'Save this mapping for future use',
            ],
            'mapping_name' => [
                'label' => 'Mapping Name',
                'placeholder' => 'e.g., Product Import v1',
            ],
        ],
    ],

    // Notifications
    'notifications' => [
        'completed_body' => 'Your import has completed. :count of :total rows were processed successfully.',
        'failed_rows' => ':count rows failed to import.',
        'mapping_saved' => 'Mapping saved successfully.',
        'started' => [
            'title' => 'Import Started',
            'body' => 'Your import of :count rows has started and will be processed in the background.',
        ],
        'completed' => [
            'title' => 'Import Completed',
        ],
    ],

    // Field labels
    'fields' => [
        'sku' => 'SKU',
        'code' => 'Code',
        'name' => 'Name',
        'description' => 'Description',
        'category' => 'Category',
        'unit' => 'Unit',
        'cost_price' => 'Cost Price',
        'sell_price' => 'Sell Price',
        'price' => 'Price',
        'reorder_point' => 'Reorder Point',
        'reorder_quantity' => 'Reorder Quantity',
        'barcode' => 'Barcode',
        'is_consumable' => 'Is Consumable',
        'is_active' => 'Is Active',
        'first_name' => 'First Name',
        'last_name' => 'Last Name',
        'email' => 'Email',
        'phone' => 'Phone',
        'secondary_phone' => 'Secondary Phone',
        'mobile' => 'Mobile',
        'date_of_birth' => 'Date of Birth',
        'gender' => 'Gender',
        'national_id' => 'National ID',
        'address' => 'Address',
        'city' => 'City',
        'country' => 'Country',
        'occupation' => 'Occupation',
        'emergency_contact_name' => 'Emergency Contact Name',
        'emergency_contact_phone' => 'Emergency Contact Phone',
        'emergency_contact_relation' => 'Emergency Contact Relation',
        'referral_source' => 'Referral Source',
        'language' => 'Language',
        'tags' => 'Tags',
        'notes' => 'Notes',
        'marketing_consent' => 'Marketing Consent',
        'sms_consent' => 'SMS Consent',
        'email_consent' => 'Email Consent',
        'whatsapp_consent' => 'WhatsApp Consent',
        'contact_person' => 'Contact Person',
        'tax_number' => 'Tax Number',
        'payment_terms_days' => 'Payment Terms (Days)',
        'currency_code' => 'Currency Code',
        'duration_minutes' => 'Duration (Minutes)',
        'buffer_minutes' => 'Buffer (Minutes)',
        'recommended_sessions' => 'Recommended Sessions',
        'session_interval_days' => 'Session Interval (Days)',
        'requires_consent' => 'Requires Consent',
        'is_bookable_online' => 'Bookable Online',
    ],

    // Preview & Validation
    'preview' => [
        'title' => 'Import Preview',
        'valid_rows' => 'Valid rows',
        'error_rows' => 'Rows with errors',
        'will_create' => 'Will create',
        'will_update' => 'Will update',
        'errors' => 'Errors',
        'row' => 'Row :number',
        'skip_invalid' => 'Skip invalid rows',
        'abort_on_errors' => 'Abort on errors',
        'download_error_report' => 'Download Error Report',
    ],

    // Progress
    'progress' => [
        'title' => 'Importing...',
        'created' => 'Created',
        'updated' => 'Updated',
        'skipped' => 'Skipped',
        'running_in_background' => 'Running in background...',
    ],
];
