<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Blog
    |--------------------------------------------------------------------------
    |
    | Public blog module settings. Posts are authored in Markdown, tagged with a
    | language, and rendered on the storefront layout so the blog reads as part
    | of the shop rather than a bolted-on subsite.
    |
    */

    'per_page' => 9,

    'popular_limit' => 5,

    'related_limit' => 3,

    // Articles shown in the "From the blog" strip on the storefront homepage.
    'home_limit' => 3,

    // Comments land in moderation before appearing publicly.
    'moderate_comments' => env('BLOG_MODERATE_COMMENTS', true),

    // Newsletter capture blocks rendered inside articles.
    'newsletter_enabled' => env('BLOG_NEWSLETTER_ENABLED', true),

    'locales' => ['ms', 'en'],
];
