@props(['post'])

<li>
    <a href="{{ route('blog.show', $post->slug) }}"
       class="group flex items-start gap-3 rounded-xl p-2 transition-colors hover:bg-violet-50/70">
        <span class="relative aspect-square w-14 shrink-0 overflow-hidden rounded-lg bg-zinc-100">
            @if($post->featuredImage?->url)
                <img src="{{ $post->featuredImage->url }}"
                     alt="{{ $post->featuredImage->alt_text ?: $post->title }}"
                     loading="lazy" decoding="async"
                     class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105">
            @else
                <span class="store-grad flex h-full w-full items-center justify-center">
                    <flux:icon name="newspaper" class="h-5 w-5 text-white/90" />
                </span>
            @endif
        </span>
        <span class="min-w-0 flex-1">
            <span class="line-clamp-2 text-sm font-semibold leading-snug text-zinc-800 transition-colors group-hover:text-violet-700">
                {{ $post->title }}
            </span>
            <span class="mt-1 flex items-center gap-1.5 text-xs text-zinc-400">
                <flux:icon name="calendar" class="h-3.5 w-3.5" />
                <time datetime="{{ $post->published_at?->toDateString() }}">
                    {{ $post->published_at?->translatedFormat('j M Y') }}
                </time>
            </span>
        </span>
    </a>
</li>
