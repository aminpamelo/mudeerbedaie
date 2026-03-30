<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }} - Content Management</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

    @viteReactRefresh
    @vite(['resources/js/cms/styles/cms.css', 'resources/js/cms/main.jsx'])
</head>
<body class="h-full bg-zinc-50 antialiased">
    <div id="cms-app" class="h-full"></div>

    @php
        $cmsUser = auth()->user()?->only(['id', 'name', 'email', 'role']);
    @endphp
    <script>
        window.cmsConfig = {
            csrfToken: '{{ csrf_token() }}',
            apiBaseUrl: '{{ url('/api/cms') }}',
            appUrl: '{{ url('/') }}',
            dashboardUrl: '{{ url('/dashboard') }}',
            user: @json($cmsUser),
        };
    </script>
</body>
</html>
