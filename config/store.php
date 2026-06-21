<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Storefront
    |--------------------------------------------------------------------------
    |
    | Public-facing e-commerce storefront settings. The brand name and tagline
    | drive the header/footer; the WhatsApp number powers the "Order on WhatsApp"
    | call-to-action used for packages (which are bundles, not single products).
    |
    */

    'name' => env('STORE_NAME', 'Bedaie'),

    'tagline' => env('STORE_TAGLINE', 'Kedai rasmi produk & pakej Bedaie'),

    // International format, digits only (e.g. 60111058 4015 -> 601110584015).
    'whatsapp' => preg_replace('/\D/', '', (string) env('STORE_WHATSAPP', '601110584015')),

    'currency' => env('STORE_CURRENCY', 'RM'),

    // How many items each storefront section / page shows.
    'featured_limit' => 8,
    'package_limit' => 3,
    'category_limit' => 8,
    'per_page' => 12,
];
