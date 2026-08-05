@php
    $activeTag = $activeTag ?? null;
    $heading = $activeCategory?->name ?? ($activeTag ? __('blog.tag_title', ['tag' => $activeTag->name]) : __('blog.index_heading'));
    $sub = $activeCategory?->description ?? ($activeTag ? __('blog.tag_meta', ['tag' => $activeTag->name, 'store' => config('store.name')]) : __('blog.index_subheading'));
@endphp

<x-layouts.store :seo="$seo">

    {{-- ===================== HERO ===================== --}}
    <section class="relative overflow-hidden border-b border-violet-100/70 bg-gradient-to-b from-violet-50 via-fuchsia-50/60 to-white">
        <div class="store-hero-grid absolute inset-0 opacity-70"></div>
        <span class="pointer-events-none absolute -right-24 -top-24 h-72 w-72 rounded-full bg-fuchsia-300/40 blur-3xl"></span>
        <span class="pointer-events-none absolute -left-24 top-20 h-72 w-72 rounded-full bg-violet-300/40 blur-3xl"></span>

        <div class="relative mx-auto max-w-7xl px-4 py-14 sm:px-6 sm:py-18 lg:px-8">
            <div class="mx-auto max-w-3xl text-center">
                <span class="inline-flex items-center gap-1.5 rounded-full border border-violet-200 bg-white/80 px-3 py-1 text-xs font-semibold text-violet-700 shadow-sm backdrop-blur">
                    <span class="store-grad h-1.5 w-1.5 rounded-full"></span>
                    {{ __('blog.index_eyebrow') }}
                </span>

                <h1 class="font-display mt-5 text-4xl font-extrabold leading-[1.1] text-zinc-900 sm:text-5xl">
                    {{ $heading }}
                </h1>

                @if($sub)
                    <p class="mx-auto mt-4 max-w-2xl text-base leading-relaxed text-zinc-600">{{ $sub }}</p>
                @endif

                {{-- Search --}}
                <form method="GET" action="{{ $activeCategory ? route('blog.category', $activeCategory->slug) : route('blog.index') }}"
                      class="mx-auto mt-8 flex max-w-xl items-center gap-2 rounded-2xl border border-white/60 bg-white/90 p-1.5 shadow-xl shadow-fuchsia-900/10 ring-1 ring-zinc-900/5 backdrop-blur">
                    <div class="flex flex-1 items-center gap-2 pl-3">
                        <flux:icon name="magnifying-glass" class="h-5 w-5 shrink-0 text-zinc-400" />
                        <label for="blog-search" class="sr-only">{{ __('blog.search_placeholder') }}</label>
                        <input id="blog-search" type="search" name="q" value="{{ $search }}" placeholder="{{ __('blog.search_placeholder') }}"
                               class="w-full border-0 bg-transparent py-2.5 text-base text-zinc-900 placeholder:text-zinc-400 focus:outline-none focus:ring-0 sm:text-sm" />
                    </div>
                    <button type="submit" class="store-grad store-grad-hover shrink-0 rounded-xl px-5 py-2.5 text-sm font-semibold text-white">
                        {{ __('blog.search_cta') }}
                    </button>
                </form>
            </div>
        </div>
    </section>

    {{-- ===================== TOPIC RAIL ===================== --}}
    @if($categories->isNotEmpty())
        <nav class="border-b border-zinc-100 bg-white/80 backdrop-blur" aria-label="{{ __('blog.categories_title') }}">
            <div class="mx-auto flex max-w-7xl gap-2 overflow-x-auto px-4 py-3 sm:px-6 lg:px-8 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                <a href="{{ route('blog.index') }}"
                   @class([
                       'shrink-0 rounded-full px-4 py-2 text-sm font-semibold transition-colors',
                       'store-grad text-white shadow-sm' => ! $activeCategory,
                       'bg-zinc-100 text-zinc-600 hover:bg-violet-50 hover:text-violet-700' => $activeCategory,
                   ])>
                    {{ __('blog.all_categories') }}
                </a>
                @foreach($categories as $cat)
                    <a href="{{ route('blog.category', $cat->slug) }}"
                       @class([
                           'shrink-0 rounded-full px-4 py-2 text-sm font-semibold transition-colors',
                           'store-grad text-white shadow-sm' => $activeCategory?->id === $cat->id,
                           'bg-zinc-100 text-zinc-600 hover:bg-violet-50 hover:text-violet-700' => $activeCategory?->id !== $cat->id,
                       ])>
                        {{ $cat->name }}
                        <span class="ml-1 tabular-nums opacity-60">{{ $cat->published_count }}</span>
                    </a>
                @endforeach
            </div>
        </nav>
    @endif

    <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">

        {{-- ===================== FEATURED ===================== --}}
        @if($featured && $posts->currentPage() === 1)
            @php $featImg = $featured->featuredImage?->url; @endphp
            <a href="{{ route('blog.show', $featured->slug) }}"
               class="group mb-12 grid overflow-hidden rounded-3xl border border-zinc-100 bg-white transition-all duration-300 hover:border-violet-200 hover:shadow-2xl hover:shadow-fuchsia-900/10 lg:grid-cols-2">
                <div class="relative aspect-[16/10] overflow-hidden bg-zinc-50 lg:aspect-auto lg:min-h-[340px]">
                    @if($featImg)
                        <img src="{{ $featImg }}" alt="{{ $featured->featuredImage?->alt_text ?: $featured->title }}"
                             class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-[1.03]">
                    @else
                        <div class="flex h-full w-full items-center justify-center bg-gradient-to-br from-violet-100 via-fuchsia-100 to-rose-100">
                            <flux:icon name="newspaper" class="h-16 w-16 text-violet-300" />
                        </div>
                    @endif
                    <span class="store-grad absolute left-4 top-4 inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-[11px] font-bold uppercase tracking-wide text-white shadow-lg">
                        <flux:icon name="sparkles" class="h-3.5 w-3.5" />
                        {{ __('blog.featured_badge') }}
                    </span>
                </div>

                <div class="flex flex-col justify-center p-6 sm:p-10">
                    @if($featured->category)
                        <span class="text-xs font-bold uppercase tracking-wide" style="color: {{ $featured->category->color }}">
                            {{ $featured->category->name }}
                        </span>
                    @endif
                    <h2 class="font-display mt-2 text-2xl font-extrabold leading-tight text-zinc-900 transition-colors group-hover:text-violet-700 sm:text-3xl">
                        {{ $featured->title }}
                    </h2>
                    @if($featured->excerpt)
                        <p class="mt-3 line-clamp-3 text-sm leading-relaxed text-zinc-600 sm:text-base">{{ $featured->excerpt }}</p>
                    @endif

                    <div class="mt-6 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-zinc-500">
                        <span class="font-semibold text-zinc-700">{{ $featured->author_name }}</span>
                        <span class="h-1 w-1 rounded-full bg-zinc-300" aria-hidden="true"></span>
                        <time datetime="{{ $featured->published_at?->toDateString() }}">{{ $featured->published_at?->translatedFormat('j M Y') }}</time>
                        <span class="h-1 w-1 rounded-full bg-zinc-300" aria-hidden="true"></span>
                        <span class="inline-flex items-center gap-1">
                            <flux:icon name="clock" class="h-3.5 w-3.5" />
                            {{ __('blog.min_read', ['minutes' => $featured->reading_time]) }}
                        </span>
                    </div>

                    <span class="mt-6 inline-flex w-fit items-center gap-1.5 text-sm font-bold text-violet-700 transition-colors group-hover:text-fuchsia-700">
                        {{ __('blog.read_article') }}
                        <flux:icon name="arrow-right" class="h-4 w-4 transition-transform group-hover:translate-x-0.5" />
                    </span>
                </div>
            </a>
        @endif

        <div class="grid gap-10 lg:grid-cols-12">
            {{-- ===================== ARTICLE GRID ===================== --}}
            <div class="lg:col-span-8">
                @if($search !== '')
                    <p class="mb-6 text-sm text-zinc-500">
                        {{ trans_choice('blog.search_results', $posts->total(), ['count' => $posts->total()]) }}
                        <span class="font-semibold text-zinc-800">“{{ $search }}”</span>
                    </p>
                @endif

                @if($posts->isEmpty())
                    <div class="rounded-3xl border border-dashed border-zinc-200 bg-zinc-50/60 px-6 py-20 text-center">
                        <span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-gradient-to-br from-violet-100 via-fuchsia-100 to-rose-100">
                            <flux:icon name="magnifying-glass" class="h-6 w-6 text-violet-500" />
                        </span>
                        <h3 class="font-display mt-4 text-lg font-bold text-zinc-900">
                            {{ $search !== '' ? __('blog.no_results') : __('blog.empty_state') }}
                        </h3>
                        <p class="mt-1.5 text-sm text-zinc-500">
                            {{ $search !== '' ? __('blog.no_results_hint') : __('blog.empty_state_hint') }}
                        </p>
                    </div>
                @else
                    <div class="grid gap-6 sm:grid-cols-2">
                        @foreach($posts as $post)
                            <x-store.post-card :post="$post" :key="'post-'.$post->id" />
                        @endforeach
                    </div>

                    <div class="mt-10">
                        {{ $posts->links() }}
                    </div>
                @endif
            </div>

            {{-- ===================== SIDEBAR ===================== --}}
            <aside class="space-y-6 lg:col-span-4">
                <div class="lg:sticky lg:top-24 lg:space-y-6">
                    {{-- Most read --}}
                    @if($popular->isNotEmpty())
                        <section class="rounded-2xl border border-zinc-100 bg-white p-5">
                            <h2 class="font-display flex items-center gap-2 text-sm font-bold text-zinc-900">
                                <flux:icon name="fire" class="h-4 w-4 text-rose-500" />
                                {{ __('blog.popular_title') }}
                            </h2>
                            <ol class="mt-4 space-y-3">
                                @foreach($popular as $i => $pop)
                                    <li>
                                        <a href="{{ route('blog.show', $pop->slug) }}" class="group flex gap-3">
                                            <span class="font-display grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-violet-50 text-xs font-extrabold text-violet-700 tabular-nums">
                                                {{ $i + 1 }}
                                            </span>
                                            <span class="min-w-0">
                                                <span class="line-clamp-2 text-sm font-semibold leading-snug text-zinc-800 transition-colors group-hover:text-violet-700">{{ $pop->title }}</span>
                                                <span class="mt-0.5 block text-xs text-zinc-400">{{ __('blog.min_read', ['minutes' => $pop->reading_time]) }}</span>
                                            </span>
                                        </a>
                                    </li>
                                @endforeach
                            </ol>
                        </section>
                    @endif

                    {{-- Topics --}}
                    @if($categories->isNotEmpty())
                        <section class="rounded-2xl border border-zinc-100 bg-white p-5">
                            <h2 class="font-display text-sm font-bold text-zinc-900">{{ __('blog.categories_title') }}</h2>
                            <ul class="mt-4 flex flex-wrap gap-2">
                                @foreach($categories as $cat)
                                    <li>
                                        <a href="{{ route('blog.category', $cat->slug) }}"
                                           class="inline-flex items-center gap-1.5 rounded-full border border-zinc-200 px-3 py-1.5 text-xs font-semibold text-zinc-600 transition-colors hover:border-violet-300 hover:bg-violet-50 hover:text-violet-700">
                                            <span class="h-2 w-2 rounded-full" style="background-color: {{ $cat->color }}"></span>
                                            {{ $cat->name }}
                                            <span class="tabular-nums opacity-50">{{ $cat->published_count }}</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endif

                    {{-- Newsletter --}}
                    @if(config('blog.newsletter_enabled'))
                        <x-store.newsletter-form context="sidebar" />
                    @endif
                </div>
            </aside>
        </div>
    </div>
</x-layouts.store>
