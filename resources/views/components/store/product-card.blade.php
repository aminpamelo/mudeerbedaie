@props([
    'product',
    // Set on the storefront's top sellers so the badge reflects real demand
    // rather than a hand-picked flag.
    'bestseller' => false,
])

@php
    $img = $product->primaryImage?->url;
    $tracks = $product->track_quantity;
    $available = $tracks ? $product->stockLevels->sum('available_quantity') : null;
    $outOfStock = $tracks && $available <= 0;
    $browseUrl = route('storefront.product', $product->slug);
@endphp

<div class="group flex flex-col overflow-hidden rounded-2xl border border-zinc-100 bg-white transition-all duration-300 hover:-translate-y-1 hover:border-violet-200 hover:shadow-xl hover:shadow-fuchsia-900/10">
    <a href="{{ $browseUrl }}" class="relative block aspect-square overflow-hidden bg-zinc-50">
        @if($img)
            <img src="{{ $img }}" alt="{{ $product->name }}" loading="lazy" class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                 onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
            {{-- Shown only if the image fails to load (missing/broken file). --}}
            <div class="hidden h-full w-full items-center justify-center bg-gradient-to-br from-violet-50 via-fuchsia-50 to-rose-50" aria-hidden="true">
                <flux:icon name="photo" class="h-12 w-12 text-violet-200" />
            </div>
        @else
            <div class="flex h-full w-full items-center justify-center bg-gradient-to-br from-violet-50 via-fuchsia-50 to-rose-50">
                <flux:icon name="photo" class="h-12 w-12 text-violet-200" />
            </div>
        @endif
        @if($outOfStock)
            <span class="absolute left-3 top-3 rounded-full bg-zinc-900/80 px-2.5 py-1 text-[11px] font-semibold text-white">{{ __('store.out_of_stock') }}</span>
        @elseif($bestseller)
            <span class="absolute left-3 top-3 inline-flex items-center gap-1 rounded-full bg-amber-300 px-2.5 py-1 text-[11px] font-extrabold uppercase tracking-wide text-amber-950 shadow-sm">
                <flux:icon name="fire" class="h-3.5 w-3.5" />
                {{ __('store.badge_bestseller') }}
            </span>
        @endif
    </a>

    <div class="flex flex-1 flex-col p-4">
        @if($product->category)
            <span class="text-[11px] font-semibold uppercase tracking-wide text-fuchsia-600">{{ $product->category->name }}</span>
        @endif
        <h3 class="mt-1 line-clamp-2 text-sm font-semibold leading-snug text-zinc-900">
            <a href="{{ $browseUrl }}" class="transition-colors hover:text-violet-700">{{ $product->name }}</a>
        </h3>

        <div class="mt-3 flex flex-1 items-end">
            <span class="font-display text-lg font-extrabold tabular-nums text-zinc-900">{{ $product->formatted_price }}</span>
        </div>

        <div class="mt-3">
            @if($outOfStock)
                <button type="button" disabled class="w-full cursor-not-allowed rounded-xl bg-zinc-100 px-4 py-2.5 text-sm font-semibold text-zinc-400">
                    {{ __('store.out_of_stock') }}
                </button>
            @else
                <livewire:store.add-to-cart :product="$product" :key="'atc-'.$product->id" />
            @endif
        </div>
    </div>
</div>
