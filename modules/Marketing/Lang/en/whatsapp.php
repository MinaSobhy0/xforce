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

    'inbox' => [
        'nav_label' => 'WhatsApp Inbox',
        'model_label' => 'Conversation',
        'model_label_plural' => 'Conversations',

        'col_contact' => 'Contact',
        'col_last_message' => 'Last message',
        'col_window' => 'Window',
        'col_unread' => 'Unread',
        'col_last_at' => 'Last activity',

        'filter_unread' => 'Unread only',
        'filter_window' => 'Within 24-hour window',

        'window_open' => '24h window open',
        'window_closed' => '24h window expired',
        'window_open_tip' => 'You can send free-form text replies for the next 24 hours after the last inbound message.',
        'window_closed_tip' => 'Free-form replies are blocked. Send an approved template to re-open the conversation.',

        'window_expired' => 'The 24-hour customer-service window has expired.',
        'must_use_template' => 'To restart the conversation, send an approved template message — Meta only allows free-form text after the recipient replies within 24 hours.',

        'reply_freeform' => 'Reply',
        'reply_template' => 'Send template',
        'reply_placeholder' => 'Type your message…',
        'choose_template' => 'Template',
        'window_expired_help' => 'You are outside the 24-hour reply window. Pick an approved template to send.',

        'no_messages' => 'No messages in this conversation yet.',
        'media_pending' => 'Media downloading…',
        'button_reply' => 'Button reply',
        'location_shared' => 'Location shared',

        'send_success' => 'Message sent',
        'send_failed' => 'Send failed',
    ],
];
