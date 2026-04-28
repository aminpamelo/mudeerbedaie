@props([
    'eyebrow',
    'value',
    'tone' => 'indigo',
    'icon' => null,
])

@php
    $allowedTones = ['indigo', 'emerald', 'violet', 'amber'];
    $resolvedTone = in_array($tone, $allowedTones) ? $tone : 'indigo';

    $toneStyles = [
        'indigo' => [
            'text' => 'text-violet-700/80 dark:text-violet-300/90',
            'icon_bg' => 'bg-violet-500/10 dark:bg-violet-400/15',
            'icon_color' => 'text-violet-600 dark:text-violet-300',
        ],
        'emerald' => [
            'text' => 'text-emerald-700/80 dark:text-emerald-300/90',
            'icon_bg' => 'bg-emerald-500/10 dark:bg-emerald-400/15',
            'icon_color' => 'text-emerald-600 dark:text-emerald-300',
        ],
        'violet' => [
            'text' => 'text-violet-700/80 dark:text-violet-300/90',
            'icon_bg' => 'bg-violet-500/10 dark:bg-violet-400/15',
            'icon_color' => 'text-violet-600 dark:text-violet-300',
        ],
        'amber' => [
            'text' => 'text-amber-700/80 dark:text-amber-300/90',
            'icon_bg' => 'bg-amber-500/10 dark:bg-amber-400/15',
            'icon_color' => 'text-amber-600 dark:text-amber-300',
        ],
    ];

    $styles = $toneStyles[$resolvedTone];
@endphp

<div class="teacher-card teacher-card-hover teacher-stat teacher-stat-{{ $resolvedTone }} teacher-stat-hover p-5">
    <div class="flex items-center justify-between mb-3">
        <span class="text-xs font-semibold uppercase tracking-wider {{ $styles['text'] }}">{{ $eyebrow }}</span>
        @if($icon)
            <div class="rounded-lg {{ $styles['icon_bg'] }} p-1.5">
                <flux:icon name="{{ $icon }}" class="w-4 h-4 {{ $styles['icon_color'] }}" />
            </div>
        @endif
    </div>
    <div class="teacher-display teacher-num text-3xl sm:text-4xl font-bold text-slate-900 dark:text-white">{{ $value }}</div>
    @if($slot->isNotEmpty())
        <div class="mt-1.5 text-xs">
            {{ $slot }}
        </div>
    @endif
</div>
