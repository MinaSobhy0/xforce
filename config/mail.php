<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    |
    | Set via MAIL_MAILER in .env. Production runs against a local Postfix
    | (localhost:25); dev uses MailHog.
    |
    */

    'default' => env('MAIL_MAILER', 'log'),

    'mailers' => [

        'smtp' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME'),
            'url' => env('MAIL_URL'),
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => env('MAIL_PORT', 25),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'encryption' => env('MAIL_ENCRYPTION'),
            'timeout' => null,
            // HELO/EHLO greeting — must match the sending domain for
            // SPF/DKIM/DMARC to line up. Falls back to APP_URL's host.
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        // Fallback chain — if the primary SMTP transport rejects (rate limit,
        // temp block) the message is queued via log so we still see it.
        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp',
                'log',
            ],
            'retry_after' => 60,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    */

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'noreply@x-linic.com'),
        'name' => env('MAIL_FROM_NAME', 'XLinic'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Reply-To Address
    |--------------------------------------------------------------------------
    |
    | Applied to platform marketing emails so recipient replies land in the
    | support inbox instead of bouncing back from the no-reply mailbox.
    | Transactional Mailables can override per-message.
    |
    */

    'reply_to' => [
        'address' => env('MAIL_REPLY_TO_ADDRESS', 'support@x-linic.com'),
        'name' => env('MAIL_REPLY_TO_NAME', 'XLinic Support'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Markdown Mail Settings
    |--------------------------------------------------------------------------
    */

    'markdown' => [
        'theme' => env('MAIL_MARKDOWN_THEME', 'default'),
        'paths' => [
            resource_path('views/vendor/mail'),
        ],
    ],

];
