@php
    $activeTag = $activeTag ?? null;
    $news = $news ?? null;
    $heading = $activeCategory?->name ?? ($activeTag ? __('blog.tag_title', ['tag' => $activeTag->name]) : __('blog.index_heading'));
    $sub = $activeCategory?->description ?? ($activeTag ? __('blog.tag_meta', ['tag' => $activeTag->name, 'store' => config('store.name')]) : __('blog.index_subheading'));

    // Front page = the unfiltered first page that carries a lead story. Only then
    // do the ticker / lead / section strips render.
    $isFront = $search === '' && ! $activeCategory && ! $activeTag && $posts->currentPage() === 1 && $news && $news['lead'];

    // The main river drops the lead + secondary headlines on the front page so the
    // top of the feed is not shown twice.
    $listing = $posts->getCollection();
    if ($isFront) {
        $usedIds = collect([$news['lead']?->id])->merge($news['secondary']->pluck('id'))->filter()->all();
        $listing = $listing->reject(fn ($p) => in_array($p->id, $usedIds))->values();
    }
@endphp

<x-layouts.store :seo="$seo">

    {{-- ===================== MASTHEAD ===================== --}}
    <section class="relative overflow-hidden border-b border-violet-100/70 bg-gradient-to-b from-violet-50 via-fuchsia-50/40 to-white">
        <div class="store-hero-grid absolute inset-0 opacity-50"></div>
        <span class="pointer-events-none absolute -right-20 -top-24 h-56 w-56 rounded-full bg-fuchsia-300/30 blur-3xl"></span>

        <div class="relative mx-auto max-w-3xl px-4 py-8 text-center sm:py-10 lg:px-8">
            <span class="inline-flex items-center gap-1.5 rounded-full border border-violet-200 bg-white/80 px-3 py-1 text-xs font-semibold text-violet-700 shadow-sm backdrop-blur">
                <span class="store-grad h-1.5 w-1.5 rounded-full"></span>
                {{ __('blog.index_eyebrow') }}
            </span>

            <h1 class="font-display mt-3 text-3xl font-extrabold leading-[1.1] text-zinc-900 sm:text-4xl">
                {{ $heading }}
            </h1>

            @if($sub)
                <p class="mx-auto mt-2 max-w-xl text-sm leading-relaxed text-zinc-600 sm:text-base">{{ $sub }}</p>
            @endif

            <form method="GET" action="{{ $activeCategory ? route('blog.category', $activeCategory->slug) : route('blog.index') }}"
                  class="mx-auto mt-5 flex max-w-lg items-center gap-2 rounded-2xl border border-white/60 bg-white/90 p-1.5 shadow-lg shadow-fuchsia-900/10 ring-1 ring-zinc-900/5 backdrop-blur">
                <div class="flex flex-1 items-center gap-2 pl-3">
                    <flux:icon name="magnifying-glass" class="h-5 w-5 shrink-0 text-zinc-400" />
                    <label for="blog-search" class="sr-only">{{ __('blog.search_placeholder') }}</label>
                    <input id="blog-search" type="search" name="q" value="{{ $search }}" placeholder="{{ __('blog.search_placeholder') }}"
                           class="w-full border-0 bg-transparent py-2 text-base text-zinc-900 placeholder:text-zinc-400 focus:outline-none focus:ring-0 sm:text-sm" />
                </div>
                <button type="submit" class="store-grad store-grad-hover shrink-0 rounded-xl px-5 py-2 text-sm font-semibold text-white">
                    {{ __('blog.search_cta') }}
                </button>
            </form>
        </div>
    </section>

    {{-- ===================== TOPIC RAIL ===================== --}}
    @if($categories->isNotEmpty())
        <nav class="sticky top-16 z-30 border-b border-zinc-100 bg-white/90 backdrop-blur" aria-label="{{ __('blog.categories_title') }}">
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

    {{-- ===================== NEWS FRONT PAGE ===================== --}}
    @if($isFront)
        @php $lead = $news['lead']; $secondary = $news['secondary']; @endphp

        {{-- ---------- TICKER ---------- --}}
        @if($news['ticker']->isNotEmpty())
            <div class="border-b border-zinc-100 bg-white">
                <div class="mx-auto flex max-w-7xl items-center gap-3 px-4 py-2.5 sm:px-6 lg:px-8">
                    <span class="store-grad inline-flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-wide text-white">
                        <flux:icon name="bolt" class="h-3.5 w-3.5" />
                        {{ __('blog.latest_title') }}
                    </span>
                    <div class="flex min-w-0 flex-1 items-center gap-5 overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                        @foreach($news['ticker'] as $t)
                            <a href="{{ route('blog.show', $t->slug) }}" wire:key="tick-{{ $t->id }}"
                               class="group flex shrink-0 items-center gap-2 text-sm text-zinc-600 transition-colors hover:text-violet-700">
                                <span class="h-1 w-1 shrink-0 rounded-full bg-fuchsia-400" aria-hidden="true"></span>
                                <span class="whitespace-nowrap font-medium">{{ str($t->title)->limit(64) }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        {{-- ---------- LEAD + SECONDARY ---------- --}}
        <div class="mx-auto max-w-7xl px-4 pt-10 sm:px-6 lg:px-8">
            <div class="grid gap-6 lg:grid-cols-12">
                @php $leadImg = $lead->featuredImage?->url; @endphp
                <a href="{{ route('blog.show', $lead->slug) }}"
                   @class([
                       'group flex flex-col overflow-hidden rounded-3xl border border-zinc-100 bg-white transition-all duration-300 hover:border-violet-200 hover:shadow-2xl hover:shadow-fuchsia-900/10',
                       'lg:col-span-7' => $secondary->isNotEmpty(),
                       'lg:col-span-12' => $secondary->isEmpty(),
                   ])>
                    <div class="relative aspect-[16/9] overflow-hidden bg-zinc-50">
                        @if($leadImg)
                            <img src="{{ $leadImg }}" alt="{{ $lead->featuredImage?->alt_text ?: $lead->title }}"
                                 class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-[1.03]" fetchpriority="high">
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

                    <div class="p-6 sm:p-8">
                        @if($lead->category)
                            <span class="text-xs font-bold uppercase tracking-wide" style="color: {{ $lead->category->color }}">
                                {{ $lead->category->name }}
                            </span>
                        @endif
                        <h2 class="font-display mt-2 text-2xl font-extrabold leading-tight text-zinc-900 transition-colors group-hover:text-violet-700 sm:text-3xl">
                            {{ $lead->title }}
                        </h2>
                        @if($lead->excerpt)
                            <p class="mt-3 line-clamp-2 text-sm leading-relaxed text-zinc-600 sm:text-base">{{ $lead->excerpt }}</p>
                        @endif
                        <div class="mt-5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-zinc-500">
                            <span class="font-semibold text-zinc-700">{{ $lead->author_name }}</span>
                            <span class="h-1 w-1 rounded-full bg-zinc-300" aria-hidden="true"></span>
                            <time datetime="{{ $lead->published_at?->toDateString() }}">{{ $lead->published_at?->translatedFormat('j M Y') }}</time>
                            <span class="h-1 w-1 rounded-full bg-zinc-300" aria-hidden="true"></span>
                            <span class="inline-flex items-center gap-1">
                                <flux:icon name="clock" class="h-3.5 w-3.5" />
                                {{ __('blog.min_read', ['minutes' => $lead->reading_time]) }}
                            </span>
                        </div>
                    </div>
                </a>

                @if($secondary->isNotEmpty())
                    <div class="flex flex-col divide-y divide-zinc-100 lg:col-span-5">
                        @foreach($secondary as $s)
                            <a href="{{ route('blog.show', $s->slug) }}" wire:key="sec-{{ $s->id }}"
                               class="group flex gap-4 py-4 first:pt-0 last:pb-0">
                                <div class="relative aspect-[4/3] w-28 shrink-0 overflow-hidden rounded-xl bg-zinc-100">
                                    @if($s->featuredImage?->url)
                                        <img src="{{ $s->featuredImage->url }}" alt="{{ $s->featuredImage->alt_text ?: $s->title }}"
                                             loading="lazy" decoding="async"
                                             class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105">
                                    @else
                                        <div class="store-grad flex h-full w-full items-center justify-center">
                                            <flux:icon name="newspaper" class="h-5 w-5 text-white/90" />
                                        </div>
                                    @endif
                                </div>
                                <div class="min-w-0 flex-1">
                                    @if($s->category)
                                        <span class="text-[11px] font-bold uppercase tracking-wide" style="color: {{ $s->category->color }}">{{ $s->category->name }}</span>
                                    @endif
                                    <h3 class="font-display mt-1 line-clamp-3 text-sm font-bold leading-snug text-zinc-900 transition-colors group-hover:text-violet-700 sm:text-base">
                                        {{ $s->title }}
                                    </h3>
                                    <div class="mt-1.5 flex items-center gap-1.5 text-xs text-zinc-400">
                                        <flux:icon name="calendar" class="h-3.5 w-3.5" />
                                        <time datetime="{{ $s->published_at?->toDateString() }}">{{ $s->published_at?->translatedFormat('j M Y') }}</time>
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- ---------- CATEGORY SECTIONS ---------- --}}
        @foreach($news['sections'] as $section)
            <section class="mx-auto max-w-7xl px-4 pt-12 sm:px-6 lg:px-8" wire:key="sec-cat-{{ $section['category']->id }}">
                <div class="mb-6 flex items-center gap-3">
                    <span class="h-5 w-1.5 rounded-full" style="background-color: {{ $section['category']->color }}"></span>
                    <h2 class="font-display text-lg font-extrabold text-zinc-900 sm:text-xl">{{ $section['category']->name }}</h2>
                    <span class="h-px flex-1 bg-zinc-100"></span>
                    <a href="{{ route('blog.category', $section['category']->slug) }}"
                       class="inline-flex shrink-0 items-center gap-1 text-sm font-semibold text-violet-700 transition-colors hover:text-fuchsia-700">
                        {{ __('blog.section_view_all') }}
                        <flux:icon name="arrow-right" class="h-4 w-4" />
                    </a>
                </div>
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach($section['posts'] as $sp)
                        <x-store.post-card :post="$sp" :key="'cat-'.$section['category']->id.'-'.$sp->id" />
                    @endforeach
                </div>
            </section>
        @endforeach
    @endif

    {{-- ===================== MAIN LISTING + SIDEBAR ===================== --}}
    <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
        <div class="grid gap-10 lg:grid-cols-12">
            {{-- ---------- ARTICLE RIVER ---------- --}}
            <div class="lg:col-span-8">
                <div class="mb-6 flex items-center gap-3">
                    @if($isFront)
                        <flux:icon name="clock" class="h-5 w-5 shrink-0 text-violet-600" />
                    @endif
                    <h2 class="font-display text-lg font-extrabold text-zinc-900 sm:text-xl">
                        @if($search !== '')
                            {{ trans_choice('blog.search_results', $posts->total(), ['count' => $posts->total()]) }}
                            <span class="font-extrabold text-zinc-900">“{{ $search }}”</span>
                        @elseif($isFront)
                            {{ __('blog.latest_title') }}
                        @else
                            {{ $heading }}
                        @endif
                    </h2>
                    <span class="h-px flex-1 bg-zinc-100"></span>
                </div>

                @if($listing->isEmpty())
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
                    <div class="divide-y divide-zinc-100">
                        @foreach($listing as $post)
                            <x-store.article-list-item :post="$post" :key="'river-'.$post->id" />
                        @endforeach
                    </div>

                    <div class="mt-10">
                        {{ $posts->links() }}
                    </div>
                @endif
            </div>

            {{-- ---------- SIDEBAR ---------- --}}
            <aside class="space-y-6 lg:col-span-4">
                <div class="lg:sticky lg:top-32 lg:space-y-6">
                    {{-- Trending --}}
                    @if($popular->isNotEmpty())
                        <section class="rounded-2xl border border-zinc-100 bg-white p-5">
                            <h2 class="font-display flex items-center gap-2 text-sm font-bold text-zinc-900">
                                <flux:icon name="fire" class="h-4 w-4 text-rose-500" />
                                {{ __('blog.popular_title') }}
                            </h2>
                            <ol class="mt-4 space-y-3">
                                @foreach($popular as $i => $pop)
                                    <li wire:key="pop-{{ $pop->id }}">
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
                                    <li wire:key="side-cat-{{ $cat->id }}">
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
