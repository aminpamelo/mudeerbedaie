<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Inbound webhook
    |--------------------------------------------------------------------------
    |
    | Public URL that WAHA calls back with inbound events. It must be reachable
    | from the WAHA server/container (a tunnel or public domain). When set, every
    | session Cekbot creates — and `php artisan cekbot:sync-webhooks` — registers
    | this webhook automatically, so new numbers work without manual setup.
    |
    */

    'webhook_url' => env('CEKBOT_WEBHOOK_URL'),

    'webhook_events' => ['message', 'session.status', 'message.ack'],

    /*
    |--------------------------------------------------------------------------
    | Broadcast throttle
    |--------------------------------------------------------------------------
    |
    | Seconds to wait between messages when sending a broadcast (ban-risk guard).
    |
    */

    'broadcast_throttle_seconds' => env('CEKBOT_BROADCAST_THROTTLE', 2),

    /*
    |--------------------------------------------------------------------------
    | Human-like pacing for bot replies
    |--------------------------------------------------------------------------
    |
    | Before each bot bubble the customer sees "typing…" for a moment that grows
    | with the message length. A longer pause follows any image/video: WhatsApp
    | (especially the Cloud API) delivers media slower than text, so without it
    | a picture sent mid-script lands after the bubbles that follow it.
    |
    */

    'typing' => [
        'enabled' => env('CEKBOT_TYPING_ENABLED', true),
        'base_ms' => (int) env('CEKBOT_TYPING_BASE_MS', 700),
        'per_char_ms' => (int) env('CEKBOT_TYPING_PER_CHAR_MS', 25),
        'max_ms' => (int) env('CEKBOT_TYPING_MAX_MS', 3500),
        'media_ms' => (int) env('CEKBOT_TYPING_MEDIA_MS', 1500),
        'after_media_ms' => (int) env('CEKBOT_TYPING_AFTER_MEDIA_MS', 3000),
    ],

];
