<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="vapid-public-key" content="{{ config('webpush.vapid.public_key') }}">

    <title>{{ config('app.name', 'Laravel') }} - HR Management</title>

    <link rel="manifest" href="{{ route('hr.manifest') }}">
    <meta name="theme-color" content="#1e40af">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="apple-touch-icon" href="/icons/hr-192.png">

    {{-- Night mode: apply the saved/system theme before paint to avoid a flash of light UI.
         Scoped to the employee portal only — the admin dashboard (HrLayout) stays light. --}}
    @if (auth()->user()?->role !== 'admin')
    <script>
        (function () {
            try {
                var t = localStorage.getItem('hr-theme');
                if (t !== 'light' && t !== 'dark') {
                    t = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                }
                var el = document.documentElement;
                el.classList.toggle('dark', t === 'dark');
                el.style.colorScheme = t;
                var meta = document.querySelector('meta[name="theme-color"]');
                if (meta) { meta.setAttribute('content', t === 'dark' ? '#020617' : '#1e40af'); }
            } catch (e) {}
        })();
    </script>
    @endif

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

    @viteReactRefresh
    @vite(['resources/js/hr/styles/hr.css', 'resources/js/hr/main.jsx'])
</head>
<body class="h-full bg-zinc-50 antialiased">
    <div id="hr-app" class="h-full"></div>

    @php
        $hrUser = auth()->user()?->only(['id', 'name', 'email', 'role']);
    @endphp
    <script>
        window.hrConfig = {
            csrfToken: '{{ csrf_token() }}',
            apiBaseUrl: '{{ url('/api/hr') }}',
            appUrl: '{{ url('/') }}',
            dashboardUrl: '{{ url('/dashboard') }}',
            user: @json($hrUser),
        };
    </script>
</body>
</html>
