@props([
    'expandable' => false,
    'expanded' => true,
    'heading' => null,
    'icon' => null,
    'color' => null,
])

@php
    $iconClasses = match ($color) {
        'indigo' => 'bg-indigo-100 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-400',
        'blue' => 'bg-blue-100 text-blue-600 dark:bg-blue-500/15 dark:text-blue-400',
        'cyan' => 'bg-cyan-100 text-cyan-600 dark:bg-cyan-500/15 dark:text-cyan-400',
        'sky' => 'bg-sky-100 text-sky-600 dark:bg-sky-500/15 dark:text-sky-400',
        'teal' => 'bg-teal-100 text-teal-600 dark:bg-teal-500/15 dark:text-teal-400',
        'emerald' => 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400',
        'green' => 'bg-green-100 text-green-600 dark:bg-green-500/15 dark:text-green-400',
        'amber' => 'bg-amber-100 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400',
        'orange' => 'bg-orange-100 text-orange-600 dark:bg-orange-500/15 dark:text-orange-400',
        'yellow' => 'bg-yellow-100 text-yellow-600 dark:bg-yellow-500/15 dark:text-yellow-400',
        'rose' => 'bg-rose-100 text-rose-600 dark:bg-rose-500/15 dark:text-rose-400',
        'pink' => 'bg-pink-100 text-pink-600 dark:bg-pink-500/15 dark:text-pink-400',
        'fuchsia' => 'bg-fuchsia-100 text-fuchsia-600 dark:bg-fuchsia-500/15 dark:text-fuchsia-400',
        'purple' => 'bg-purple-100 text-purple-600 dark:bg-purple-500/15 dark:text-purple-400',
        'violet' => 'bg-violet-100 text-violet-600 dark:bg-violet-500/15 dark:text-violet-400',
        'slate' => 'bg-slate-100 text-slate-600 dark:bg-slate-500/15 dark:text-slate-400',
        default => 'bg-zinc-100 text-zinc-500 dark:bg-zinc-700/40 dark:text-zinc-400',
    };
@endphp

<?php if ($expandable && $heading): ?>
    <ui-disclosure {{ $attributes->class('group/disclosure') }} @if ($expanded === true) open @endif data-flux-navlist-group>
        <button type="button" class="w-full h-10 lg:h-9 flex items-center gap-2 group/disclosure-button mb-[2px] rounded-lg px-2 hover:bg-zinc-800/5 dark:hover:bg-white/[7%] text-zinc-600 hover:text-zinc-900 dark:text-white/80 dark:hover:text-white transition-colors">
            <?php if ($icon): ?>
                <span class="flex size-6 shrink-0 items-center justify-center rounded-md {{ $iconClasses }}">
                    <flux:icon :name="$icon" class="size-3.5!" />
                </span>
            <?php endif; ?>

            <span class="flex-1 text-start text-sm font-semibold leading-none truncate">{{ $heading }}</span>

            <flux:icon.chevron-down class="size-3.5! shrink-0 text-zinc-400 hidden group-data-open/disclosure-button:block" />
            <flux:icon.chevron-right class="size-3.5! shrink-0 text-zinc-400 block group-data-open/disclosure-button:hidden rtl:rotate-180" />
        </button>

        <div class="relative hidden data-open:block space-y-[2px] ps-7" @if ($expanded === true) data-open @endif>
            <div class="absolute inset-y-[3px] w-px bg-gradient-to-b from-indigo-200 via-violet-200 to-transparent dark:from-indigo-500/30 dark:via-violet-500/20 start-0 ms-4"></div>

            {{ $slot }}
        </div>
    </ui-disclosure>
<?php elseif ($heading): ?>
    <div {{ $attributes->class('block space-y-[2px]') }}>
        <div class="px-3 py-2">
            <div class="text-sm text-zinc-400 font-medium leading-none">{{ $heading }}</div>
        </div>

        <div>
            {{ $slot }}
        </div>
    </div>
<?php else: ?>
    <div {{ $attributes->class('block space-y-[2px]') }}>
        {{ $slot }}
    </div>
<?php endif; ?>
