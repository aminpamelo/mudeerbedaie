@php
    /** @var string $active 'products' or 'packages' */
    $active = $active ?? 'products';
    $tabs = [
        ['key' => 'products', 'label' => __('Products'), 'icon' => 'cube', 'route' => 'products.index', 'count' => \App\Models\Product::count()],
        ['key' => 'packages', 'label' => __('Packages'), 'icon' => 'gift', 'route' => 'packages.index', 'count' => \App\Models\Package::count()],
    ];
@endphp
<div class="mb-6 inline-flex items-center gap-1 rounded-xl border border-gray-200 bg-white p-1 dark:border-zinc-700 dark:bg-zinc-800">
    @foreach($tabs as $tab)
        @php $isActive = $active === $tab['key']; @endphp
        <a
            href="{{ route($tab['route']) }}"
            wire:navigate
            @class([
                'inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition-colors',
                'bg-emerald-600 text-white shadow-sm' => $isActive,
                'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-zinc-700' => ! $isActive,
            ])
            @if($isActive) aria-current="page" @endif
        >
            <flux:icon :name="$tab['icon']" class="h-4 w-4" />
            {{ $tab['label'] }}
            <span @class([
                'rounded-full px-2 py-0.5 text-xs tabular-nums',
                'bg-white/20 text-white' => $isActive,
                'bg-gray-100 text-gray-500 dark:bg-zinc-700 dark:text-gray-400' => ! $isActive,
            ])>{{ number_format($tab['count']) }}</span>
        </a>
    @endforeach
</div>
