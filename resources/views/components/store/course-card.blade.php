@props(['course'])

@php
    $url = route('storefront.course', $course->slug);
    $thumb = $course->thumbnail_url;
    $teacher = $course->teacher?->user?->name;
@endphp

<div class="group flex flex-col overflow-hidden rounded-2xl border border-zinc-100 bg-white transition-all duration-300 hover:-translate-y-1 hover:border-violet-200 hover:shadow-xl hover:shadow-fuchsia-900/10">
    <a href="{{ $url }}" class="relative block aspect-[16/10] overflow-hidden">
        @if($thumb)
            <img src="{{ $thumb }}" alt="{{ $course->name }}" loading="lazy" class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                 onerror="this.onerror=null;this.style.display='none';this.nextElementSibling.style.display='flex';">
            <div class="store-grad hidden h-full w-full items-center justify-center" aria-hidden="true">
                <flux:icon name="academic-cap" class="h-14 w-14 text-white/90" />
            </div>
        @else
            <div class="store-grad flex h-full w-full items-center justify-center">
                <flux:icon name="academic-cap" class="h-14 w-14 text-white/90" />
            </div>
        @endif
        <span class="absolute left-3 top-3 rounded-full bg-white/20 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-white backdrop-blur">{{ __('store.lms_badge') }}</span>
    </a>

    <div class="flex flex-1 flex-col p-4">
        <h3 class="font-display line-clamp-2 text-base font-bold leading-snug text-zinc-900">
            <a href="{{ $url }}" class="transition-colors hover:text-violet-700">{{ $course->name }}</a>
        </h3>
        @if($course->short_description)
            <p class="mt-1.5 line-clamp-2 text-sm text-zinc-500">{{ $course->short_description }}</p>
        @endif

        <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-zinc-500">
            @if($teacher)
                <span class="inline-flex items-center gap-1"><flux:icon name="user" class="h-3.5 w-3.5 text-violet-500" /> {{ $teacher }}</span>
            @endif
            <span class="inline-flex items-center gap-1"><flux:icon name="rectangle-stack" class="h-3.5 w-3.5 text-fuchsia-500" /> {{ trans_choice('store.lms_class_count', $course->classes_count ?? 0, ['count' => $course->classes_count ?? 0]) }}</span>
        </div>

        <div class="mt-auto flex items-end justify-between gap-2 pt-4">
            <span class="store-grad-text font-display text-lg font-extrabold tabular-nums">{{ $course->formatted_fee }}</span>
            <a href="{{ $url }}" class="inline-flex items-center gap-1 rounded-xl bg-violet-50 px-3 py-2 text-sm font-semibold text-violet-700 transition-colors hover:bg-violet-100">
                {{ __('store.lms_view') }} <flux:icon name="arrow-right" class="h-4 w-4" />
            </a>
        </div>
    </div>
</div>
