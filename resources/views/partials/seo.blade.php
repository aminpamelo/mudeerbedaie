{{--
    Central SEO meta block.

    Included from partials/head.blade.php, so EVERY page in the application gets
    at least a sane default set. Pages opt into richer output by passing a `$seo`
    array from their controller (see BlogController::postSeo()).

    Supported keys:
      title, description, canonical, image, type ('website'|'article'),
      noindex (bool), locale, published_time, modified_time, author, section,
      tags (array), breadcrumbs (array of ['name' => ..., 'url' => ...])
--}}
@php
    $seo = $seo ?? [];

    $settings = app(\App\Services\SettingsService::class);
    $siteName = $settings->get('site_name', config('app.name'));
    $storeName = config('store.name');

    $seoTitle = $seo['title'] ?? ($title ?? $siteName);
    $seoDescription = trim((string) ($seo['description'] ?? config('store.tagline')));
    $seoCanonical = $seo['canonical'] ?? url()->current();
    $seoType = $seo['type'] ?? 'website';
    $seoNoindex = (bool) ($seo['noindex'] ?? false);
    $seoLocale = $seo['locale'] ?? app()->getLocale();

    // Social platforms reject relative image paths — force absolute.
    $seoImage = $seo['image'] ?? $settings->getLogo();
    if ($seoImage && ! \Illuminate\Support\Str::startsWith($seoImage, ['http://', 'https://'])) {
        $seoImage = url($seoImage);
    }

    $ogLocale = $seoLocale === 'ms' ? 'ms_MY' : 'en_US';
@endphp

<meta name="description" content="{{ \Illuminate\Support\Str::limit(strip_tags($seoDescription), 160, '') }}">
<link rel="canonical" href="{{ $seoCanonical }}">

@if($seoNoindex)
    <meta name="robots" content="noindex, nofollow">
@else
    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
@endif

{{-- ---------------------------- Open Graph ---------------------------- --}}
<meta property="og:site_name" content="{{ $storeName }}">
<meta property="og:type" content="{{ $seoType }}">
<meta property="og:title" content="{{ $seoTitle }}">
<meta property="og:description" content="{{ \Illuminate\Support\Str::limit(strip_tags($seoDescription), 200, '') }}">
<meta property="og:url" content="{{ $seoCanonical }}">
<meta property="og:locale" content="{{ $ogLocale }}">
@if($seoImage)
    <meta property="og:image" content="{{ $seoImage }}">
    <meta property="og:image:alt" content="{{ $seoTitle }}">
@endif

@if($seoType === 'article')
    @isset($seo['published_time'])
        <meta property="article:published_time" content="{{ $seo['published_time'] }}">
    @endisset
    @isset($seo['modified_time'])
        <meta property="article:modified_time" content="{{ $seo['modified_time'] }}">
    @endisset
    @isset($seo['author'])
        <meta property="article:author" content="{{ $seo['author'] }}">
    @endisset
    @isset($seo['section'])
        <meta property="article:section" content="{{ $seo['section'] }}">
    @endisset
    @foreach($seo['tags'] ?? [] as $seoTag)
        <meta property="article:tag" content="{{ $seoTag }}">
    @endforeach
@endif

{{-- ------------------------------ Twitter ----------------------------- --}}
<meta name="twitter:card" content="{{ $seoImage ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{{ $seoTitle }}">
<meta name="twitter:description" content="{{ \Illuminate\Support\Str::limit(strip_tags($seoDescription), 200, '') }}">
@if($seoImage)
    <meta name="twitter:image" content="{{ $seoImage }}">
@endif

{{-- ---------------------- Structured data (JSON-LD) -------------------- --}}
@php
    $company = config('app.company');
    $graph = [];

    $graph[] = array_filter([
        '@type' => 'Organization',
        '@id' => url('/') . '#organization',
        'name' => $company['name'] ?? $storeName,
        'url' => url('/'),
        'logo' => $settings->getLogo() ? url($settings->getLogo()) : null,
        'email' => $company['email'] ?? null,
        'telephone' => $company['phone'] ?? null,
    ]);

    $graph[] = [
        '@type' => 'WebSite',
        '@id' => url('/') . '#website',
        'name' => $storeName,
        'url' => url('/'),
        'publisher' => ['@id' => url('/') . '#organization'],
        'inLanguage' => $seoLocale,
        'potentialAction' => [
            '@type' => 'SearchAction',
            'target' => [
                '@type' => 'EntryPoint',
                'urlTemplate' => route('shop') . '?search={search_term_string}',
            ],
            'query-input' => 'required name=search_term_string',
        ],
    ];

    if ($seoType === 'article') {
        $graph[] = array_filter([
            '@type' => 'BlogPosting',
            '@id' => $seoCanonical . '#article',
            'headline' => \Illuminate\Support\Str::limit($seoTitle, 110, ''),
            'description' => \Illuminate\Support\Str::limit(strip_tags($seoDescription), 200, ''),
            'image' => $seoImage,
            'datePublished' => $seo['published_time'] ?? null,
            'dateModified' => $seo['modified_time'] ?? ($seo['published_time'] ?? null),
            'author' => isset($seo['author']) ? ['@type' => 'Person', 'name' => $seo['author']] : null,
            'publisher' => ['@id' => url('/') . '#organization'],
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $seoCanonical],
            'articleSection' => $seo['section'] ?? null,
            'keywords' => ! empty($seo['tags']) ? implode(', ', $seo['tags']) : null,
            'inLanguage' => $seoLocale,
            'wordCount' => $seo['word_count'] ?? null,
        ]);
    }

    if ($seoType === 'product' && ! empty($seo['product'])) {
        $p = $seo['product'];

        $graph[] = array_filter([
            '@type' => 'Product',
            '@id' => $seoCanonical . '#product',
            'name' => $seoTitle,
            'description' => \Illuminate\Support\Str::limit(strip_tags($seoDescription), 300, ''),
            'image' => $seoImage,
            'sku' => $p['sku'] ?? null,
            'brand' => ! empty($p['brand']) ? ['@type' => 'Brand', 'name' => $p['brand']] : null,
            'offers' => [
                '@type' => 'Offer',
                'url' => $seoCanonical,
                'price' => (string) ($p['price'] ?? 0),
                'priceCurrency' => $p['currency'] ?? 'MYR',
                'availability' => $p['availability'] ?? 'https://schema.org/InStock',
                'seller' => ['@id' => url('/') . '#organization'],
            ],
        ]);
    }

    if (! empty($seo['breadcrumbs'])) {
        $graph[] = [
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($seo['breadcrumbs'])->values()->map(fn ($crumb, $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $crumb['name'],
                'item' => $crumb['url'],
            ])->all(),
        ];
    }
@endphp
<script type="application/ld+json">@json(['@context' => 'https://schema.org', '@graph' => $graph], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)</script>

{{-- RSS autodiscovery so feed readers and crawlers find the blog. --}}
<link rel="alternate" type="application/rss+xml" title="{{ $storeName }} — {{ __('blog.nav_blog') }}" href="{{ route('blog.feed') }}">
