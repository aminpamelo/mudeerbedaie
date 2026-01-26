<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @php
        $settingsService = app(\App\Services\SettingsService::class);
        $dynamicSiteName = $settingsService->get('site_name', config('app.name'));
        $dynamicFavicon = $settingsService->getFavicon();
    @endphp

    <title>{{ $title ?? 'Email Builder' }} - {{ $dynamicSiteName }}</title>

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

    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/css/react-email-builder.css'])
    @stack('styles')

    @livewireStyles
</head>
<body class="h-screen overflow-hidden bg-zinc-100 dark:bg-zinc-900">
    {{ $slot }}

    @livewireScripts
    @vite(['resources/js/react-email-builder.jsx'])
    @stack('scripts')
</body>
</html>
