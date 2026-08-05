@php
    $img = $post->featuredImage?->url;
    $accent = $post->category?->color ?: '#7c3aed';
    $shareUrl = route('blog.show', $post->slug);
    $seo['breadcrumbs'] = array_values(array_filter([
        ['name' => config('store.name'), 'url' => route('storefront.home')],
        ['name' => __('blog.nav_blog'), 'url' => route('blog.index')],
        $post->category ? ['name' => $post->category->name, 'url' => route('blog.category', $post->category->slug)] : null,
        ['name' => $post->title, 'url' => $shareUrl],
    ]));
    $seo['word_count'] = data_get($post->seo_report, 'word_count');
@endphp

<x-layouts.store :seo="$seo">

    {{-- ============ READING PROGRESS ============
         Purely decorative, so it is hidden from assistive tech and disabled
         entirely when the visitor asks for reduced motion. --}}
    <div
        x-data="{ progress: 0 }"
        x-init="
            const update = () => {
                const el = document.getElementById('article-body');
                if (!el) return;
                const start = el.offsetTop;
                const total = el.offsetHeight - window.innerHeight + 200;
                progress = Math.min(100, Math.max(0, (window.scrollY - start + 200) / total * 100));
            };
            update();
            window.addEventListener('scroll', update, { passive: true });
            window.addEventListener('resize', update, { passive: true });
        "
        class="fixed inset-x-0 top-0 z-50 h-1 bg-transparent motion-reduce:hidden"
        aria-hidden="true"
    >
        <div class="store-grad h-full origin-left transition-[width] duration-150 ease-out" :style="`width: ${progress}%`"></div>
    </div>

    {{-- ===================== HEADER ===================== --}}
    <header class="relative overflow-hidden border-b border-violet-100/70 bg-gradient-to-b from-violet-50 via-fuchsia-50/50 to-white">
        <div class="store-hero-grid absolute inset-0 opacity-60"></div>
        <span class="pointer-events-none absolute -right-24 -top-24 h-64 w-64 rounded-full bg-fuchsia-300/30 blur-3xl"></span>

        <div class="relative mx-auto max-w-4xl px-4 py-10 sm:px-6 sm:py-14 lg:px-8">
            {{-- Breadcrumb --}}
            <nav class="flex flex-wrap items-center gap-1.5 text-xs font-medium text-zinc-500" aria-label="Breadcrumb">
                <a href="{{ route('blog.index') }}" class="transition-colors hover:text-violet-700">{{ __('blog.nav_blog') }}</a>
                @if($post->category)
                    <flux:icon name="chevron-right" class="h-3.5 w-3.5 text-zinc-300" />
                    <a href="{{ route('blog.category', $post->category->slug) }}" class="transition-colors hover:text-violet-700">{{ $post->category->name }}</a>
                @endif
            </nav>

            @if($post->category)
                <span class="mt-5 inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold text-white shadow-sm"
                      style="background-color: {{ $accent }}">
                    {{ $post->category->name }}
                </span>
            @endif

            <h1 class="font-display mt-4 text-3xl font-extrabold leading-[1.15] text-zinc-900 sm:text-4xl lg:text-5xl">
                {{ $post->title }}
            </h1>

            @if($post->excerpt)
                <p class="mt-4 max-w-2xl text-base leading-relaxed text-zinc-600 sm:text-lg">{{ $post->excerpt }}</p>
            @endif

            {{-- Author + meta --}}
            <div class="mt-7 flex flex-wrap items-center gap-x-4 gap-y-2">
                <div class="flex items-center gap-2.5">
                    <span class="store-grad font-display grid h-10 w-10 shrink-0 place-items-center rounded-full text-sm font-bold text-white">
                        {{ strtoupper(mb_substr($post->author_name, 0, 1)) }}
                    </span>
                    <div class="leading-tight">
                        <p class="text-sm font-bold text-zinc-900">{{ $post->author_name }}</p>
                        <p class="text-xs text-zinc-500">
                            <time datetime="{{ $post->published_at?->toDateString() }}">{{ $post->published_at?->translatedFormat('j F Y') }}</time>
                        </p>
                    </div>
                </div>

                <span class="hidden h-8 w-px bg-zinc-200 sm:block" aria-hidden="true"></span>

                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-zinc-500">
                    <span class="inline-flex items-center gap-1.5">
                        <flux:icon name="clock" class="h-4 w-4 text-violet-500" />
                        {{ __('blog.min_read', ['minutes' => $post->reading_time]) }}
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <flux:icon name="eye" class="h-4 w-4 text-fuchsia-500" />
                        {{ trans_choice('blog.views_count', $post->view_count, ['count' => number_format($post->view_count)]) }}
                    </span>
                </div>
            </div>
        </div>
    </header>

    {{-- ===================== FEATURED IMAGE ===================== --}}
    @if($img)
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <figure class="-mt-2 overflow-hidden rounded-3xl border border-zinc-100 bg-zinc-50 shadow-xl shadow-fuchsia-900/5">
                <img src="{{ $img }}" alt="{{ $post->featuredImage?->alt_text ?: $post->title }}"
                     class="h-full w-full object-cover" fetchpriority="high">
            </figure>
        </div>
    @endif

    {{-- ===================== BODY ===================== --}}
    <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
        <div class="grid gap-10 lg:grid-cols-12">

            {{-- ---------- ARTICLE ---------- --}}
            <main class="lg:col-span-8">
                {{-- Mobile TOC: collapsible so it never pushes the article down a screen. --}}
                @if(count($toc) > 1)
                    <details class="mb-8 rounded-2xl border border-zinc-200 bg-zinc-50/70 p-4 lg:hidden">
                        <summary class="font-display cursor-pointer text-sm font-bold text-zinc-900 marker:text-violet-500">
                            {{ __('blog.toc_title') }}
                        </summary>
                        <ul class="mt-3 space-y-2 border-l-2 border-violet-100 pl-4">
                            @foreach($toc as $item)
                                <li class="{{ $item['level'] === 3 ? 'pl-3' : '' }}">
                                    <a href="#{{ $item['id'] }}" class="text-sm text-zinc-600 transition-colors hover:text-violet-700">{{ $item['text'] }}</a>
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @endif

                <div id="article-body" class="blog-prose">
                    {!! $post->content_html !!}
                </div>

                {{-- ---------- TAGS ---------- --}}
                @if($post->tags->isNotEmpty())
                    <div class="mt-10 flex flex-wrap items-center gap-2">
                        @foreach($post->tags as $tag)
                            <a href="{{ route('blog.tag', $tag->slug) }}"
                               class="inline-flex items-center gap-1 rounded-full border border-zinc-200 px-3 py-1.5 text-xs font-semibold text-zinc-600 transition-colors hover:border-violet-300 hover:bg-violet-50 hover:text-violet-700">
                                <flux:icon name="hashtag" class="h-3.5 w-3.5" />
                                {{ $tag->name }}
                            </a>
                        @endforeach
                    </div>
                @endif

                {{-- ---------- SHARE ---------- --}}
                <div class="mt-10 flex flex-wrap items-center gap-3 rounded-2xl border border-zinc-100 bg-zinc-50/70 p-4"
                     x-data="{ copied: false, copy() {
                        navigator.clipboard.writeText('{{ $shareUrl }}').then(() => {
                            this.copied = true;
                            setTimeout(() => this.copied = false, 2200);
                        });
                     } }">
                    <span class="font-display text-sm font-bold text-zinc-900">{{ __('blog.share_title') }}</span>

                    <div class="flex flex-wrap items-center gap-2">
                        <a href="https://wa.me/?text={{ urlencode($post->title.' — '.$shareUrl) }}" target="_blank" rel="noopener noreferrer"
                           class="inline-flex h-11 items-center gap-1.5 rounded-xl bg-white px-3.5 text-xs font-semibold text-zinc-700 ring-1 ring-zinc-200 transition-colors hover:bg-emerald-50 hover:text-emerald-700 hover:ring-emerald-200"
                           aria-label="{{ __('blog.share_whatsapp') }}">
                            <flux:icon name="chat-bubble-left-right" class="h-4 w-4" />
                            WhatsApp
                        </a>

                        <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($shareUrl) }}" target="_blank" rel="noopener noreferrer"
                           class="inline-flex h-11 items-center gap-1.5 rounded-xl bg-white px-3.5 text-xs font-semibold text-zinc-700 ring-1 ring-zinc-200 transition-colors hover:bg-blue-50 hover:text-blue-700 hover:ring-blue-200"
                           aria-label="{{ __('blog.share_facebook') }}">
                            <flux:icon name="globe-alt" class="h-4 w-4" />
                            Facebook
                        </a>

                        <a href="https://x.com/intent/tweet?text={{ urlencode($post->title) }}&url={{ urlencode($shareUrl) }}" target="_blank" rel="noopener noreferrer"
                           class="inline-flex h-11 items-center gap-1.5 rounded-xl bg-white px-3.5 text-xs font-semibold text-zinc-700 ring-1 ring-zinc-200 transition-colors hover:bg-zinc-900 hover:text-white"
                           aria-label="{{ __('blog.share_x') }}">
                            <flux:icon name="paper-airplane" class="h-4 w-4" />
                            X
                        </a>

                        <button type="button" @click="copy()"
                                class="inline-flex h-11 items-center gap-1.5 rounded-xl bg-white px-3.5 text-xs font-semibold text-zinc-700 ring-1 ring-zinc-200 transition-colors hover:bg-violet-50 hover:text-violet-700 hover:ring-violet-200"
                                :aria-label="copied ? '{{ __('blog.share_copied') }}' : '{{ __('blog.share_copy') }}'">
                            <flux:icon name="link" class="h-4 w-4" x-show="!copied" />
                            <flux:icon name="check" class="h-4 w-4 text-emerald-600" x-show="copied" x-cloak />
                            <span x-text="copied ? '{{ __('blog.share_copied') }}' : '{{ __('blog.share_copy') }}'"></span>
                        </button>
                    </div>
                </div>

                {{-- ---------- PRODUCTS IN THIS ARTICLE ---------- --}}
                @if($post->products->isNotEmpty())
                    <section class="mt-12">
                        <div class="flex items-end justify-between gap-4">
                            <div>
                                <h2 class="font-display text-xl font-extrabold text-zinc-900">{{ __('blog.products_title') }}</h2>
                                <p class="mt-1 text-sm text-zinc-500">{{ __('blog.products_subtitle') }}</p>
                            </div>
                        </div>
                        <div class="mt-5 grid gap-5 sm:grid-cols-2">
                            @foreach($post->products as $product)
                                <x-store.product-card :product="$product" :key="'bp-'.$product->id" />
                            @endforeach
                        </div>
                    </section>
                @endif

                {{-- ---------- AUTHOR ---------- --}}
                <section class="mt-12 flex flex-col gap-4 rounded-2xl border border-zinc-100 bg-gradient-to-br from-violet-50/60 via-fuchsia-50/40 to-rose-50/40 p-6 sm:flex-row sm:items-center">
                    <span class="store-grad font-display grid h-14 w-14 shrink-0 place-items-center rounded-2xl text-lg font-extrabold text-white">
                        {{ strtoupper(mb_substr($post->author_name, 0, 1)) }}
                    </span>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-violet-600">{{ __('blog.written_by') }}</p>
                        <p class="font-display mt-0.5 text-lg font-bold text-zinc-900">{{ $post->author_name }}</p>
                        <p class="mt-1 text-sm leading-relaxed text-zinc-600">{{ __('blog.author_bio', ['store' => config('store.name')]) }}</p>
                    </div>
                </section>

                {{-- ---------- NEWSLETTER ---------- --}}
                @if(config('blog.newsletter_enabled'))
                    <div class="mt-12">
                        <x-store.newsletter-form context="banner" :post="$post" />
                    </div>
                @endif

                {{-- ---------- COMMENTS ---------- --}}
                <section id="comments" class="mt-12 scroll-mt-24">
                    <h2 class="font-display flex items-center gap-2 text-xl font-extrabold text-zinc-900">
                        <flux:icon name="chat-bubble-left-ellipsis" class="h-5 w-5 text-violet-600" />
                        {{ __('blog.comments_title') }}
                        <span class="rounded-full bg-violet-50 px-2.5 py-0.5 text-sm font-bold text-violet-700 tabular-nums">{{ $comments->count() }}</span>
                    </h2>

                    @if(session('comment_status'))
                        <div class="mt-4 flex items-center gap-2 rounded-xl bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700" role="status" aria-live="polite">
                            <flux:icon name="check-circle" class="h-5 w-5 shrink-0" />
                            {{ session('comment_status') === 'pending' ? __('blog.comment_pending') : __('blog.comment_published') }}
                        </div>
                    @endif

                    @if(! $post->allow_comments)
                        <p class="mt-4 rounded-xl bg-zinc-50 px-4 py-3 text-sm text-zinc-500">{{ __('blog.comments_closed') }}</p>
                    @else
                        {{-- Comment form --}}
                        @auth
                            <form method="POST" action="{{ route('blog.comments.store', $post) }}" class="mt-6" x-data="{ replyTo: null, replyName: '' }"
                                  @reply-to.window="replyTo = $event.detail.id; replyName = $event.detail.name; $refs.body.focus()">
                                @csrf
                                <input type="hidden" name="parent_id" :value="replyTo">

                                <template x-if="replyTo">
                                    <div class="mb-2 flex items-center justify-between rounded-lg bg-violet-50 px-3 py-2 text-xs font-semibold text-violet-700">
                                        <span x-text="`{{ __('blog.comment_reply_to', ['name' => '__NAME__']) }}`.replace('__NAME__', replyName)"></span>
                                        <button type="button" @click="replyTo = null" class="rounded px-2 py-1 hover:bg-violet-100">{{ __('blog.comment_cancel') }}</button>
                                    </div>
                                </template>

                                <label for="comment-body" class="sr-only">{{ __('blog.comment_placeholder') }}</label>
                                <textarea id="comment-body" x-ref="body" name="body" rows="4" required
                                          placeholder="{{ __('blog.comment_placeholder') }}"
                                          class="w-full rounded-2xl border-zinc-200 bg-white px-4 py-3 text-base text-zinc-900 placeholder:text-zinc-400 focus:border-violet-400 focus:ring-2 focus:ring-violet-200 sm:text-sm">{{ old('body') }}</textarea>
                                @error('body')
                                    <p class="mt-1.5 text-xs font-semibold text-rose-600" role="alert">{{ $message }}</p>
                                @enderror

                                <button type="submit" class="store-grad store-grad-hover mt-3 inline-flex h-11 items-center rounded-xl px-5 text-sm font-bold text-white">
                                    {{ __('blog.comment_submit') }}
                                </button>
                            </form>
                        @else
                            <div class="mt-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-zinc-200 bg-zinc-50 px-5 py-4">
                                <p class="text-sm text-zinc-600">{{ __('blog.comment_login') }}</p>
                                <a href="{{ route('login') }}" class="store-grad store-grad-hover inline-flex h-11 items-center rounded-xl px-5 text-sm font-bold text-white">
                                    {{ __('blog.comment_login_cta') }}
                                </a>
                            </div>
                        @endauth

                        {{-- Thread --}}
                        @if($comments->isEmpty())
                            <p class="mt-8 rounded-2xl border border-dashed border-zinc-200 px-5 py-8 text-center text-sm text-zinc-500">
                                {{ __('blog.comments_empty') }}
                            </p>
                        @else
                            <ol class="mt-8 space-y-6">
                                @foreach($comments as $comment)
                                    <li wire:key="comment-{{ $comment->id }}">
                                        <x-store.comment :comment="$comment" />

                                        @if($comment->replies->isNotEmpty())
                                            <ol class="mt-4 space-y-4 border-l-2 border-violet-100 pl-4 sm:pl-6">
                                                @foreach($comment->replies as $reply)
                                                    <li>
                                                        <x-store.comment :comment="$reply" :is-reply="true" />
                                                    </li>
                                                @endforeach
                                            </ol>
                                        @endif
                                    </li>
                                @endforeach
                            </ol>
                        @endif
                    @endif
                </section>
            </main>

            {{-- ---------- STICKY SIDEBAR ---------- --}}
            <aside class="hidden lg:col-span-4 lg:block">
                <div class="sticky top-24 space-y-6">
                    @if(count($toc) > 1)
                        <nav class="rounded-2xl border border-zinc-100 bg-white p-5"
                             aria-label="{{ __('blog.toc_title') }}"
                             x-data="{
                                active: '',
                                init() {
                                    const ids = @js(array_column($toc, 'id'));
                                    const targets = ids.map(id => document.getElementById(id)).filter(Boolean);
                                    if (!targets.length) return;
                                    const observer = new IntersectionObserver((entries) => {
                                        entries.forEach(entry => {
                                            if (entry.isIntersecting) this.active = entry.target.id;
                                        });
                                    }, { rootMargin: '-80px 0px -70% 0px', threshold: 0 });
                                    targets.forEach(t => observer.observe(t));
                                }
                             }">
                            <h2 class="font-display flex items-center gap-2 text-sm font-bold text-zinc-900">
                                <flux:icon name="list-bullet" class="h-4 w-4 text-violet-600" />
                                {{ __('blog.toc_title') }}
                            </h2>
                            <ul class="mt-4 space-y-1 border-l-2 border-zinc-100">
                                @foreach($toc as $item)
                                    <li>
                                        <a href="#{{ $item['id'] }}"
                                           class="-ml-0.5 block border-l-2 py-1.5 text-sm transition-colors"
                                           :class="active === '{{ $item['id'] }}'
                                                ? 'border-violet-600 font-semibold text-violet-700'
                                                : 'border-transparent text-zinc-500 hover:text-zinc-900'"
                                           style="padding-left: {{ $item['level'] === 3 ? '1.75rem' : '1rem' }}">
                                            {{ $item['text'] }}
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </nav>
                    @endif

                    @if(config('blog.newsletter_enabled'))
                        <x-store.newsletter-form context="sidebar" :post="$post" />
                    @endif
                </div>
            </aside>
        </div>

        {{-- ===================== RELATED ===================== --}}
        @if($related->isNotEmpty())
            <section class="mt-16 border-t border-zinc-100 pt-12">
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <h2 class="font-display text-2xl font-extrabold text-zinc-900">{{ __('blog.related_title') }}</h2>
                        <p class="mt-1 text-sm text-zinc-500">{{ __('blog.related_subtitle') }}</p>
                    </div>
                    <a href="{{ route('blog.index') }}" class="hidden shrink-0 items-center gap-1 text-sm font-semibold text-violet-700 hover:text-fuchsia-700 sm:inline-flex">
                        {{ __('blog.back_to_blog') }} <flux:icon name="arrow-right" class="h-4 w-4" />
                    </a>
                </div>
                <div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($related as $rel)
                        <x-store.post-card :post="$rel" :key="'rel-'.$rel->id" />
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-layouts.store>
