<x-layouts.store :seo="$seo">
    @php
        $tracks = $product->track_quantity;
        $available = $tracks ? $product->stockLevels->sum('available_quantity') : null;
        $outOfStock = $tracks && $available <= 0;
        $images = $product->images;
        $mainImage = $product->primaryImage?->url ?? $images->first()?->url;
    @endphp

    {{-- Breadcrumb --}}
    <section class="border-b border-violet-100/70 bg-gradient-to-b from-violet-50/60 to-white">
        <div class="mx-auto max-w-7xl px-4 py-4 sm:px-6 lg:px-8">
            <nav class="flex flex-wrap items-center gap-1.5 text-xs font-medium text-zinc-400">
                <a href="{{ route('storefront.home') }}" class="hover:text-violet-700">{{ __('store.nav_home') }}</a>
                <flux:icon name="chevron-right" class="h-3.5 w-3.5" />
                <a href="{{ route('shop') }}" class="hover:text-violet-700">{{ __('store.shop_title') }}</a>
                @if($product->category)
                    <flux:icon name="chevron-right" class="h-3.5 w-3.5" />
                    <a href="{{ route('shop', ['category' => $product->category->id]) }}" class="hover:text-violet-700">{{ $product->category->name }}</a>
                @endif
                <flux:icon name="chevron-right" class="h-3.5 w-3.5" />
                <span class="truncate text-zinc-600">{{ $product->name }}</span>
            </nav>
        </div>
    </section>

    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 gap-8 lg:grid-cols-2 lg:gap-12">
            {{-- Gallery --}}
            <div x-data="{ main: @js($mainImage) }">
                <div class="relative aspect-square overflow-hidden rounded-2xl border border-zinc-100 bg-zinc-50 ring-1 ring-zinc-900/5">
                    @if($mainImage)
                        <img x-bind:src="main" alt="{{ $product->name }}" class="h-full w-full object-cover"
                             onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <div class="hidden h-full w-full items-center justify-center bg-gradient-to-br from-violet-50 via-fuchsia-50 to-rose-50" aria-hidden="true">
                            <flux:icon name="photo" class="h-16 w-16 text-violet-200" />
                        </div>
                    @else
                        <div class="flex h-full w-full items-center justify-center bg-gradient-to-br from-violet-50 via-fuchsia-50 to-rose-50">
                            <flux:icon name="photo" class="h-16 w-16 text-violet-200" />
                        </div>
                    @endif
                    @if($outOfStock)
                        <span class="absolute left-4 top-4 rounded-full bg-zinc-900/80 px-3 py-1 text-xs font-semibold text-white">{{ __('store.out_of_stock') }}</span>
                    @endif
                </div>

                @if($images->count() > 1)
                    <div class="mt-3 grid grid-cols-5 gap-2">
                        @foreach($images as $image)
                            <button type="button" @click="main = @js($image->url)"
                                    class="aspect-square overflow-hidden rounded-lg border border-zinc-100 bg-zinc-50 transition-all hover:border-violet-300"
                                    :class="main === @js($image->url) ? 'ring-2 ring-violet-500' : ''">
                                <img src="{{ $image->url }}" alt="{{ $product->name }}" loading="lazy" class="h-full w-full object-cover"
                                     onerror="this.style.visibility='hidden'">
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Info --}}
            <div class="flex flex-col">
                @if($product->category)
                    <a href="{{ route('shop', ['category' => $product->category->id]) }}" class="text-xs font-semibold uppercase tracking-wide text-fuchsia-600 hover:text-fuchsia-700">{{ $product->category->name }}</a>
                @endif
                <h1 class="font-display mt-1.5 text-2xl font-extrabold leading-tight text-zinc-900 sm:text-3xl">{{ $product->name }}</h1>

                <div class="mt-4 flex items-center gap-3">
                    <span class="store-grad-text font-display text-3xl font-extrabold tabular-nums">{{ $product->formatted_price }}</span>
                    @if($outOfStock)
                        <span class="inline-flex items-center gap-1 rounded-full bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-600">
                            <flux:icon name="x-circle" class="h-4 w-4" /> {{ __('store.out_of_stock') }}
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1 rounded-full bg-violet-50 px-2.5 py-1 text-xs font-semibold text-violet-700">
                            <flux:icon name="check-circle" class="h-4 w-4" /> {{ __('store.in_stock') }}
                        </span>
                    @endif
                </div>

                @if($product->short_description)
                    <p class="mt-4 text-sm leading-relaxed text-zinc-600">{{ $product->short_description }}</p>
                @endif

                <div class="mt-6">
                    @if($outOfStock)
                        <button type="button" disabled class="w-full cursor-not-allowed rounded-xl bg-zinc-100 px-4 py-3 text-sm font-semibold text-zinc-400 sm:w-auto sm:px-10">
                            {{ __('store.out_of_stock') }}
                        </button>
                    @else
                        <livewire:store.product-cart :product="$product" :key="'pc-'.$product->id" />
                    @endif
                </div>

                {{-- Trust / meta --}}
                <div class="mt-8 grid grid-cols-1 gap-3 border-t border-zinc-100 pt-6 text-sm text-zinc-600 sm:grid-cols-2">
                    @if($product->sku)
                        <div class="flex items-center gap-2"><flux:icon name="hashtag" class="h-4 w-4 text-zinc-400" /> {{ __('store.sku') }}: <span class="font-medium text-zinc-800">{{ $product->sku }}</span></div>
                    @endif
                    <div class="flex items-center gap-2"><flux:icon name="shield-check" class="h-4 w-4 text-violet-600" /> {{ __('store.stat_secure') }}</div>
                    <div class="flex items-center gap-2"><flux:icon name="truck" class="h-4 w-4 text-fuchsia-600" /> {{ __('store.stat_delivery') }}</div>
                    <div class="flex items-center gap-2"><flux:icon name="arrow-path" class="h-4 w-4 text-rose-500" /> {{ __('store.stat_support') }}</div>
                </div>
            </div>
        </div>

        {{-- Description --}}
        @if($product->description)
            <section class="mt-12 max-w-3xl">
                <h2 class="font-display text-xl font-bold text-zinc-900">{{ __('store.description_title') }}</h2>
                <div class="prose prose-zinc mt-4 max-w-none text-sm leading-relaxed text-zinc-600">
                    {!! nl2br(e($product->description)) !!}
                </div>
            </section>
        @endif

        {{-- Related --}}
        @if($related->isNotEmpty())
            <section class="mt-16">
                <h2 class="font-display text-xl font-bold text-zinc-900 sm:text-2xl">{{ __('store.related_title') }}</h2>
                <div class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                    @foreach($related as $item)
                        <x-store.product-card :product="$item" />
                    @endforeach
                </div>
            </section>
        @endif
    </div>

</x-layouts.store>
