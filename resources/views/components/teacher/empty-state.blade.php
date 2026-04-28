@props([
    'icon',
    'title',
    'message' => null,
])

<div class="text-center py-14 rounded-xl bg-gradient-to-br from-violet-50 to-violet-100/60 dark:from-violet-950/30 dark:to-violet-900/20 ring-1 ring-violet-100 dark:ring-violet-900/30">
    <div class="inline-flex w-14 h-14 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-600 to-violet-500 text-white shadow-lg shadow-violet-500/30 mb-4">
        <flux:icon name="{{ $icon }}" class="w-7 h-7" />
    </div>
    <h3 class="teacher-display text-lg font-bold text-slate-900 dark:text-white">{{ $title }}</h3>
    @if($message)
        <p class="text-sm text-slate-500 dark:text-zinc-400 mt-1">{{ $message }}</p>
    @endif
    @if($slot->isNotEmpty())
        <div class="mt-5 flex justify-center">
            {{ $slot }}
        </div>
    @endif
</div>
