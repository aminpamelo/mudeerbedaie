<x-layouts.store :title="config('store.name') . ' — ' . __('store.hero_eyebrow')">

    {{-- ===================== HERO ===================== --}}
    <section class="relative overflow-hidden border-b border-emerald-100 bg-gradient-to-b from-emerald-50/80 to-white">
        <div class="store-hero-grid absolute inset-0 opacity-70"></div>
        <span class="pointer-events-none absolute -right-24 -top-24 h-72 w-72 rounded-full bg-emerald-200/40 blur-3xl"></span>
        <div class="relative mx-auto max-w-7xl px-4 py-16 sm:px-6 sm:py-20 lg:px-8 lg:py-24">
            <div class="mx-auto max-w-3xl text-center">
                <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-white/70 px-3 py-1 text-xs font-semibold text-emerald-700">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                    {{ __('store.hero_eyebrow') }}
                </span>
                <h1 class="font-display mt-5 text-4xl font-extrabold leading-[1.1] text-zinc-900 sm:text-5xl lg:text-6xl">
                    {{ __('store.hero_title') }}
                </h1>
                <p class="mx-auto mt-5 max-w-2xl text-base leading-relaxed text-zinc-600 sm:text-lg">
                    {{ __('store.hero_subtitle') }}
                </p>

                {{-- Search --}}
                <form method="GET" action="{{ route('shop') }}" class="mx-auto mt-8 flex max-w-xl items-center gap-2 rounded-2xl border border-zinc-200 bg-white p-1.5 shadow-lg shadow-emerald-900/5">
                    <div class="flex flex-1 items-center gap-2 pl-3">
                        <flux:icon name="magnifying-glass" class="h-5 w-5 shrink-0 text-zinc-400" />
                        <input type="text" name="q" placeholder="{{ __('store.hero_search_ph') }}" class="w-full border-0 bg-transparent py-2.5 text-sm text-zinc-900 placeholder:text-zinc-400 focus:outline-none focus:ring-0" />
                    </div>
                    <button type="submit" class="shrink-0 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-emerald-700">
                        {{ __('store.hero_cta_shop') }}
                    </button>
                </form>

                {{-- Trust stats --}}
                <div class="mx-auto mt-8 flex flex-wrap items-center justify-center gap-x-8 gap-y-3 text-sm text-zinc-600">
                    <span class="inline-flex items-center gap-2"><flux:icon name="cube" class="h-4 w-4 text-emerald-600" /> <strong class="font-semibold text-zinc-900 tabular-nums">{{ number_format($productCount) }}</strong> {{ __('store.stat_products') }}</span>
                    <span class="inline-flex items-center gap-2"><flux:icon name="shield-check" class="h-4 w-4 text-emerald-600" /> {{ __('store.stat_secure') }}</span>
                    <span class="inline-flex items-center gap-2"><flux:icon name="truck" class="h-4 w-4 text-emerald-600" /> {{ __('store.stat_delivery') }}</span>
                </div>
            </div>
        </div>
    </section>

    {{-- ===================== CATEGORIES ===================== --}}
    @if($categories->isNotEmpty())
        <section class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <h2 class="font-display text-2xl font-extrabold text-zinc-900 sm:text-3xl">{{ __('store.categories_title') }}</h2>
                    <p class="mt-1.5 text-sm text-zinc-500">{{ __('store.categories_subtitle') }}</p>
                </div>
                <a href="{{ route('shop') }}" class="hidden shrink-0 items-center gap-1 text-sm font-semibold text-emerald-700 hover:text-emerald-800 sm:inline-flex">
                    {{ __('store.categories_all') }} <flux:icon name="arrow-right" class="h-4 w-4" />
                </a>
            </div>
            <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                @foreach($categories as $category)
                    <a href="{{ route('shop', ['category' => $category->id]) }}" class="group flex items-center gap-3 rounded-2xl border border-zinc-100 bg-white p-4 transition-all hover:-translate-y-0.5 hover:border-emerald-200 hover:shadow-md">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-emerald-50 text-emerald-600 transition-colors group-hover:bg-emerald-600 group-hover:text-white">
                            <flux:icon name="tag" class="h-5 w-5" />
                        </span>
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-semibold text-zinc-900">{{ $category->name }}</span>
                            <span class="text-xs text-zinc-400 tabular-nums">{{ trans_choice('store.category_count', $category->active_products_count, ['count' => $category->active_products_count]) }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- ===================== FEATURED PRODUCTS ===================== --}}
    <section class="mx-auto max-w-7xl px-4 pb-14 sm:px-6 lg:px-8">
        <div class="flex items-end justify-between gap-4">
            <div>
                <h2 class="font-display text-2xl font-extrabold text-zinc-900 sm:text-3xl">{{ __('store.featured_title') }}</h2>
                <p class="mt-1.5 text-sm text-zinc-500">{{ __('store.featured_subtitle') }}</p>
            </div>
            <a href="{{ route('shop') }}" class="inline-flex shrink-0 items-center gap-1 text-sm font-semibold text-emerald-700 hover:text-emerald-800">
                {{ __('store.view_all') }} <flux:icon name="arrow-right" class="h-4 w-4" />
            </a>
        </div>

        @if($featured->isNotEmpty())
            <div class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                @foreach($featured as $product)
                    <x-store.product-card :product="$product" />
                @endforeach
            </div>
        @else
            <div class="mt-6 grid place-items-center rounded-2xl border border-dashed border-zinc-200 py-16 text-sm text-zinc-400">
                {{ __('store.no_products') }}
            </div>
        @endif
    </section>

    {{-- ===================== PACKAGE DEALS ===================== --}}
    @if($packages->isNotEmpty())
        <section id="packages" class="bg-zinc-50 py-16 scroll-mt-20">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="text-center">
                    <h2 class="font-display text-2xl font-extrabold text-zinc-900 sm:text-3xl">{{ __('store.packages_title') }}</h2>
                    <p class="mx-auto mt-1.5 max-w-xl text-sm text-zinc-500">{{ __('store.packages_subtitle') }}</p>
                </div>

                <div class="mt-8 grid grid-cols-1 gap-5 md:grid-cols-3">
                    @foreach($packages as $package)
                        @php
                            $savingsPct = $package->getSavingsPercentage();
                            $whatsapp = config('store.whatsapp');
                            $waUrl = $whatsapp
                                ? 'https://wa.me/' . $whatsapp . '?text=' . rawurlencode($package->name . ' — ' . $package->formatted_price)
                                : null;
                        @endphp
                        <div class="flex flex-col overflow-hidden rounded-3xl border border-zinc-100 bg-white shadow-sm">
                            <div class="relative flex aspect-[16/10] items-center justify-center bg-gradient-to-br from-emerald-600 to-emerald-500 p-6 text-center">
                                <span class="absolute left-4 top-4 rounded-full bg-white/20 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-white backdrop-blur">{{ __('store.package_badge') }}</span>
                                @if($savingsPct > 0)
                                    <span class="absolute right-4 top-4 rounded-full bg-amber-400 px-2.5 py-1 text-[11px] font-extrabold text-amber-950 tabular-nums">{{ __('store.package_save_pct', ['pct' => $savingsPct]) }}</span>
                                @endif
                                <flux:icon name="gift" class="h-14 w-14 text-white/90" />
                            </div>
                            <div class="flex flex-1 flex-col p-5">
                                <h3 class="font-display line-clamp-2 text-base font-bold text-zinc-900">{{ $package->name }}</h3>
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
                                        <div class="mt-1 inline-flex items-center gap-1 rounded-md bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700">
                                            <flux:icon name="sparkles" class="h-3.5 w-3.5" /> {{ __('store.package_save', ['amount' => $package->formatted_savings]) }}
                                        </div>
                                    @endif

                                    @if($waUrl)
                                        <a href="{{ $waUrl }}" target="_blank" rel="noopener" class="mt-4 flex w-full items-center justify-center gap-2 rounded-xl border border-emerald-600 px-4 py-2.5 text-sm font-semibold text-emerald-700 transition-colors hover:bg-emerald-600 hover:text-white">
                                            <flux:icon name="chat-bubble-left-right" class="h-4 w-4" />
                                            {{ __('store.package_order') }}
                                        </a>
                                    @else
                                        <a href="{{ route('shop') }}" class="mt-4 flex w-full items-center justify-center gap-2 rounded-xl border border-emerald-600 px-4 py-2.5 text-sm font-semibold text-emerald-700 transition-colors hover:bg-emerald-600 hover:text-white">
                                            {{ __('store.view_all') }}
                                            <flux:icon name="arrow-right" class="h-4 w-4" />
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- ===================== WHY US ===================== --}}
    <section class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
        <div class="text-center">
            <h2 class="font-display text-2xl font-extrabold text-zinc-900 sm:text-3xl">{{ __('store.why_title') }}</h2>
            <p class="mx-auto mt-1.5 max-w-xl text-sm text-zinc-500">{{ __('store.why_subtitle') }}</p>
        </div>
        @php
            $whys = [
                ['icon' => 'truck', 'title' => 'why_1_title', 'text' => 'why_1_text'],
                ['icon' => 'shield-check', 'title' => 'why_2_title', 'text' => 'why_2_text'],
                ['icon' => 'check-badge', 'title' => 'why_3_title', 'text' => 'why_3_text'],
                ['icon' => 'chat-bubble-left-right', 'title' => 'why_4_title', 'text' => 'why_4_text'],
            ];
        @endphp
        <div class="mt-8 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
            @foreach($whys as $why)
                <div class="rounded-2xl border border-zinc-100 bg-white p-6 text-center">
                    <span class="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-emerald-50 text-emerald-600">
                        <flux:icon :name="$why['icon']" class="h-6 w-6" />
                    </span>
                    <h3 class="font-display mt-4 text-sm font-bold text-zinc-900">{{ __('store.' . $why['title']) }}</h3>
                    <p class="mt-1.5 text-sm leading-relaxed text-zinc-500">{{ __('store.' . $why['text']) }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- ===================== CTA BAND ===================== --}}
    <section class="mx-auto max-w-7xl px-4 pb-20 sm:px-6 lg:px-8">
        <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-emerald-700 to-emerald-600 px-6 py-14 text-center shadow-xl shadow-emerald-900/10 sm:px-12">
            <span class="pointer-events-none absolute -left-16 -top-16 h-56 w-56 rounded-full bg-white/10 blur-2xl"></span>
            <span class="pointer-events-none absolute -bottom-20 -right-10 h-56 w-56 rounded-full bg-emerald-400/20 blur-2xl"></span>
            <h2 class="font-display relative text-2xl font-extrabold text-white sm:text-3xl">{{ __('store.cta_title') }}</h2>
            <p class="relative mx-auto mt-3 max-w-lg text-sm text-emerald-50 sm:text-base">{{ __('store.cta_text') }}</p>
            <a href="{{ route('shop') }}" class="relative mt-7 inline-flex items-center gap-2 rounded-xl bg-white px-6 py-3 text-sm font-bold text-emerald-700 shadow-sm transition-transform hover:-translate-y-0.5">
                {{ __('store.cta_button') }} <flux:icon name="arrow-right" class="h-4 w-4" />
            </a>
        </div>
    </section>

</x-layouts.store>
