<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-gray-50 dark:bg-zinc-900 antialiased">
        {{ $slot }}
        @fluxScripts
        @stack('scripts')
    </body>
</html>
