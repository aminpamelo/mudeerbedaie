<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">

    <title>BeDaie - Affiliate Portal</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Styles -->
    @viteReactRefresh
    @vite(['resources/css/app.css'])
</head>
<body class="h-full bg-gray-50 antialiased">
    <div id="affiliate-app" class="h-full"></div>

    <!-- Scripts -->
    @vite(['resources/js/affiliate-dashboard/index.jsx'])

    <script>
        window.affiliateConfig = {
            csrfToken: '{{ csrf_token() }}',
            apiBaseUrl: '{{ url('/api/v1/affiliate') }}',
            appUrl: '{{ url('/') }}',
            appName: 'BeDaie',
        };
    </script>
</body>
</html>
