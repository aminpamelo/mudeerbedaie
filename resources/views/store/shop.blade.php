<x-layouts.store :title="__('store.shop_title') . ' — ' . config('store.name')">

    {{-- Page header --}}
    <section class="border-b border-zinc-100 bg-gradient-to-b from-emerald-50/60 to-white">
        <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
            <nav class="mb-3 flex items-center gap-1.5 text-xs font-medium text-zinc-400">
                <a href="{{ route('home') }}" class="hover:text-emerald-700">{{ __('store.nav_home') }}</a>
                <flux:icon name="chevron-right" class="h-3.5 w-3.5" />
                <span class="text-zinc-600">{{ __('store.shop_title') }}</span>
            </nav>
            <h1 class="font-display text-3xl font-extrabold text-zinc-900 sm:text-4xl">{{ __('store.shop_title') }}</h1>
            <p class="mt-1.5 text-sm text-zinc-500">{{ __('store.shop_subtitle') }}</p>
        </div>
    </section>

    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        {{-- Filter bar --}}
        <form method="GET" action="{{ route('shop') }}" class="rounded-2xl border border-zinc-100 bg-white p-3 shadow-sm">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-12">
                <div class="lg:col-span-6">
                    <div class="flex items-center gap-2 rounded-xl border border-zinc-200 px-3 focus-within:border-emerald-400">
                        <flux:icon name="magnifying-glass" class="h-5 w-5 shrink-0 text-zinc-400" />
                        <input type="text" name="q" value="{{ $search }}" placeholder="{{ __('store.shop_search_ph') }}" class="w-full border-0 bg-transparent py-2.5 text-sm text-zinc-900 placeholder:text-zinc-400 focus:outline-none focus:ring-0" />
                    </div>
                </div>

                <div class="lg:col-span-3">
                    <select name="category" onchange="this.form.submit()" class="w-full rounded-xl border-zinc-200 py-2.5 text-sm text-zinc-700 focus:border-emerald-400 focus:ring-emerald-400">
                        <option value="">{{ __('store.shop_all_categories') }}</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" @selected($categoryId === $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="lg:col-span-3">
                    <select name="sort" onchange="this.form.submit()" class="w-full rounded-xl border-zinc-200 py-2.5 text-sm text-zinc-700 focus:border-emerald-400 focus:ring-emerald-400">
                        <option value="latest" @selected($sort === 'latest')>{{ __('store.sort_latest') }}</option>
                        <option value="price_low" @selected($sort === 'price_low')>{{ __('store.sort_price_low') }}</option>
                        <option value="price_high" @selected($sort === 'price_high')>{{ __('store.sort_price_high') }}</option>
                        <option value="name" @selected($sort === 'name')>{{ __('store.sort_name') }}</option>
                    </select>
                </div>
            </div>
            <div class="mt-3 flex items-center justify-between gap-3">
                <p class="text-sm text-zinc-500">{{ __('store.shop_results', ['count' => number_format($products->total())]) }}</p>
                <div class="flex items-center gap-2">
                    @if($search || $categoryId || $sort !== 'latest')
                        <a href="{{ route('shop') }}" class="rounded-lg px-3 py-2 text-sm font-semibold text-zinc-500 transition-colors hover:text-zinc-800">{{ __('store.shop_clear') }}</a>
                    @endif
                    <button type="submit" class="rounded-xl bg-emerald-600 px-5 py-2 text-sm font-semibold text-white transition-colors hover:bg-emerald-700">{{ __('store.shop_apply') }}</button>
                </div>
            </div>
        </form>

        {{-- Products --}}
        @if($products->isNotEmpty())
            <div class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                @foreach($products as $product)
                    <x-store.product-card :product="$product" />
                @endforeach
            </div>

            <div class="mt-8">
                {{ $products->onEachSide(1)->links() }}
            </div>
        @else
            <div class="mt-6 grid place-items-center rounded-2xl border border-dashed border-zinc-200 bg-white py-20 text-center">
                <flux:icon name="magnifying-glass" class="h-12 w-12 text-zinc-300" />
                <h3 class="font-display mt-3 text-base font-bold text-zinc-900">{{ __('store.shop_empty_title') }}</h3>
                <p class="mt-1 text-sm text-zinc-500">{{ __('store.shop_empty_text') }}</p>
                <a href="{{ route('shop') }}" class="mt-5 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-emerald-700">{{ __('store.shop_clear') }}</a>
            </div>
        @endif
    </div>

</x-layouts.store>
