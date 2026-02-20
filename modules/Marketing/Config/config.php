<?php

return [
    'name' => 'Marketing',

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Business Cloud API Settings
    |--------------------------------------------------------------------------
    */
    'whatsapp' => [
        'enabled' => env('WHATSAPP_ENABLED', false),
        'api_version' => env('WHATSAPP_API_VERSION', 'v18.0'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'webhook_verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | SMS Provider Settings
    |--------------------------------------------------------------------------
    | Supported providers: twilio, vonage, messagebird, local
    */
    'sms' => [
        'enabled' => env('SMS_ENABLED', false),
        'provider' => env('SMS_PROVIDER', 'twilio'),

        'twilio' => [
            'sid' => env('TWILIO_SID'),
            'token' => env('TWILIO_TOKEN'),
            'from' => env('TWILIO_FROM'),
        ],

        'vonage' => [
            'api_key' => env('VONAGE_API_KEY'),
            'api_secret' => env('VONAGE_API_SECRET'),
            'from' => env('VONAGE_FROM'),
        ],

        // Local Egyptian SMS providers
        'victorylink' => [
            'username' => env('VICTORYLINK_USERNAME'),
            'password' => env('VICTORYLINK_PASSWORD'),
            'sender_id' => env('VICTORYLINK_SENDER_ID'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Email Settings
    |--------------------------------------------------------------------------
    | Uses Laravel's mail configuration, but adds marketing-specific settings
    */
    'email' => [
        'enabled' => env('MARKETING_EMAIL_ENABLED', true),
        'from_name' => env('MARKETING_EMAIL_FROM_NAME'),
        'from_address' => env('MARKETING_EMAIL_FROM_ADDRESS'),
        'reply_to' => env('MARKETING_EMAIL_REPLY_TO'),
        'track_opens' => env('MARKETING_EMAIL_TRACK_OPENS', true),
        'track_clicks' => env('MARKETING_EMAIL_TRACK_CLICKS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Automation Settings
    |--------------------------------------------------------------------------
    */
    'automation' => [
        // Appointment reminders
        'reminder_hours_before' => [24, 2], // Send reminders 24h and 2h before

        // Follow-up after appointment
        'followup_days_after' => 7,

        // Default channel priority (try in order)
        'channel_priority' => ['whatsapp', 'sms', 'email'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    */
    'rate_limits' => [
        'whatsapp_per_minute' => 80,
        'sms_per_minute' => 60,
        'email_per_minute' => 100,
        'campaign_batch_size' => 100, // Recipients per batch
        'campaign_batch_delay' => 60, // Seconds between batches
    ],

    /*
    |--------------------------------------------------------------------------
    | Template Settings
    |--------------------------------------------------------------------------
    */
    'templates' => [
        // WhatsApp templates must be pre-approved by Meta
        'whatsapp_namespace' => env('WHATSAPP_TEMPLATE_NAMESPACE'),

        // Variables available in templates
        'variables' => [
            'patient_name',
            'patient_first_name',
            'patient_phone',
            'clinic_name',
            'branch_name',
            'appointment_date',
            'appointment_time',
            'treatment_name',
            'practitioner_name',
            'invoice_number',
            'invoice_total',
            'payment_amount',
        ],
    ],
];
