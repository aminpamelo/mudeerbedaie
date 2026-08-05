@props(['post', 'compact' => false])

@php
    $img = $post->featuredImage?->url;
    $url = route('blog.show', $post->slug);
    $accent = $post->category?->color ?: '#7c3aed';
@endphp

<article class="group flex flex-col overflow-hidden rounded-2xl border border-zinc-100 bg-white transition-all duration-300 hover:-translate-y-1 hover:border-violet-200 hover:shadow-xl hover:shadow-fuchsia-900/10">
    <a href="{{ $url }}" class="relative block {{ $compact ? 'aspect-[16/9]' : 'aspect-[16/10]' }} overflow-hidden bg-zinc-50">
        @if($img)
            <img src="{{ $img }}" alt="{{ $post->featuredImage?->alt_text ?: $post->title }}" loading="lazy" decoding="async"
                 class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                 onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
            {{-- Shown only if the file is missing, so a broken image never renders. --}}
            <div class="hidden h-full w-full items-center justify-center bg-gradient-to-br from-violet-50 via-fuchsia-50 to-rose-50" aria-hidden="true">
                <flux:icon name="newspaper" class="h-12 w-12 text-violet-200" />
            </div>
        @else
            <div class="flex h-full w-full items-center justify-center bg-gradient-to-br from-violet-50 via-fuchsia-50 to-rose-50">
                <flux:icon name="newspaper" class="h-12 w-12 text-violet-200" />
            </div>
        @endif

        @if($post->category)
            <span class="absolute left-3 top-3 rounded-full px-2.5 py-1 text-[11px] font-bold text-white shadow-sm backdrop-blur-sm"
                  style="background-color: {{ $accent }}dd">
                {{ $post->category->name }}
            </span>
        @endif
    </a>

    <div class="flex flex-1 flex-col p-5">
        <h3 class="font-display line-clamp-2 text-base font-bold leading-snug text-zinc-900">
            <a href="{{ $url }}" class="transition-colors hover:text-violet-700">{{ $post->title }}</a>
        </h3>

        @unless($compact)
            @if($post->excerpt)
                <p class="mt-2 line-clamp-2 text-sm leading-relaxed text-zinc-500">{{ $post->excerpt }}</p>
            @endif
        @endunless

        <div class="mt-4 flex flex-1 items-end">
            <div class="flex w-full flex-wrap items-center gap-x-3 gap-y-1 text-xs text-zinc-500">
                <time datetime="{{ $post->published_at?->toDateString() }}" class="font-medium">
                    {{ $post->published_at?->translatedFormat('j M Y') }}
                </time>
                <span class="h-1 w-1 rounded-full bg-zinc-300" aria-hidden="true"></span>
                <span class="inline-flex items-center gap-1">
                    <flux:icon name="clock" class="h-3.5 w-3.5" />
                    {{ __('blog.min_read', ['minutes' => $post->reading_time]) }}
                </span>
            </div>
        </div>
    </div>
</article>
