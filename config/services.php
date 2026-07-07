<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'whatsapp' => [
        'number' => env('WHATSAPP_NUMBER'),
    ],

    'store' => [
        // Where the owner's copy of every paid-order receipt is sent. Falls back
        // to the mail "from" address when unset.
        'notification_email' => env('STORE_NOTIFICATION_EMAIL'),
    ],

    'mpesa' => [
        'env' => env('MPESA_ENV', 'sandbox'),
        'key' => env('MPESA_CONSUMER_KEY'),
        'secret' => env('MPESA_CONSUMER_SECRET'),
        'shortcode' => env('MPESA_SHORTCODE'),
        'passkey' => env('MPESA_PASSKEY'),
        'callback_url' => env('MPESA_CALLBACK_URL'),

        // High-entropy secret embedded in the callback URL path. Safaricom calls
        // back to /payments/mpesa/callback/{token}; requests with the wrong token
        // are rejected before any order is touched. Treat as a shared secret.
        'callback_token' => env('MPESA_CALLBACK_TOKEN'),

        // Optional production hardening: comma-separated source IPs/CIDRs allowed
        // to hit the callback. Enforced only when MPESA_ENV=production and set.
        'allowed_ips' => array_filter(array_map('trim', explode(',', (string) env('MPESA_ALLOWED_IPS', '')))),

        // How long checkout holds limited-batch stock for an unpaid order before
        // the reservation expires and the stock is returned to the pool.
        'reservation_minutes' => (int) env('MPESA_RESERVATION_MINUTES', 15),

        // Safaricom spike-arrests the STK Query endpoint (~5 req/min, no burst).
        // The reconcile job spaces its per-order queries by this many seconds so
        // later orders in the loop aren't rejected with a SpikeArrestViolation and
        // silently skipped. Set to 0 in tests to keep them fast.
        'query_spacing_seconds' => (int) env('MPESA_QUERY_SPACING_SECONDS', 13),

        // Max STK queries per reconcile run. With ~13s spacing, 4 keeps a run
        // comfortably inside its one-minute scheduling slot; any backlog rolls
        // into the next run instead of stretching this one.
        'reconcile_batch' => (int) env('MPESA_RECONCILE_BATCH', 4),
    ],

];
