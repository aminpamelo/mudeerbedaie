<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Shipping Provider
    |--------------------------------------------------------------------------
    |
    | The default shipping provider to use when creating shipments.
    |
    */

    'default_provider' => 'jnt',

    /*
    |--------------------------------------------------------------------------
    | Default Sender Information
    |--------------------------------------------------------------------------
    |
    | Default sender details used as the origin for shipments.
    | These can be overridden in the admin Shipping settings.
    |
    */

    'sender' => [
        'name' => env('SHIPPING_SENDER_NAME', ''),
        'phone' => env('SHIPPING_SENDER_PHONE', ''),
        'address' => env('SHIPPING_SENDER_ADDRESS', ''),
        'city' => env('SHIPPING_SENDER_CITY', ''),
        'state' => env('SHIPPING_SENDER_STATE', ''),
        'postal_code' => env('SHIPPING_SENDER_POSTAL_CODE', ''),
        'country' => env('SHIPPING_SENDER_COUNTRY', 'MY'),
    ],

];
