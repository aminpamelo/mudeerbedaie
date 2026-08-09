<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">

    <title inertia>{{ config('app.name', 'Password Vault') }}</title>

    @routes
    @viteReactRefresh
    @inertiaHead
</head>
<body class="font-sans antialiased">
    @inertia
</body>
</html>
