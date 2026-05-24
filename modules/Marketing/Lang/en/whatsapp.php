<?php

return [
    'page' => [
        'title' => 'WhatsApp Integration',
        'nav_label' => 'WhatsApp',
    ],

    'placeholder' => [
        'heading' => 'Coming soon',
        'description' => "Meta WhatsApp integration is being provisioned by the platform. Once it's ready you'll be able to connect your own WhatsApp Business number here in a single click.",
    ],

    'disconnected' => [
        'heading' => 'Connect your WhatsApp Business number',
        'description' => "Your clinic's WhatsApp number will be used for appointment reminders, OTPs, campaigns and approver notifications. You'll be guided through Meta's signup in a single popup — takes about 5 minutes.",
        'connect_button' => 'Connect WhatsApp',
        'help_text' => 'You can use an existing WhatsApp Business number or buy a new one during signup. If you already use the number for personal WhatsApp, that personal account will stop working once you connect it here.',
        'last_error' => 'Last signup attempt failed:',
    ],

    'connected' => [
        'heading' => 'WhatsApp connected',
        'description' => 'Messages from this clinic now go through your own WhatsApp Business number. Billing is handled directly between you and Meta.',
        'phone' => 'Phone number',
        'waba_id' => 'WhatsApp Business Account ID',
        'connected_at' => 'Connected',
    ],

    'actions' => [
        'send_test' => 'Send test message',
        'disconnect' => 'Disconnect',
    ],

    'disconnect' => [
        'heading' => 'Disconnect WhatsApp',
        'description' => "Future messages will fall back to the platform's default WhatsApp number until you reconnect. To fully remove XForce as a Meta Tech Provider, revoke us in Meta Business Manager too.",
    ],

    'test' => [
        'to' => 'Send to (international format)',
        'to_help' => 'E.164 format, e.g. +201281717343. For sandbox/test numbers, the recipient must be in your Meta App Dashboard allow list.',
        'mode' => 'Send as',
        'mode_template' => 'Approved template (works any time)',
        'mode_freeform' => 'Freeform text (only within a 24-hour customer-service window)',
        'template_name' => 'Template name',
        'template_language' => 'Template language',
        'message' => 'Message',
        'default_body' => 'This is a test message from your XForce WhatsApp integration.',
    ],

    'notify' => [
        'connected' => 'WhatsApp connected successfully',
        'connect_failed' => 'WhatsApp connection failed',
        'disconnected' => 'WhatsApp disconnected',
        'test_sent' => 'Test message sent',
        'test_failed' => 'Test message failed',
        'no_tenant' => 'No tenant context — please re-open this page from inside a clinic admin panel.',
    ],
];
