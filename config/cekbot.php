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

];
