<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }} - Point of Sale</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Styles -->
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/pos/styles/pos.css'])
</head>
<body class="h-full bg-gray-50 antialiased">
    <div id="pos-app" class="h-full"></div>

    <!-- Scripts -->
    @vite(['resources/js/pos/index.jsx'])

    @php
        $posUser = auth()->user()?->only(['id', 'name', 'email', 'role']);
    @endphp
    <script>
        window.posConfig = {
            csrfToken: '{{ csrf_token() }}',
            apiBaseUrl: '{{ url('/api/pos') }}',
            appUrl: '{{ url('/') }}',
            dashboardUrl: '{{ url('/dashboard') }}',
            user: @json($posUser),
        };
    </script>
</body>
</html>
