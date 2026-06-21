@props(['product'])

@php
    $img = $product->primaryImage?->url;
    $tracks = $product->track_quantity;
    $available = $tracks ? $product->stockLevels->sum('available_quantity') : null;
    $outOfStock = $tracks && $available <= 0;
    $browseUrl = $product->category_id ? route('shop', ['category' => $product->category_id]) : route('shop');
@endphp

<div class="group flex flex-col overflow-hidden rounded-2xl border border-zinc-100 bg-white transition-all duration-300 hover:-translate-y-1 hover:border-emerald-200 hover:shadow-xl hover:shadow-emerald-900/5">
    <a href="{{ $browseUrl }}" class="relative block aspect-square overflow-hidden bg-zinc-50">
        @if($img)
            <img src="{{ $img }}" alt="{{ $product->name }}" loading="lazy" class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105">
        @else
            <div class="flex h-full w-full items-center justify-center bg-gradient-to-br from-emerald-50 to-zinc-50">
                <flux:icon name="photo" class="h-12 w-12 text-emerald-200" />
            </div>
        @endif
        @if($outOfStock)
            <span class="absolute left-3 top-3 rounded-full bg-zinc-900/80 px-2.5 py-1 text-[11px] font-semibold text-white">{{ __('store.out_of_stock') }}</span>
        @endif
    </a>

    <div class="flex flex-1 flex-col p-4">
        @if($product->category)
            <span class="text-[11px] font-semibold uppercase tracking-wide text-emerald-600">{{ $product->category->name }}</span>
        @endif
        <h3 class="mt-1 line-clamp-2 text-sm font-semibold leading-snug text-zinc-900">
            <a href="{{ $browseUrl }}" class="transition-colors hover:text-emerald-700">{{ $product->name }}</a>
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
