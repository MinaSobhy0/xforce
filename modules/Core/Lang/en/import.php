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
        'failed_title' => 'Import Failed',
        'failed_body' => 'The import could not be completed. Please check your file and try again.',
        'success_title' => 'Import Successful',
        'no_data' => 'No data found in the uploaded file.',
        'invalid_file' => 'The uploaded file could not be read. Please check the file format.',
        'no_file' => 'Please upload a file to import.',
        'empty_file' => 'The uploaded file is empty or has no data rows.',
        'success' => 'Import Successful',
        'success_body' => ':count records imported successfully.',
        'partial_success' => 'Import Completed with Errors',
        'partial_success_body' => ':success records imported successfully, :failed records failed.',
        'failed' => 'Import Failed',
        'failed_body' => 'All :count records failed to import.',
        'row_error' => 'Row :row Error',
        'more_errors' => ':count more errors not shown',
    ],

    // Validation messages
    'validation' => [
        'missing_mappings' => 'Required Mappings Missing',
        'required_mapping' => 'The ":column" field is required and must be mapped to a column.',
        'field_required' => 'This field is required.',
        'field_string' => 'This field must be a string.',
        'field_max' => 'This field may not exceed :max characters.',
        'no_file' => 'Please upload a file to import.',
        'invalid_format' => 'The file format is not supported. Please use CSV or Excel files.',
    ],

    // Error messages
    'errors' => [
        'row_failed' => 'Row :row: :message',
        'column_not_found' => 'Column ":column" not found in file.',
        'invalid_value' => 'Invalid value for ":field".',
        'relationship_not_found' => 'Could not find :model with value ":value".',
        'duplicate_entry' => 'Duplicate entry found for ":field".',
        'database_error' => 'A database error occurred. Please check your data.',
        'unknown_error' => 'An unknown error occurred while processing row :row.',
        'empty_row' => 'Row has no data after applying column mappings.',
        'cannot_resolve_record' => 'Cannot create or find record for this data.',
        'record_not_found_for_update' => 'No existing record found to update.',
        'record_already_exists' => 'A record with this data already exists.',
        'duplicate_value' => 'Duplicate value for field: :field',
        'invalid_relationship' => 'Invalid relationship reference. The related record does not exist.',
        'required_field' => 'The field :field is required.',
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
