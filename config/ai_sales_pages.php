<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Sales Page Builder
    |--------------------------------------------------------------------------
    |
    | Configuration for the AI-driven sales page generator. The model is sent
    | to the configured `ai.default` provider (OpenAI by default). Change the
    | model in the .env file via AI_SALES_PAGE_MODEL without touching code.
    |
    */

    'model' => env('AI_SALES_PAGE_MODEL', 'gpt-4o'),

    'timeout' => (int) env('AI_SALES_PAGE_TIMEOUT', 180),

    'queue' => env('AI_SALES_PAGE_QUEUE', 'default'),

    /*
    | Brand defaults injected into every generation so the AI keeps pages
    | on-brand. These mirror the project's design tokens.
    */
    'brand' => [
        'primary' => env('AI_SALES_PAGE_PRIMARY', '#2563EB'),
        'secondary' => env('AI_SALES_PAGE_SECONDARY', '#7C3AED'),
        'accent' => env('AI_SALES_PAGE_ACCENT', '#EC4899'),
        'font' => 'Plus Jakarta Sans',
    ],

    /*
    | Public route prefix for published sales pages, e.g. /p/{slug}.
    */
    'public_prefix' => env('AI_SALES_PAGE_PREFIX', 'p'),
];
