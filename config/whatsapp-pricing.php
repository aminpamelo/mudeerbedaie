<?php

return [

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Message Pricing Rates (per message, in USD)
    |--------------------------------------------------------------------------
    |
    | Rates by country code and message category.
    | Source: https://business.whatsapp.com/products/platform-pricing
    |
    */

    'rates' => [
        'MY' => [
            'marketing' => 0.0860,
            'utility' => 0.0140,
            'authentication' => 0.0140,
            'authentication_international' => 0.0418,
            'service' => 0.0000,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Currency Conversion
    |--------------------------------------------------------------------------
    */

    'usd_to_myr' => env('WHATSAPP_USD_TO_MYR', 4.50),

    /*
    |--------------------------------------------------------------------------
    | Default Country
    |--------------------------------------------------------------------------
    */

    'default_country' => env('WHATSAPP_DEFAULT_COUNTRY', 'MY'),

];
