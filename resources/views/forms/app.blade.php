<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <meta name="theme-color" content="#4F46E5">
    <meta name="application-name" content="Borang">
    <meta name="mobile-web-app-capable" content="yes">

    <title inertia>{{ config('app.name', 'Borang') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap" rel="stylesheet">

    @routes
    @viteReactRefresh
    @vite(['resources/js/forms/app.jsx', 'resources/js/forms/styles/forms.css'])
    @inertiaHead
</head>
<body class="font-sans antialiased">
    @inertia
</body>
</html>
