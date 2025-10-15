<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-900 antialiased">
        <div class="bg-background dark:bg-zinc-900 flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div class="flex w-full max-w-sm flex-col gap-2">
                @php
                    $settingsService = app(\App\Services\SettingsService::class);
                    $siteName = $settingsService->get('site_name', 'Mudeer Bedaie');
                    $siteDescription = $settingsService->get('site_description', 'Educational Management System');
                    $dynamicLogo = $settingsService->getLogo();
                @endphp
                
                <a href="{{ route('home') }}" class="flex flex-col items-center gap-3 font-medium" wire:navigate>
                    @if($dynamicLogo)
                        <div class="flex h-12 w-12 items-center justify-center rounded-md overflow-hidden">
                            <img src="{{ $dynamicLogo }}" alt="{{ $siteName }}" class="h-12 w-12 object-contain" />
                        </div>
                    @else
                        <span class="flex h-12 w-12 items-center justify-center rounded-md">
                            <x-app-logo-icon class="size-12 fill-current text-blue-600" />
                        </span>
                    @endif
                    <span class="text-xl font-bold text-gray-900 dark:text-white">{{ $siteName }}</span>
                    @if($siteDescription)
                        <span class="text-sm text-gray-600 dark:text-gray-400">{{ $siteDescription }}</span>
                    @endif
                </a>
                <div class="flex flex-col gap-6">
                    {{ $slot }}
                </div>
            </div>
        </div>
        @fluxScripts
    </body>
</html>
