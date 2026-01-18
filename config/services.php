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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | OnSend.io WhatsApp API (Unofficial)
    |--------------------------------------------------------------------------
    |
    | Configuration for the OnSend.io WhatsApp messaging service.
    | WARNING: This is an unofficial API and may violate WhatsApp's ToS.
    | Use at your own risk. Anti-ban measures are implemented to reduce
    | the risk of account suspension.
    |
    */

    'onsend' => [
        'api_url' => env('ONSEND_API_URL', 'https://onsend.io/api/v1'),
        'api_token' => env('ONSEND_API_TOKEN'),
        'enabled' => env('ONSEND_ENABLED', false),

        // Anti-ban settings
        'min_delay_seconds' => env('ONSEND_MIN_DELAY', 10),
        'max_delay_seconds' => env('ONSEND_MAX_DELAY', 30),
        'batch_size' => env('ONSEND_BATCH_SIZE', 15),
        'batch_pause_minutes' => env('ONSEND_BATCH_PAUSE', 1), // 1 minute pause between batches
        'daily_limit' => env('ONSEND_DAILY_LIMIT', 0), // 0 = unlimited
        'time_restriction_enabled' => env('ONSEND_TIME_RESTRICTION', false), // false = no time restriction
        'send_hours_start' => env('ONSEND_HOURS_START', 8),
        'send_hours_end' => env('ONSEND_HOURS_END', 22),
        'message_variation_enabled' => env('ONSEND_MESSAGE_VARIATION', false), // false = disabled for safety
    ],

];
