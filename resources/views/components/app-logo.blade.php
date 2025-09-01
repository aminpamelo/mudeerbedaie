@php
    $settingsService = app(\App\Services\SettingsService::class);
    $dynamicLogo = $settingsService->getLogo();
    $siteName = $settingsService->get('site_name', 'Laravel Starter Kit');
@endphp

@if($dynamicLogo)
    <div class="flex aspect-square size-8 items-center justify-center rounded-md overflow-hidden">
        <img src="{{ $dynamicLogo }}" alt="{{ $siteName }}" class="size-8 object-contain" />
    </div>
@else
    <div class="flex aspect-square size-8 items-center justify-center rounded-md bg-accent-content text-accent-foreground">
        <x-app-logo-icon class="size-5 fill-current text-white dark:text-black" />
    </div>
@endif
<div class="ms-1 grid flex-1 text-start text-sm">
    <span class="mb-0.5 truncate leading-tight font-semibold">{{ $siteName }}</span>
</div>
