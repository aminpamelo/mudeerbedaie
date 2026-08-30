<x-layouts.store :seo="$seo">

    @php
        $storeName = config('store.name');
        // Slide 0 is the built-in brand panel; campaign banners stack after it.
        // Carousel chrome only appears once there is something to move between.
        $slideCount = 1 + $banners->count();
    @endphp

    {{-- ===================== HERO (brand slide + campaign slides) ===================== --}}
    <section
        class="relative overflow-hidden border-b border-violet-100/70 bg-gradient-to-b from-violet-50 via-fuchsia-50/60 to-white"
        x-data="{
            active: 0,
            count: {{ $slideCount }},
            timer: null,
            touchX: null,
            reduced: window.matchMedia('(prefers-reduced-motion: reduce)').matches,
            go(index) { this.active = (index + this.count) % this.count; this.restart(); },
            next() { this.go(this.active + 1); },
            prev() { this.go(this.active - 1); },
            start() {
                if (this.count < 2 || this.reduced || this.timer) return;
                this.timer = setInterval(() => { this.active = (this.active + 1) % this.count; }, 7000);
            },
            stop() { clearInterval(this.timer); this.timer = null; },
            restart() { this.stop(); this.start(); },
            swipe(endX) {
                if (this.touchX === null) return;
                const dx = endX - this.touchX;
                this.touchX = null;
                if (Math.abs(dx) > 55) { dx < 0 ? this.next() : this.prev(); }
            },
        }"
        x-init="start()"
        @mouseenter="stop()"
        @mouseleave="start()"
        @focusin="stop()"
        @focusout="start()"
        @keydown.window.arrow-right="if (count > 1) next()"
        @keydown.window.arrow-left="if (count > 1) prev()"
        @touchstart.passive="touchX = $event.changedTouches[0].clientX"
        @touchend.passive="swipe($event.changedTouches[0].clientX)"
        role="region"
        aria-roledescription="carousel"
        aria-label="{{ $storeName }}"
    >
        <div class="store-hero-grid absolute inset-0 opacity-70"></div>
        <span class="store-drift pointer-events-none absolute -right-24 -top-24 h-72 w-72 rounded-full bg-fuchsia-300/40 blur-3xl"></span>
        <span class="store-drift-slow pointer-events-none absolute -left-24 top-20 h-72 w-72 rounded-full bg-violet-300/40 blur-3xl"></span>
        <span class="store-drift pointer-events-none absolute bottom-0 right-1/3 h-56 w-56 rounded-full bg-rose-300/30 blur-3xl"></span>

        {{-- Slides share one grid cell so the section height tracks the tallest
             visible slide and the crossfade can overlap without absolute
             positioning. --}}
        <div class="relative mx-auto grid max-w-7xl px-4 py-14 sm:px-6 sm:py-16 lg:px-8 lg:py-20">

            {{-- ---------- Slide 0: brand ---------- --}}
            <div
                class="col-start-1 row-start-1"
                x-show="active === 0"
                x-transition:enter="transition ease-out duration-500"
                x-transition:enter-start="opacity-0 translate-y-3"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                role="group"
                aria-roledescription="slide"
            >
                <div class="grid items-center gap-10 lg:grid-cols-12 lg:gap-8">
                    <div class="text-center lg:col-span-7 lg:text-left">
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-violet-200 bg-white/80 px-3 py-1 text-xs font-semibold text-violet-700 shadow-sm backdrop-blur">
                            <span class="store-grad h-1.5 w-1.5 rounded-full"></span>
                            {{ __('store.hero_brand_eyebrow', ['store' => $storeName]) }}
                        </span>

                        <h1 class="font-display mt-5">
                            <span class="store-grad-text block text-5xl font-extrabold leading-[1.05] sm:text-6xl lg:text-7xl">
                                {{ __('store.hero_brand_title') }}
                            </span>
                            <span class="mt-3 block text-xl font-bold leading-snug text-zinc-900 sm:text-2xl lg:text-3xl">
                                {{ __('store.hero_title') }}
                            </span>
                        </h1>

                        <p class="mx-auto mt-4 max-w-xl text-base leading-relaxed text-zinc-600 sm:text-lg lg:mx-0">
                            {{ __('store.hero_subtitle') }}
                        </p>

                        <form method="GET" action="{{ route('shop') }}" class="mx-auto mt-7 flex max-w-xl items-center gap-2 rounded-2xl border border-white/60 bg-white/90 p-1.5 shadow-xl shadow-fuchsia-900/10 ring-1 ring-zinc-900/5 backdrop-blur lg:mx-0">
                            <div class="flex flex-1 items-center gap-2 pl-3">
                                <flux:icon name="magnifying-glass" class="h-5 w-5 shrink-0 text-zinc-400" />
                                <input type="text" name="q" placeholder="{{ __('store.hero_search_ph') }}" aria-label="{{ __('store.hero_search_ph') }}" class="w-full border-0 bg-transparent py-2.5 text-sm text-zinc-900 placeholder:text-zinc-400 focus:outline-none focus:ring-0" />
                            </div>
                            <button type="submit" class="store-grad store-grad-hover shrink-0 rounded-xl px-5 py-2.5 text-sm font-semibold text-white">
                                {{ __('store.hero_cta_shop') }}
                            </button>
                        </form>

                        <div class="mt-5 flex flex-wrap items-center justify-center gap-3 lg:justify-start">
                            <a href="{{ route('shop') }}" class="inline-flex items-center gap-1.5 rounded-xl border border-violet-200 bg-white px-4 py-2.5 text-sm font-semibold text-violet-700 transition-colors hover:border-violet-300 hover:bg-violet-50">
                                {{ __('store.categories_all') }} <flux:icon name="arrow-right" class="h-4 w-4" />
                            </a>
                        </div>
                    </div>

                    {{-- Cover collage, built from the actual best-selling titles so
                         it always shows real stock and needs no separate artwork. --}}
                    @if($heroCovers->isNotEmpty())
                        <div class="lg:col-span-5">
                            <div class="store-float relative mx-auto aspect-square w-full max-w-sm">
                                @if(isset($heroCovers[2]))
                                    <img src="{{ $heroCovers[2] }}" alt="" aria-hidden="true" loading="lazy"
                                         class="absolute left-0 top-10 aspect-square w-[52%] -rotate-12 rounded-2xl object-cover shadow-2xl shadow-violet-900/20 ring-1 ring-zinc-900/5" />
                                @endif
                                @if(isset($heroCovers[1]))
                                    <img src="{{ $heroCovers[1] }}" alt="" aria-hidden="true" loading="lazy"
                                         class="absolute right-0 top-4 aspect-square w-[52%] rotate-[10deg] rounded-2xl object-cover shadow-2xl shadow-fuchsia-900/20 ring-1 ring-zinc-900/5" />
                                @endif
                                <img src="{{ $heroCovers[0] }}" alt="" aria-hidden="true" fetchpriority="high"
                                     class="absolute left-1/2 top-20 aspect-square w-[60%] -translate-x-1/2 rounded-2xl object-cover shadow-2xl shadow-rose-900/25 ring-1 ring-zinc-900/5" />
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- ---------- Slides 1+: admin-managed campaigns ---------- --}}
            @foreach($banners as $index => $banner)
                <div
                    class="col-start-1 row-start-1"
                    x-show="active === {{ $index + 1 }}"
                    x-cloak
                    x-transition:enter="transition ease-out duration-500"
                    x-transition:enter-start="opacity-0 translate-y-3"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-200"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    role="group"
                    aria-roledescription="slide"
                    wire:key="hero-banner-{{ $banner->id }}"
                >
                    <div class="relative overflow-hidden rounded-3xl shadow-xl shadow-fuchsia-900/10">
                        @if($banner->image_url)
                            <img src="{{ $banner->image_url }}" alt="{{ $banner->title }}" loading="lazy" class="absolute inset-0 h-full w-full object-cover" />
                            {{-- Scrim keeps the copy readable over any uploaded artwork. --}}
                            <div class="absolute inset-0 bg-gradient-to-r from-zinc-950/85 via-zinc-950/60 to-zinc-950/20"></div>
                        @else
                            <div class="store-grad absolute inset-0"></div>
                        @endif

                        <div class="relative flex min-h-[380px] flex-col justify-center px-6 py-14 sm:min-h-[420px] sm:px-12">
                            <div class="max-w-xl">
                                @if($banner->eyebrow)
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold text-white backdrop-blur">
                                        {{ $banner->eyebrow }}
                                    </span>
                                @endif
                                <h2 class="font-display mt-4 text-3xl font-extrabold leading-tight text-white sm:text-4xl lg:text-5xl">
                                    {{ $banner->title }}
                                </h2>
                                @if($banner->subtitle)
                                    <p class="mt-4 text-base leading-relaxed text-white/85 sm:text-lg">{{ $banner->subtitle }}</p>
                                @endif
                                @if($banner->cta_text && $banner->cta_url)
                                    <a href="{{ $banner->cta_url }}" class="mt-7 inline-flex items-center gap-2 rounded-xl bg-white px-6 py-3 text-sm font-bold text-violet-700 shadow-lg transition-transform hover:-translate-y-0.5">
                                        {{ $banner->cta_text }} <flux:icon name="arrow-right" class="h-4 w-4" />
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- ---------- Carousel controls (only with 2+ slides) ---------- --}}
        @if($slideCount > 1)
            <div class="relative mx-auto flex max-w-7xl items-center justify-center gap-3 px-4 pb-8 sm:px-6 lg:px-8">
                <button type="button" @click="prev()" aria-label="{{ __('store.hero_slide_prev') }}"
                        class="grid h-9 w-9 place-items-center rounded-full border border-violet-200 bg-white/80 text-violet-700 backdrop-blur transition-colors hover:bg-violet-50">
                    <flux:icon name="chevron-left" class="h-4 w-4" />
                </button>

                <div class="flex items-center gap-2">
                    @for($i = 0; $i < $slideCount; $i++)
                        <button type="button" @click="go({{ $i }})"
                                :class="active === {{ $i }} ? 'w-7 bg-gradient-to-r from-violet-600 via-fuchsia-600 to-rose-500' : 'w-2 bg-violet-200 hover:bg-violet-300'"
                                :aria-current="active === {{ $i }}"
                                class="h-2 rounded-full transition-all duration-300"
                                aria-label="{{ __('store.hero_slide_goto', ['n' => $i + 1]) }}"></button>
                    @endfor
                </div>

                <button type="button" @click="next()" aria-label="{{ __('store.hero_slide_next') }}"
                        class="grid h-9 w-9 place-items-center rounded-full border border-violet-200 bg-white/80 text-violet-700 backdrop-blur transition-colors hover:bg-violet-50">
                    <flux:icon name="chevron-right" class="h-4 w-4" />
                </button>
            </div>
        @endif
    </section>

    {{-- ===================== BENEFIT TICKER ===================== --}}
    @php
        $tickerItems = [
            ['icon' => 'truck', 'text' => __('store.why_1_title')],
            ['icon' => 'shield-check', 'text' => __('store.why_2_title')],
            ['icon' => 'book-open', 'text' => __('store.why_3_title', ['store' => $storeName])],
            ['icon' => 'chat-bubble-left-right', 'text' => __('store.why_4_title')],
        ];
    @endphp
    <div class="store-grad store-marquee-wrap overflow-hidden py-3" role="presentation">
        {{-- The track is rendered twice; the keyframe shifts it by exactly half
             its width, so the seam is never visible. --}}
        <div class="store-marquee flex w-max items-center gap-10 pr-10">
            @for($pass = 0; $pass < 2; $pass++)
                @foreach($tickerItems as $item)
                    <span class="flex shrink-0 items-center gap-2 text-sm font-semibold text-white/90" @if($pass === 1) aria-hidden="true" @endif>
                        <flux:icon :name="$item['icon']" class="h-4 w-4 shrink-0 text-white/70" />
                        {{ $item['text'] }}
                    </span>
                    <span class="h-1 w-1 shrink-0 rounded-full bg-white/40" aria-hidden="true"></span>
                @endforeach
            @endfor
        </div>
    </div>

    {{-- ===================== TRUST STATS ===================== --}}
    <section class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
        <div class="reveal grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
            <div class="rounded-2xl border border-zinc-100 bg-white p-5 text-center shadow-sm transition-all duration-300 hover:-translate-y-1 hover:border-violet-200 hover:shadow-lg hover:shadow-fuchsia-900/5">
                <span class="mx-auto grid h-11 w-11 place-items-center rounded-xl bg-violet-50 text-violet-600">
                    <flux:icon name="shopping-bag" class="h-5 w-5" />
                </span>
                <div class="font-display mt-3 text-2xl font-extrabold text-zinc-900 sm:text-3xl">
                    <x-store.counter :value="$orderCount" suffix="+" />
                </div>
                <p class="mt-1 text-xs font-medium text-zinc-500 sm:text-sm">{{ __('store.trust_orders') }}</p>
            </div>

            <div class="rounded-2xl border border-zinc-100 bg-white p-5 text-center shadow-sm transition-all duration-300 hover:-translate-y-1 hover:border-violet-200 hover:shadow-lg hover:shadow-fuchsia-900/5" style="--reveal-delay: 80ms">
                <span class="mx-auto grid h-11 w-11 place-items-center rounded-xl bg-fuchsia-50 text-fuchsia-600">
                    <flux:icon name="book-open" class="h-5 w-5" />
                </span>
                <div class="font-display mt-3 text-2xl font-extrabold text-zinc-900 sm:text-3xl">
                    <x-store.counter :value="$productCount" />
                </div>
                <p class="mt-1 text-xs font-medium text-zinc-500 sm:text-sm">{{ __('store.trust_titles') }}</p>
            </div>

            <div class="rounded-2xl border border-zinc-100 bg-white p-5 text-center shadow-sm transition-all duration-300 hover:-translate-y-1 hover:border-violet-200 hover:shadow-lg hover:shadow-fuchsia-900/5">
                <span class="mx-auto grid h-11 w-11 place-items-center rounded-xl bg-emerald-50 text-emerald-600">
                    <flux:icon name="shield-check" class="h-5 w-5" />
                </span>
                <div class="font-display mt-3 text-base font-extrabold text-zinc-900 sm:text-lg">{{ __('store.trust_secure_title') }}</div>
                <p class="mt-1 text-xs font-medium text-zinc-500 sm:text-sm">{{ __('store.trust_secure_text') }}</p>
            </div>

            <div class="rounded-2xl border border-zinc-100 bg-white p-5 text-center shadow-sm transition-all duration-300 hover:-translate-y-1 hover:border-violet-200 hover:shadow-lg hover:shadow-fuchsia-900/5">
                <span class="mx-auto grid h-11 w-11 place-items-center rounded-xl bg-rose-50 text-rose-500">
                    <flux:icon name="truck" class="h-5 w-5" />
                </span>
                <div class="font-display mt-3 text-base font-extrabold text-zinc-900 sm:text-lg">{{ __('store.trust_delivery_title') }}</div>
                <p class="mt-1 text-xs font-medium text-zinc-500 sm:text-sm">{{ __('store.trust_delivery_text') }}</p>
            </div>
        </div>
    </section>

    {{-- ===================== CATEGORIES ===================== --}}
    @if($categories->isNotEmpty())
        <section class="mx-auto max-w-7xl px-4 pb-14 sm:px-6 lg:px-8">
            <div class="reveal flex items-end justify-between gap-4">
                <div>
                    <h2 class="font-display text-2xl font-extrabold text-zinc-900 sm:text-3xl">{{ __('store.categories_title') }}</h2>
                    <p class="mt-1.5 text-sm text-zinc-500">{{ __('store.categories_subtitle') }}</p>
                </div>
                <a href="{{ route('shop') }}" class="hidden shrink-0 items-center gap-1 text-sm font-semibold text-violet-700 hover:text-fuchsia-700 sm:inline-flex">
                    {{ __('store.categories_all') }} <flux:icon name="arrow-right" class="h-4 w-4" />
                </a>
            </div>

            <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                @foreach($categories as $index => $category)
                    @php
                        // Prefer the category's own artwork; fall back to a cover
                        // from the first product inside it.
                        $thumb = $category->image ?: ($categoryThumbs[$category->id] ?? null);
                    @endphp
                    <a href="{{ route('shop', ['category' => $category->id]) }}"
                       wire:key="cat-{{ $category->id }}"
                       style="--reveal-delay: {{ min($index, 5) * 60 }}ms"
                       class="reveal group relative overflow-hidden rounded-2xl border border-zinc-100 bg-white transition-all duration-300 hover:-translate-y-1 hover:border-violet-200 hover:shadow-xl hover:shadow-fuchsia-900/10">
                        <div class="relative aspect-[5/3] overflow-hidden bg-gradient-to-br from-violet-50 via-fuchsia-50 to-rose-50">
                            @if($thumb)
                                <img src="{{ $thumb }}" alt="" aria-hidden="true" loading="lazy"
                                     class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-110"
                                     onerror="this.style.display='none'" />
                            @else
                                <span class="grid h-full w-full place-items-center">
                                    <flux:icon name="tag" class="h-8 w-8 text-violet-200" />
                                </span>
                            @endif
                            <div class="absolute inset-0 bg-gradient-to-t from-zinc-950/70 via-zinc-950/10 to-transparent"></div>
                            <div class="absolute inset-x-0 bottom-0 p-3">
                                <span class="font-display block truncate text-sm font-bold text-white">{{ $category->name }}</span>
                                <span class="text-[11px] font-medium text-white/70 tabular-nums">
                                    {{ trans_choice('store.category_count', $category->active_products_count, ['count' => $category->active_products_count]) }}
                                </span>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- ===================== FEATURED PRODUCTS ===================== --}}
    <section class="mx-auto max-w-7xl px-4 pb-14 sm:px-6 lg:px-8">
        <div class="reveal flex items-end justify-between gap-4">
            <div>
                <h2 class="font-display text-2xl font-extrabold text-zinc-900 sm:text-3xl">{{ __('store.featured_title') }}</h2>
                <p class="mt-1.5 text-sm text-zinc-500">{{ __('store.featured_subtitle') }}</p>
            </div>
            <a href="{{ route('shop') }}" class="inline-flex shrink-0 items-center gap-1 text-sm font-semibold text-violet-700 hover:text-fuchsia-700">
                {{ __('store.view_all') }} <flux:icon name="arrow-right" class="h-4 w-4" />
            </a>
        </div>

        @if($featured->isNotEmpty())
            <div class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                @foreach($featured as $index => $product)
                    <div class="reveal" wire:key="feat-{{ $product->id }}" style="--reveal-delay: {{ min($index, 5) * 60 }}ms">
                        <x-store.product-card :product="$product" :bestseller="in_array($product->id, $bestsellerIds, true)" />
                    </div>
                @endforeach
            </div>
        @else
            <div class="mt-6 grid place-items-center rounded-2xl border border-dashed border-zinc-200 py-16 text-sm text-zinc-400">
                {{ __('store.no_products') }}
            </div>
        @endif
    </section>

    {{-- ===================== PACKAGE DEALS (hidden) ===================== --}}
    @if(false && $packages->isNotEmpty())
        <section id="packages" class="relative overflow-hidden bg-zinc-50 py-16 scroll-mt-20">
            <span class="store-drift pointer-events-none absolute -left-20 top-10 h-64 w-64 rounded-full bg-violet-200/40 blur-3xl"></span>
            <span class="store-drift-slow pointer-events-none absolute -right-20 bottom-10 h-64 w-64 rounded-full bg-rose-200/40 blur-3xl"></span>
            <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="reveal text-center">
                    <h2 class="font-display text-2xl font-extrabold text-zinc-900 sm:text-3xl">{{ __('store.packages_title') }}</h2>
                    <p class="mx-auto mt-1.5 max-w-xl text-sm text-zinc-500">{{ __('store.packages_subtitle') }}</p>
                </div>

                <div class="mt-8 grid grid-cols-1 gap-5 md:grid-cols-3">
                    @foreach($packages as $index => $package)
                        @php $savingsPct = $package->getSavingsPercentage(); @endphp
                        <div class="reveal flex flex-col overflow-hidden rounded-3xl border border-zinc-100 bg-white shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-xl hover:shadow-fuchsia-900/10"
                             wire:key="pkg-{{ $package->id }}" style="--reveal-delay: {{ $index * 90 }}ms">
                            <a href="{{ route('storefront.package', $package->slug) }}" class="store-grad relative flex aspect-[16/10] items-center justify-center p-6 text-center">
                                <span class="absolute left-4 top-4 rounded-full bg-white/20 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-white backdrop-blur">{{ __('store.package_badge') }}</span>
                                @if($savingsPct > 0)
                                    <span class="absolute right-4 top-4 rounded-full bg-amber-300 px-2.5 py-1 text-[11px] font-extrabold text-amber-950 tabular-nums">{{ __('store.package_save_pct', ['pct' => $savingsPct]) }}</span>
                                @endif
                                <flux:icon name="gift" class="h-14 w-14 text-white/90" />
                            </a>
                            <div class="flex flex-1 flex-col p-5">
                                <h3 class="font-display line-clamp-2 text-base font-bold text-zinc-900">
                                    <a href="{{ route('storefront.package', $package->slug) }}" class="transition-colors hover:text-violet-700">{{ $package->name }}</a>
                                </h3>
                                @if($package->short_description)
                                    <p class="mt-1.5 line-clamp-2 text-sm text-zinc-500">{{ $package->short_description }}</p>
                                @endif
                                <div class="mt-2 text-xs font-medium text-zinc-400">{{ __('store.package_items', ['count' => $package->items_count]) }}</div>

                                <div class="mt-auto pt-4">
                                    <div class="flex items-end gap-2">
                                        <span class="font-display text-2xl font-extrabold tabular-nums text-zinc-900">{{ $package->formatted_price }}</span>
                                        @if($package->original_price && $package->original_price > $package->price)
                                            <span class="pb-1 text-sm text-zinc-400 line-through tabular-nums">{{ $package->formatted_original_price }}</span>
                                        @endif
                                    </div>
                                    @if($package->calculateSavings() > 0)
                                        <div class="mt-1 inline-flex items-center gap-1 rounded-md bg-fuchsia-50 px-2 py-0.5 text-xs font-semibold text-fuchsia-700">
                                            <flux:icon name="sparkles" class="h-3.5 w-3.5" /> {{ __('store.package_save', ['amount' => $package->formatted_savings]) }}
                                        </div>
                                    @endif

                                    <div class="mt-4 space-y-2">
                                        <livewire:store.package-cart :package="$package" :compact="true" :key="'pkgcard-'.$package->id" />
                                        <a href="{{ route('storefront.package', $package->slug) }}" class="flex w-full items-center justify-center gap-1 rounded-xl border border-zinc-200 px-4 py-2 text-sm font-semibold text-zinc-600 transition-colors hover:border-violet-300 hover:text-violet-700">
                                            {{ __('store.package_details') }}
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- ===================== LMS / COURSES (hidden) ===================== --}}
    @if(false && $courses->isNotEmpty())
        <section class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <h2 class="font-display text-2xl font-extrabold text-zinc-900 sm:text-3xl">{{ __('store.lms_title') }}</h2>
                    <p class="mt-1.5 max-w-xl text-sm text-zinc-500">{{ __('store.lms_subtitle') }}</p>
                </div>
                <a href="{{ route('storefront.courses') }}" class="inline-flex shrink-0 items-center gap-1 text-sm font-semibold text-violet-700 hover:text-fuchsia-700">
                    {{ __('store.lms_all') }} <flux:icon name="arrow-right" class="h-4 w-4" />
                </a>
            </div>
            <div class="mt-6 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($courses as $course)
                    <x-store.course-card :course="$course" />
                @endforeach
            </div>
        </section>
    @endif

    {{-- ===================== OUR STORY ===================== --}}
    <section class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
        <div class="grid items-center gap-10 lg:grid-cols-12 lg:gap-14">
            <div class="reveal lg:col-span-6">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-violet-50 px-3 py-1 text-xs font-semibold text-violet-700">
                    <span class="store-grad h-1.5 w-1.5 rounded-full"></span>
                    {{ __('store.story_eyebrow') }}
                </span>
                <h2 class="font-display mt-4 text-2xl font-extrabold leading-tight text-zinc-900 sm:text-3xl lg:text-4xl">
                    {{ __('store.story_title') }}
                </h2>
                <p class="font-display mt-4 text-lg font-semibold leading-snug text-violet-700 sm:text-xl">
                    {{ __('store.story_lead') }}
                </p>
                <div class="mt-5 space-y-4 text-base leading-relaxed text-zinc-600">
                    <p>{{ __('store.story_body_1', ['store' => $storeName]) }}</p>
                    <p>{{ __('store.story_body_2') }}</p>
                </div>
                <a href="{{ route('blog.index') }}" class="mt-7 inline-flex items-center gap-2 rounded-xl border border-violet-200 bg-white px-5 py-2.5 text-sm font-semibold text-violet-700 transition-colors hover:border-violet-300 hover:bg-violet-50">
                    {{ __('store.story_cta') }} <flux:icon name="arrow-right" class="h-4 w-4" />
                </a>
            </div>

            <div class="lg:col-span-6">
                @php
                    $storyPoints = [
                        ['icon' => 'academic-cap', 'title' => 'story_point_1_title', 'text' => 'story_point_1_text'],
                        ['icon' => 'users', 'title' => 'story_point_2_title', 'text' => 'story_point_2_text'],
                        ['icon' => 'truck', 'title' => 'story_point_3_title', 'text' => 'story_point_3_text'],
                    ];
                @endphp
                <div class="space-y-4">
                    @foreach($storyPoints as $index => $point)
                        <div class="reveal flex gap-4 rounded-2xl border border-zinc-100 bg-white p-5 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:border-violet-200 hover:shadow-lg hover:shadow-fuchsia-900/5"
                             style="--reveal-delay: {{ $index * 100 }}ms">
                            <span class="store-grad grid h-11 w-11 shrink-0 place-items-center rounded-xl text-white shadow-lg shadow-fuchsia-500/20">
                                <flux:icon :name="$point['icon']" class="h-5 w-5" />
                            </span>
                            <div class="min-w-0">
                                <h3 class="font-display text-sm font-bold text-zinc-900">{{ __('store.' . $point['title']) }}</h3>
                                <p class="mt-1 text-sm leading-relaxed text-zinc-500">{{ __('store.' . $point['text']) }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- ===================== TESTIMONIALS ===================== --}}
    {{-- Rendered only when real, admin-entered testimonials exist — the store
         never shows placeholder reviews. --}}
    @if($testimonials->isNotEmpty())
        <section class="relative overflow-hidden bg-zinc-50 py-16">
            <span class="store-drift pointer-events-none absolute -right-24 top-0 h-64 w-64 rounded-full bg-fuchsia-200/40 blur-3xl"></span>
            <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="reveal text-center">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1 text-xs font-semibold text-violet-700 shadow-sm">
                        <span class="store-grad h-1.5 w-1.5 rounded-full"></span>
                        {{ __('store.testi_eyebrow') }}
                    </span>
                    <h2 class="font-display mt-3 text-2xl font-extrabold text-zinc-900 sm:text-3xl">{{ __('store.testi_title') }}</h2>
                    <p class="mx-auto mt-1.5 max-w-xl text-sm text-zinc-500">{{ __('store.testi_subtitle') }}</p>
                </div>

                <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($testimonials as $index => $testimonial)
                        <figure class="reveal flex flex-col rounded-2xl border border-zinc-100 bg-white p-6 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-lg hover:shadow-fuchsia-900/5"
                                wire:key="testi-{{ $testimonial->id }}" style="--reveal-delay: {{ min($index, 5) * 80 }}ms">
                            @if($testimonial->rating)
                                <div class="flex items-center gap-0.5 text-amber-400" role="img" aria-label="{{ $testimonial->rating }}/5">
                                    @for($star = 1; $star <= 5; $star++)
                                        <flux:icon name="star" variant="{{ $star <= $testimonial->rating ? 'solid' : 'outline' }}" class="h-4 w-4 {{ $star <= $testimonial->rating ? '' : 'text-zinc-200' }}" />
                                    @endfor
                                </div>
                            @endif
                            <blockquote class="mt-3 flex-1 text-sm leading-relaxed text-zinc-600">“{{ $testimonial->quote }}”</blockquote>
                            <figcaption class="mt-5 flex items-center gap-3 border-t border-zinc-100 pt-4">
                                @if($testimonial->photo_url)
                                    <img src="{{ $testimonial->photo_url }}" alt="" aria-hidden="true" loading="lazy" class="h-10 w-10 shrink-0 rounded-full object-cover ring-1 ring-zinc-900/5" />
                                @else
                                    <span class="store-grad grid h-10 w-10 shrink-0 place-items-center rounded-full text-sm font-bold text-white">{{ $testimonial->initial }}</span>
                                @endif
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-semibold text-zinc-900">{{ $testimonial->author_name }}</span>
                                    @if($testimonial->author_title)
                                        <span class="block truncate text-xs text-zinc-400">{{ $testimonial->author_title }}</span>
                                    @endif
                                </span>
                            </figcaption>
                        </figure>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- ===================== WHY US ===================== --}}
    <section class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
        <div class="reveal text-center">
            <h2 class="font-display text-2xl font-extrabold text-zinc-900 sm:text-3xl">{{ __('store.why_title') }}</h2>
            <p class="mx-auto mt-1.5 max-w-xl text-sm text-zinc-500">{{ __('store.why_subtitle') }}</p>
        </div>
        @php
            $whys = [
                ['icon' => 'truck', 'title' => __('store.why_1_title'), 'text' => __('store.why_1_text')],
                ['icon' => 'shield-check', 'title' => __('store.why_2_title'), 'text' => __('store.why_2_text')],
                ['icon' => 'book-open', 'title' => __('store.why_3_title', ['store' => $storeName]), 'text' => __('store.why_3_text', ['store' => $storeName])],
                ['icon' => 'chat-bubble-left-right', 'title' => __('store.why_4_title'), 'text' => __('store.why_4_text')],
            ];
        @endphp
        <div class="mt-8 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
            @foreach($whys as $index => $why)
                <div class="reveal rounded-2xl border border-zinc-100 bg-white p-6 text-center transition-all duration-300 hover:-translate-y-1 hover:border-violet-200 hover:shadow-lg hover:shadow-fuchsia-900/5"
                     style="--reveal-delay: {{ $index * 80 }}ms">
                    <span class="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-violet-50 text-violet-600">
                        <flux:icon :name="$why['icon']" class="h-6 w-6" />
                    </span>
                    <h3 class="font-display mt-4 text-sm font-bold text-zinc-900">{{ $why['title'] }}</h3>
                    <p class="mt-1.5 text-sm leading-relaxed text-zinc-500">{{ $why['text'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- ===================== FROM THE JOURNAL ===================== --}}
    @if($posts->isNotEmpty())
        <section class="mx-auto max-w-7xl px-4 pb-14 sm:px-6 lg:px-8">
            <div class="reveal flex items-end justify-between gap-4">
                <div>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-violet-50 px-3 py-1 text-xs font-semibold text-violet-700">
                        <span class="store-grad h-1.5 w-1.5 rounded-full"></span>
                        {{ __('blog.home_section_eyebrow') }}
                    </span>
                    <h2 class="font-display mt-3 text-2xl font-extrabold text-zinc-900 sm:text-3xl">{{ __('blog.home_section_title') }}</h2>
                    <p class="mt-1.5 text-sm text-zinc-500">{{ __('blog.home_section_subtitle') }}</p>
                </div>
                <a href="{{ route('blog.index') }}" class="hidden shrink-0 items-center gap-1 text-sm font-semibold text-violet-700 hover:text-fuchsia-700 sm:inline-flex">
                    {{ __('blog.home_section_cta') }} <flux:icon name="arrow-right" class="h-4 w-4" />
                </a>
            </div>

            <div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($posts as $index => $post)
                    <div class="reveal" wire:key="home-post-{{ $post->id }}" style="--reveal-delay: {{ min($index, 3) * 80 }}ms">
                        <x-store.post-card :post="$post" />
                    </div>
                @endforeach
            </div>

            <a href="{{ route('blog.index') }}" class="mt-6 inline-flex items-center gap-1 text-sm font-semibold text-violet-700 hover:text-fuchsia-700 sm:hidden">
                {{ __('blog.home_section_cta') }} <flux:icon name="arrow-right" class="h-4 w-4" />
            </a>
        </section>
    @endif

    {{-- ===================== CTA BAND ===================== --}}
    <section class="mx-auto max-w-7xl px-4 pb-20 sm:px-6 lg:px-8">
        <div class="store-grad reveal relative overflow-hidden rounded-3xl px-6 py-14 text-center shadow-xl shadow-fuchsia-900/20 sm:px-12">
            <span class="store-drift pointer-events-none absolute -left-16 -top-16 h-56 w-56 rounded-full bg-white/15 blur-2xl"></span>
            <span class="store-drift-slow pointer-events-none absolute -bottom-20 -right-10 h-56 w-56 rounded-full bg-white/10 blur-2xl"></span>
            <h2 class="font-display relative text-2xl font-extrabold text-white sm:text-3xl">{{ __('store.cta_title') }}</h2>
            <p class="relative mx-auto mt-3 max-w-lg text-sm text-white/80 sm:text-base">{{ __('store.cta_text') }}</p>
            <a href="{{ route('shop') }}" class="relative mt-7 inline-flex items-center gap-2 rounded-xl bg-white px-6 py-3 text-sm font-bold text-violet-700 shadow-lg transition-transform hover:-translate-y-0.5">
                {{ __('store.cta_button') }} <flux:icon name="arrow-right" class="h-4 w-4" />
            </a>
        </div>
    </section>

</x-layouts.store>
