<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

@php
    $settingsService = app(\App\Services\SettingsService::class);
    $dynamicSiteName = $settingsService->get('site_name', config('app.name'));
    $dynamicFavicon = $settingsService->getFavicon();
@endphp

<title>{{ $title ?? $dynamicSiteName }}</title>

@if($dynamicFavicon)
    <link rel="icon" href="{{ $dynamicFavicon }}" sizes="any">
    <link rel="apple-touch-icon" href="{{ $dynamicFavicon }}">
@else
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
@endif

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance

{{-- Force light mode permanently --}}
<script>
    // Override Flux appearance to always use light mode
    document.addEventListener('DOMContentLoaded', function() {
        if (window.$flux && window.$flux.appearance) {
            window.$flux.appearance = 'light';
        }
        // Remove dark class if it exists
        document.documentElement.classList.remove('dark');
        // Force light color scheme
        document.documentElement.style.colorScheme = 'light';
    });
</script>
