@props(['post'])

<article class="group py-5 first:pt-0">
    <a href="{{ route('blog.show', $post->slug) }}" class="flex gap-4 sm:gap-5">
        <div class="relative aspect-[4/3] w-28 shrink-0 overflow-hidden rounded-xl bg-zinc-100 sm:w-44">
            @if($post->featuredImage?->url)
                <img src="{{ $post->featuredImage->url }}"
                     alt="{{ $post->featuredImage->alt_text ?: $post->title }}"
                     loading="lazy" decoding="async"
                     class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105">
            @else
                <div class="store-grad flex h-full w-full items-center justify-center">
                    <flux:icon name="newspaper" class="h-6 w-6 text-white/90" />
                </div>
            @endif
        </div>

        <div class="min-w-0 flex-1">
            @if($post->category)
                <span class="text-[11px] font-bold uppercase tracking-wide" style="color: {{ $post->category->color }}">
                    {{ $post->category->name }}
                </span>
            @endif
            <h3 class="font-display mt-1 line-clamp-2 text-base font-bold leading-snug text-zinc-900 transition-colors group-hover:text-violet-700 sm:text-lg">
                {{ $post->title }}
            </h3>
            @if($post->excerpt)
                <p class="mt-1.5 hidden line-clamp-2 text-sm leading-relaxed text-zinc-500 sm:block">{{ $post->excerpt }}</p>
            @endif
            <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-zinc-400">
                <span class="font-semibold text-zinc-600">{{ $post->author_name }}</span>
                <span class="h-1 w-1 rounded-full bg-zinc-300" aria-hidden="true"></span>
                <time datetime="{{ $post->published_at?->toDateString() }}">{{ $post->published_at?->translatedFormat('j M Y') }}</time>
                <span class="hidden h-1 w-1 rounded-full bg-zinc-300 sm:block" aria-hidden="true"></span>
                <span class="hidden items-center gap-1 sm:inline-flex">
                    <flux:icon name="clock" class="h-3.5 w-3.5" />
                    {{ __('blog.min_read', ['minutes' => $post->reading_time]) }}
                </span>
            </div>
        </div>
    </a>
</article>
