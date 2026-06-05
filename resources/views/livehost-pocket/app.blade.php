<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="bg-[#F0F0F5]">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Live Host Pocket PWA: installable, push-enabled host app (scope /live-host). --}}
    <link rel="manifest" href="{{ route('live-host.manifest') }}">
    <meta name="theme-color" content="#F0F0F5">
    <meta name="application-name" content="Hos">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Hos">
    <link rel="apple-touch-icon" href="/icons/pocket-192.svg">
    <link rel="icon" type="image/svg+xml" href="/icons/pocket-192.svg">

    {{-- Public VAPID key for Web Push subscription (read by the React opt-in flow). --}}
    <meta name="vapid-public-key" content="{{ config('webpush.vapid.public_key') }}">

    <title inertia>{{ config('app.name', 'Sistem Livehost Bedaie') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@100..900&family=Geist+Mono:wght@100..900&display=swap" rel="stylesheet">

    @routes
    @viteReactRefresh
    @vite(['resources/js/livehost-pocket/app.jsx', 'resources/js/livehost-pocket/styles/pocket.css'])
    @inertiaHead
</head>
<body class="font-sans antialiased">
    @inertia
</body>
</html>
