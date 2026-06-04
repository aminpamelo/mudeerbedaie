<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="bg-[#EEF2FF]">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- CEO PWA: installable executive dashboard (scope /ceo). --}}
    <link rel="manifest" href="{{ route('ceo.manifest') }}">
    <meta name="theme-color" content="#6366F1">
    <meta name="application-name" content="CEO">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="CEO">
    <link rel="apple-touch-icon" href="/icons/ceo-192.svg">
    <link rel="icon" type="image/svg+xml" href="/icons/ceo-192.svg">

    <title inertia>{{ config('app.name', 'CEO Overview') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@100..900&family=Geist+Mono:wght@100..900&display=swap" rel="stylesheet">

    @routes
    @viteReactRefresh
    @vite(['resources/js/ceo/app.jsx', 'resources/js/ceo/styles/ceo.css'])
    @inertiaHead
</head>
<body class="font-sans antialiased">
    @inertia
</body>
</html>
