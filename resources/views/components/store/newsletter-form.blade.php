@props(['context' => 'sidebar', 'post' => null])

@php
    // `sidebar` is the compact card on the index; `banner` is the wide,
    // gradient end-of-article block.
    $isBanner = $context === 'banner';
@endphp

<section id="newsletter" @class([
    'rounded-2xl' => ! $isBanner,
    'store-grad rounded-3xl' => $isBanner,
    'relative overflow-hidden',
    'border border-violet-100 bg-gradient-to-br from-violet-50 via-fuchsia-50 to-rose-50 p-5' => ! $isBanner,
    'px-6 py-10 sm:px-10' => $isBanner,
])>
    @if($isBanner)
        <span class="pointer-events-none absolute -right-16 -top-16 h-56 w-56 rounded-full bg-white/15 blur-2xl"></span>
        <span class="pointer-events-none absolute -bottom-20 -left-10 h-56 w-56 rounded-full bg-white/10 blur-2xl"></span>
    @endif

    <div class="relative {{ $isBanner ? 'mx-auto max-w-2xl text-center' : '' }}">
        <h2 @class([
            'font-display font-bold',
            'text-2xl text-white sm:text-3xl' => $isBanner,
            'text-sm text-zinc-900' => ! $isBanner,
        ])>
            {{ __('blog.newsletter_title') }}
        </h2>

        <p @class([
            'mt-2 leading-relaxed',
            'text-sm text-white/85 sm:text-base' => $isBanner,
            'text-xs text-zinc-600' => ! $isBanner,
        ])>
            {{ __('blog.newsletter_subtitle') }}
        </p>

        @if(session('newsletter_status') === 'subscribed')
            <div @class([
                'mt-4 flex items-center gap-2 rounded-xl px-4 py-3 text-sm font-semibold',
                'bg-white/20 text-white' => $isBanner,
                'bg-emerald-50 text-emerald-700' => ! $isBanner,
            ]) role="status" aria-live="polite">
                <flux:icon name="check-circle" class="h-5 w-5 shrink-0" />
                {{ __('blog.newsletter_success') }}
            </div>
        @else
            <form method="POST" action="{{ route('blog.subscribe') }}" @class(['mt-4', 'space-y-2.5' => ! $isBanner])>
                @csrf
                <input type="hidden" name="source" value="{{ $context }}">
                @if($post)
                    <input type="hidden" name="blog_post_id" value="{{ $post->id }}">
                @endif

                <div @class(['flex flex-col gap-2.5', 'sm:flex-row sm:items-start' => $isBanner])>
                    <div class="flex-1">
                        <label for="nl-email-{{ $context }}" class="sr-only">{{ __('blog.newsletter_placeholder') }}</label>
                        <input
                            id="nl-email-{{ $context }}"
                            type="email"
                            name="email"
                            inputmode="email"
                            autocomplete="email"
                            required
                            value="{{ old('email') }}"
                            placeholder="{{ __('blog.newsletter_placeholder') }}"
                            @class([
                                'w-full rounded-xl border-0 px-4 py-3 text-base placeholder:text-zinc-400 focus:outline-none focus:ring-2 sm:text-sm',
                                'bg-white/95 text-zinc-900 focus:ring-white' => $isBanner,
                                'bg-white text-zinc-900 ring-1 ring-zinc-200 focus:ring-violet-500' => ! $isBanner,
                            ])
                        >
                        @error('email')
                            <p class="mt-1.5 text-left text-xs font-semibold {{ $isBanner ? 'text-white' : 'text-rose-600' }}" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit" @class([
                        'shrink-0 rounded-xl px-6 py-3 text-sm font-bold transition-transform active:scale-[0.98]',
                        'bg-white text-violet-700 hover:bg-violet-50' => $isBanner,
                        'store-grad store-grad-hover w-full text-white' => ! $isBanner,
                    ])>
                        {{ __('blog.newsletter_cta') }}
                    </button>
                </div>

                <p @class([
                    'text-xs',
                    'mt-3 text-white/70' => $isBanner,
                    'text-zinc-500' => ! $isBanner,
                ])>
                    {{ __('blog.newsletter_privacy') }}
                </p>
            </form>
        @endif
    </div>
</section>
