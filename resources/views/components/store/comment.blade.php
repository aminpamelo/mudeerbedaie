@props(['comment', 'isReply' => false])

<article class="flex gap-3">
    <span @class([
        'font-display grid shrink-0 place-items-center rounded-full font-bold text-white',
        'h-10 w-10 text-sm store-grad' => ! $isReply,
        'h-8 w-8 text-xs bg-zinc-400' => $isReply,
    ]) aria-hidden="true">
        {{ $comment->initials }}
    </span>

    <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
            <span class="text-sm font-bold text-zinc-900">{{ $comment->display_name }}</span>
            <time datetime="{{ $comment->created_at?->toIso8601String() }}" class="text-xs text-zinc-400">
                {{ $comment->created_at?->diffForHumans() }}
            </time>
        </div>

        <p class="mt-1.5 whitespace-pre-line text-sm leading-relaxed text-zinc-700">{{ $comment->body }}</p>

        @if(! $isReply)
            @auth
                <button type="button"
                        @click="$dispatch('reply-to', { id: {{ $comment->id }}, name: @js($comment->display_name) })"
                        class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-violet-600 transition-colors hover:text-fuchsia-600">
                    <flux:icon name="arrow-uturn-left" class="h-3.5 w-3.5" />
                    {{ __('blog.comment_reply') }}
                </button>
            @endauth
        @endif
    </div>
</article>
