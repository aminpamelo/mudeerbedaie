{{-- Reusable Skeleton Components for Loading States --}}
@props([
    'type' => 'text', // text, card, avatar, session-card, class-card, stat-card, menu-item
    'lines' => 1,
    'width' => 'full',
])

@php
    $widthClasses = [
        'full' => 'w-full',
        '3/4' => 'w-3/4',
        '2/3' => 'w-2/3',
        '1/2' => 'w-1/2',
        '1/3' => 'w-1/3',
        '1/4' => 'w-1/4',
    ];
    $widthClass = $widthClasses[$width] ?? 'w-full';
@endphp

@switch($type)
    @case('text')
        <div class="animate-pulse space-y-2">
            @for($i = 0; $i < $lines; $i++)
                <div class="h-4 bg-gray-200 rounded {{ $i === $lines - 1 && $lines > 1 ? 'w-2/3' : $widthClass }}"></div>
            @endfor
        </div>
        @break

    @case('avatar')
        <div class="animate-pulse">
            <div class="w-16 h-16 bg-gray-200 rounded-full"></div>
        </div>
        @break

    @case('card')
        <div class="animate-pulse">
            <flux:card>
                <div class="space-y-3">
                    <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                    <div class="h-3 bg-gray-200 rounded w-1/2"></div>
                </div>
            </flux:card>
        </div>
        @break

    @case('session-card')
        <div class="animate-pulse px-4 py-3">
            <div class="flex items-center gap-4">
                {{-- Time skeleton --}}
                <div class="flex-shrink-0 text-center min-w-[60px]">
                    <div class="h-6 bg-gray-200 rounded w-12 mx-auto mb-1"></div>
                    <div class="h-3 bg-gray-200 rounded w-8 mx-auto"></div>
                </div>
                {{-- Info skeleton --}}
                <div class="flex-1 min-w-0 space-y-2">
                    <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                    <div class="h-3 bg-gray-200 rounded w-1/2"></div>
                </div>
                {{-- Badge skeleton --}}
                <div class="h-5 w-16 bg-gray-200 rounded-full"></div>
            </div>
        </div>
        @break

    @case('class-card')
        <div class="animate-pulse">
            <flux:card class="hover:bg-gray-50 transition-colors">
                <div class="flex items-center gap-4">
                    {{-- Icon skeleton --}}
                    <div class="flex-shrink-0 w-12 h-12 bg-gray-200 rounded-lg"></div>
                    {{-- Info skeleton --}}
                    <div class="flex-1 min-w-0 space-y-2">
                        <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                        <div class="h-3 bg-gray-200 rounded w-1/2"></div>
                        <div class="h-3 bg-gray-200 rounded w-2/3"></div>
                    </div>
                    {{-- Badge and chevron --}}
                    <div class="flex items-center gap-2">
                        <div class="h-5 w-14 bg-gray-200 rounded-full"></div>
                        <div class="h-5 w-5 bg-gray-200 rounded"></div>
                    </div>
                </div>
            </flux:card>
        </div>
        @break

    @case('stat-card')
        <div class="animate-pulse">
            <flux:card class="text-center">
                <div class="h-7 bg-gray-200 rounded w-8 mx-auto mb-2"></div>
                <div class="h-3 bg-gray-200 rounded w-16 mx-auto"></div>
            </flux:card>
        </div>
        @break

    @case('menu-item')
        <div class="animate-pulse">
            <flux:card class="hover:bg-gray-50">
                <div class="flex items-center gap-4">
                    <div class="flex-shrink-0 w-10 h-10 bg-gray-200 rounded-lg"></div>
                    <div class="flex-1 min-w-0 space-y-2">
                        <div class="h-4 bg-gray-200 rounded w-1/2"></div>
                        <div class="h-3 bg-gray-200 rounded w-2/3"></div>
                    </div>
                    <div class="h-5 w-5 bg-gray-200 rounded"></div>
                </div>
            </flux:card>
        </div>
        @break

    @case('upcoming-session')
        <div class="animate-pulse px-4 py-3">
            <div class="flex items-center gap-4">
                {{-- Date badge skeleton --}}
                <div class="flex-shrink-0 w-12 h-12 bg-gray-200 rounded-lg"></div>
                {{-- Info skeleton --}}
                <div class="flex-1 min-w-0 space-y-2">
                    <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                    <div class="h-3 bg-gray-200 rounded w-1/2"></div>
                </div>
                <div class="h-5 w-5 bg-gray-200 rounded"></div>
            </div>
        </div>
        @break

    @case('profile-header')
        <div class="animate-pulse">
            <flux:card>
                <div class="flex items-center gap-4">
                    <div class="flex-shrink-0 w-16 h-16 bg-gray-200 rounded-full"></div>
                    <div class="flex-1 min-w-0 space-y-2">
                        <div class="h-5 bg-gray-200 rounded w-1/2"></div>
                        <div class="h-3 bg-gray-200 rounded w-2/3"></div>
                        <div class="h-3 bg-gray-200 rounded w-1/3"></div>
                    </div>
                </div>
            </flux:card>
        </div>
        @break

    @default
        <div class="animate-pulse">
            <div class="h-4 bg-gray-200 rounded {{ $widthClass }}"></div>
        </div>
@endswitch
