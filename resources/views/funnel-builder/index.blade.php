<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }} - Funnel Builder</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Styles -->
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/funnel-builder/styles/funnel-builder.css'])
</head>
<body class="h-full bg-gray-50 antialiased">
    <div id="funnel-builder-app" class="h-full"></div>

    <!-- Scripts -->
    @vite(['resources/js/funnel-builder/index.jsx'])

    <script>
        // Pass server data to the React app
        window.funnelBuilderConfig = {
            csrfToken: '{{ csrf_token() }}',
            apiBaseUrl: '{{ url('/api/v1') }}',
            appUrl: '{{ url('/') }}',
            user: @json(auth()->user()?->only(['id', 'name', 'email'])),
        };
    </script>
</body>
</html>
