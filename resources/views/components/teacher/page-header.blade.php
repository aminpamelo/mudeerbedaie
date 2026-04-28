@props([
    'title',
    'subtitle' => null,
    'back' => null,
])

<div class="mb-6 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
    <div>
        @if ($back)
            <a
                href="{{ $back }}"
                wire:navigate
                class="inline-flex items-center gap-1 text-xs font-semibold text-violet-600 dark:text-violet-400 hover:text-violet-700 dark:hover:text-violet-300 mb-2"
            >
                <flux:icon name="arrow-left" class="w-3.5 h-3.5" />
                Back
            </a>
        @endif

        <h1 class="teacher-display text-3xl sm:text-4xl font-bold text-slate-900 dark:text-white tracking-tight">{{ $title }}</h1>

        @if ($subtitle)
            <p class="mt-1.5 text-sm sm:text-base text-slate-500 dark:text-zinc-400">{{ $subtitle }}</p>
        @endif
    </div>

    @if ($slot->isNotEmpty())
        <div class="shrink-0">{{ $slot }}</div>
    @endif
</div>
