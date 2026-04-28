@props([
    'status',
    'label' => null,
    'size' => 'md',
])

@php
    $map = [
        'scheduled' => ['classes' => 'bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300', 'icon' => 'calendar', 'label' => 'Scheduled'],
        'ongoing'   => ['classes' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300', 'icon' => null, 'label' => 'Live now', 'liveDot' => true],
        'completed' => ['classes' => 'bg-slate-100 text-slate-600 dark:bg-zinc-700/40 dark:text-zinc-300', 'icon' => 'check', 'label' => 'Completed'],
        'cancelled' => ['classes' => 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300', 'icon' => 'x-mark', 'label' => 'Cancelled'],
        'no_show'   => ['classes' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300', 'icon' => 'exclamation-triangle', 'label' => 'No-show'],
        'paid'      => ['classes' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300', 'icon' => 'check', 'label' => 'Paid'],
        'pending'   => ['classes' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300', 'icon' => 'clock', 'label' => 'Pending'],
        'failed'    => ['classes' => 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300', 'icon' => 'x-mark', 'label' => 'Failed'],
        'active'    => ['classes' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300', 'icon' => 'check', 'label' => 'Active'],
        'inactive'  => ['classes' => 'bg-slate-100 text-slate-600 dark:bg-zinc-700/40 dark:text-zinc-300', 'icon' => 'minus', 'label' => 'Inactive'],
    ];
    $key = strtolower($status);
    $entry = $map[$key] ?? ['classes' => 'bg-slate-100 text-slate-600 dark:bg-zinc-700/40 dark:text-zinc-300', 'icon' => null, 'label' => ucfirst(str_replace('_', ' ', $key))];
    $resolvedSize = in_array($size, ['sm', 'md']) ? $size : 'md';
    $sizeClasses = $resolvedSize === 'sm' ? 'px-2 py-0.5 text-[10px]' : 'px-2.5 py-0.5 text-[11px]';
    $iconSize = $resolvedSize === 'sm' ? 'w-3 h-3' : 'w-3 h-3';
    $displayLabel = $label ?? $entry['label'];
@endphp

<span class="inline-flex items-center gap-1 rounded-full font-semibold {{ $sizeClasses }} {{ $entry['classes'] }}">
    @if(!empty($entry['liveDot']))
        <span class="teacher-live-dot"></span>
    @elseif($entry['icon'])
        <flux:icon name="{{ $entry['icon'] }}" class="{{ $iconSize }}" />
    @endif
    {{ $displayLabel }}
</span>
