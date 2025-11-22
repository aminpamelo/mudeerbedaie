@php
    $currentRoute = request()->route()->getName();

    $navItems = [
        [
            'route' => 'live-host.dashboard',
            'icon' => 'home',
            'label' => 'Dashboard',
            'active' => $currentRoute === 'live-host.dashboard'
        ],
        [
            'route' => 'live-host.schedule',
            'icon' => 'calendar',
            'label' => 'Schedule',
            'active' => $currentRoute === 'live-host.schedule'
        ],
        [
            'route' => 'live-host.sessions.index',
            'icon' => 'video-camera',
            'label' => 'Sessions',
            'active' => str_starts_with($currentRoute, 'live-host.sessions')
        ],
        [
            'route' => 'settings.profile',
            'icon' => 'user',
            'label' => 'Profile',
            'active' => str_starts_with($currentRoute, 'settings')
        ]
    ];
@endphp

<nav class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 z-50 lg:hidden">
    <div class="grid grid-cols-4 h-16">
        @foreach ($navItems as $item)
            <a href="{{ route($item['route']) }}"
               wire:navigate
               class="flex flex-col items-center justify-center gap-1 transition-colors {{ $item['active'] ? 'text-blue-600' : 'text-gray-600 hover:text-gray-900' }}">
                <div class="relative">
                    @if ($item['icon'] === 'home')
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                        </svg>
                    @elseif ($item['icon'] === 'calendar')
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    @elseif ($item['icon'] === 'video-camera')
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                        </svg>
                    @elseif ($item['icon'] === 'user')
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                    @endif

                    @if ($item['active'])
                        <div class="absolute -top-1 left-1/2 transform -translate-x-1/2 w-1 h-1 bg-blue-600 rounded-full"></div>
                    @endif
                </div>
                <span class="text-xs font-medium">{{ $item['label'] }}</span>
            </a>
        @endforeach
    </div>
</nav>

<!-- Spacer for fixed bottom nav on mobile -->
<div class="h-16 lg:hidden"></div>
