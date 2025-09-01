<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>{{ $title ?? config('app.name') }}</title>

@php
    $settingsService = app(\App\Services\SettingsService::class);
    $dynamicFavicon = $settingsService->getFavicon();
@endphp

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
