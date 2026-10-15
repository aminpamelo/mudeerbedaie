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

    // Articles listed in each tab of the article-page discovery rail
    // (Latest / Most read).
    'rail_limit' => 5,

    // Articles shown in the "From the blog" strip on the storefront homepage.
    'home_limit' => 3,

    // Comments land in moderation before appearing publicly.
    'moderate_comments' => env('BLOG_MODERATE_COMMENTS', true),

    // Newsletter capture blocks rendered inside articles.
    'newsletter_enabled' => env('BLOG_NEWSLETTER_ENABLED', true),

    'locales' => ['ms', 'en'],

    /*
    |--------------------------------------------------------------------------
    | AI article writer (Isu Semasa)
    |--------------------------------------------------------------------------
    |
    | Headlines are captured hourly from these RSS feeds, scored by AI for how
    | well they suit an Islamic commentary, then turned into DRAFT articles
    | (with hadith/ayat + a BeDaie book CTA) for the editor to review.
    |
    */

    'ai' => [
        'model' => env('BLOG_AI_MODEL', 'gpt-4o'),
        'curate_model' => env('BLOG_AI_CURATE_MODEL', 'gpt-4o-mini'),
        'feeds' => [
            'Berita Harian' => 'https://www.bharian.com.my/feed',
            'Harian Metro' => 'https://www.hmetro.com.my/feed',
            'Sinar Harian' => 'https://www.sinarharian.com.my/rssFeed/211',
            'Astro Awani' => 'https://www.astroawani.com/rss/latest/public',
            'Utusan' => 'https://www.utusan.com.my/feed/',
            'Google News' => 'https://news.google.com/rss?hl=ms&gl=MY&ceid=MY:ms',
        ],
        // Keep captured headlines this many days before pruning unused ones.
        'keep_days' => 14,
    ],

];
