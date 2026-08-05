<?php

use App\Models\Product;
use App\Models\ProductCategory;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new class extends Component
{
    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'category')]
    public string $categoryId = '';

    #[Url]
    public string $sort = 'latest';

    /** How many products are currently shown; grows as the visitor scrolls. */
    public int $perPage = 12;

    /** How many more to reveal per "load more" / scroll step. */
    public int $step = 12;

    private const SORTS = ['latest', 'price_low', 'price_high', 'name'];

    public function mount(): void
    {
        $this->step = (int) config('store.per_page', 12);
        $this->perPage = $this->step;
    }

    public function updatedSearch(): void
    {
        $this->perPage = $this->step;
    }

    public function updatedCategoryId(): void
    {
        $this->perPage = $this->step;
    }

    public function updatedSort(): void
    {
        $this->perPage = $this->step;
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->categoryId = '';
        $this->sort = 'latest';
        $this->perPage = $this->step;
    }

    public function loadMore(): void
    {
        $this->perPage += $this->step;
    }

    private function baseQuery()
    {
        $sort = in_array($this->sort, self::SORTS, true) ? $this->sort : 'latest';
        $search = trim($this->search) ?: null;

        return Product::query()
            ->active()
            ->inStock()
            ->where('type', 'simple')
            ->with(['primaryImage', 'category:id,name,slug', 'stockLevels'])
            ->when($search, fn ($query) => $query->search($search))
            ->when($this->categoryId !== '', fn ($query) => $query->where('category_id', (int) $this->categoryId))
            ->when($sort === 'latest', fn ($query) => $query->latest())
            ->when($sort === 'price_low', fn ($query) => $query->orderBy('base_price'))
            ->when($sort === 'price_high', fn ($query) => $query->orderByDesc('base_price'))
            ->when($sort === 'name', fn ($query) => $query->orderBy('name'));
    }

    public function with(): array
    {
        $total = $this->baseQuery()->count();
        $products = $this->baseQuery()->take($this->perPage)->get();

        return [
            'products' => $products,
            'total' => $total,
            'hasMore' => $products->count() < $total,
            'categories' => ProductCategory::query()->active()->ordered()->get(['id', 'name', 'slug']),
            'hasFilters' => $this->search !== '' || $this->categoryId !== '' || $this->sort !== 'latest',
        ];
    }
}; ?>

<div>
    {{-- Filter bar --}}
    <div class="rounded-2xl border border-zinc-100 bg-white p-3 shadow-sm ring-1 ring-zinc-900/5">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-12">
            <div class="lg:col-span-6">
                <div class="flex items-center gap-2 rounded-xl border border-zinc-200 px-3 transition-colors focus-within:border-violet-400 focus-within:ring-2 focus-within:ring-violet-100">
                    <flux:icon name="magnifying-glass" class="h-5 w-5 shrink-0 text-zinc-400" wire:loading.remove wire:target="search" />
                    <flux:icon name="arrow-path" class="h-5 w-5 shrink-0 animate-spin text-violet-500" wire:loading wire:target="search" />
                    <input type="text" wire:model.live.debounce.400ms="search" placeholder="{{ __('store.shop_search_ph') }}" class="w-full border-0 bg-transparent py-2.5 text-sm text-zinc-900 placeholder:text-zinc-400 focus:outline-none focus:ring-0" />
                </div>
            </div>

            <div class="lg:col-span-3">
                <select wire:model.live="categoryId" class="w-full rounded-xl border-zinc-200 py-2.5 text-sm text-zinc-700 focus:border-violet-400 focus:ring-violet-400">
                    <option value="">{{ __('store.shop_all_categories') }}</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="lg:col-span-3">
                <select wire:model.live="sort" class="w-full rounded-xl border-zinc-200 py-2.5 text-sm text-zinc-700 focus:border-violet-400 focus:ring-violet-400">
                    <option value="latest">{{ __('store.sort_latest') }}</option>
                    <option value="price_low">{{ __('store.sort_price_low') }}</option>
                    <option value="price_high">{{ __('store.sort_price_high') }}</option>
                    <option value="name">{{ __('store.sort_name') }}</option>
                </select>
            </div>
        </div>
        <div class="mt-3 flex items-center justify-between gap-3">
            <p class="text-sm text-zinc-500 tabular-nums">{{ __('store.shop_results', ['count' => number_format($total)]) }}</p>
            @if($hasFilters)
                <button type="button" wire:click="clearFilters" class="rounded-lg px-3 py-2 text-sm font-semibold text-zinc-500 transition-colors hover:text-zinc-800">{{ __('store.shop_clear') }}</button>
            @endif
        </div>
    </div>

    {{-- Products --}}
    @if($products->isNotEmpty())
        <div class="mt-6 grid grid-cols-2 gap-4 transition-opacity sm:grid-cols-3 lg:grid-cols-4"
             wire:loading.class.delay="opacity-40" wire:target="search,categoryId,sort,clearFilters">
            @foreach($products as $product)
                <div wire:key="p-{{ $product->id }}">
                    <x-store.product-card :product="$product" />
                </div>
            @endforeach
        </div>

        @if($hasMore)
            {{-- Infinite scroll: the sentinel auto-loads when it nears the viewport;
                 the button is the accessible/no-JS fallback. --}}
            <div class="mt-8 flex flex-col items-center gap-3">
                <div x-intersect.margin.400px="$wire.loadMore()" class="h-px w-full" aria-hidden="true"></div>
                <button type="button" wire:click="loadMore" wire:loading.attr="disabled" wire:target="loadMore"
                        class="store-grad store-grad-hover inline-flex items-center gap-2 rounded-xl px-6 py-2.5 text-sm font-semibold text-white">
                    <flux:icon name="arrow-path" class="h-4 w-4 animate-spin" wire:loading wire:target="loadMore" />
                    <flux:icon name="arrow-down" class="h-4 w-4" wire:loading.remove wire:target="loadMore" />
                    <span wire:loading.remove wire:target="loadMore">{{ __('store.load_more') }}</span>
                    <span wire:loading wire:target="loadMore">{{ __('store.loading') }}</span>
                </button>
            </div>
        @elseif($total > $step)
            <p class="mt-8 text-center text-sm text-zinc-400">{{ __('store.all_loaded') }}</p>
        @endif
    @else
        <div class="mt-6 grid place-items-center rounded-2xl border border-dashed border-zinc-200 bg-white py-20 text-center">
            <flux:icon name="magnifying-glass" class="h-12 w-12 text-zinc-300" />
            <h3 class="font-display mt-3 text-base font-bold text-zinc-900">{{ __('store.shop_empty_title') }}</h3>
            <p class="mt-1 text-sm text-zinc-500">{{ __('store.shop_empty_text') }}</p>
            @if($hasFilters)
                <button type="button" wire:click="clearFilters" class="store-grad store-grad-hover mt-5 rounded-xl px-5 py-2.5 text-sm font-semibold text-white">{{ __('store.shop_clear') }}</button>
            @endif
        </div>
    @endif
</div>
