<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Bayarcash API Token
    |--------------------------------------------------------------------------
    |
    | Your Bayarcash API token for authentication. This can be obtained from
    | the Bayarcash console at console.bayar.cash (production) or
    | console.bayarcash-sandbox.com (sandbox).
    |
    */
    'api_token' => env('BAYARCASH_API_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Bayarcash API Secret Key
    |--------------------------------------------------------------------------
    |
    | Your Bayarcash API secret key used for checksum generation and
    | callback verification. Keep this secure and never expose it publicly.
    |
    */
    'api_secret_key' => env('BAYARCASH_API_SECRET_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Bayarcash Portal Key
    |--------------------------------------------------------------------------
    |
    | The portal key for your Bayarcash payment portal. Each portal can have
    | different payment channels enabled.
    |
    */
    'portal_key' => env('BAYARCASH_PORTAL_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Sandbox Mode
    |--------------------------------------------------------------------------
    |
    | Set to true to use the Bayarcash sandbox environment for testing.
    | Set to false for production payments.
    |
    */
    'sandbox' => env('BAYARCASH_SANDBOX', true),

    /*
    |--------------------------------------------------------------------------
    | API Version
    |--------------------------------------------------------------------------
    |
    | The Bayarcash API version to use. Version 3 is recommended as it
    | provides additional features for transaction management.
    |
    */
    'api_version' => env('BAYARCASH_API_VERSION', 'v3'),

    /*
    |--------------------------------------------------------------------------
    | Callback URL
    |--------------------------------------------------------------------------
    |
    | The URL that Bayarcash will send payment notifications to.
    | This should be a publicly accessible URL.
    |
    */
    'callback_url' => env('BAYARCASH_CALLBACK_URL', env('APP_URL').'/bayarcash/callback'),

    /*
    |--------------------------------------------------------------------------
    | Return URL
    |--------------------------------------------------------------------------
    |
    | The URL that users will be redirected to after completing or
    | cancelling a payment on the Bayarcash payment page.
    |
    */
    'return_url' => env('BAYARCASH_RETURN_URL', env('APP_URL').'/bayarcash/return'),
];
