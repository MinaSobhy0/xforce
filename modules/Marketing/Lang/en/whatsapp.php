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

    'template' => [
        'col_meta_status' => 'Meta status',
        'not_submitted' => 'Not submitted',
        'action_submit' => 'Submit to Meta',
        'action_resync' => 'Re-sync from Meta',
        'submit_modal_desc' => 'Sends this template to Meta for approval. Meta typically reviews within 1–24 hours. The status badge updates automatically once Meta responds.',
        'submit_queued' => 'Submission queued — Meta will respond within minutes to a few hours.',
        'resync_done' => 'Synced from Meta.',
    ],

    'catalog' => [
        'title' => 'WhatsApp Template Catalog',
        'nav_label' => 'Template Catalog',
        'adopt' => 'Adopt',
        'adopt_modal_desc' => 'Adds this template to your clinic\'s templates and submits it to Meta for approval on your WhatsApp Business Account. Meta typically reviews within 1–24 hours.',
        'adopted' => 'Template adopted',
        'adopted_body' => 'Submitted to Meta for approval — check the Message Templates page for status updates.',
        'already_adopted' => 'Already adopted',
        'not_found' => 'Template no longer available',
        'status_adopted' => 'Adopted',
        'empty_heading' => 'Catalog coming soon',
        'empty_description' => 'The platform team hasn\'t published any ready-made templates yet. You can still author your own in Message Templates.',
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
