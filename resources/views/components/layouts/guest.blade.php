<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-gray-50 antialiased">
        {{ $slot }}
        @fluxScripts
        @stack('scripts')
    </body>
</html>
