<?php

use App\Models\Product;
use App\Models\ProductCategory;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public $search = '';

    public $categoryFilter = '';

    public $statusFilter = '';

    public $typeFilter = '';

    public function with(): array
    {
        $statusCounts = Product::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            'products' => Product::query()
                ->with(['category', 'stockLevels', 'media', 'creatorFighter'])
                ->when($this->search, fn ($query) => $query->search($this->search))
                ->when($this->categoryFilter, fn ($query) => $query->where('category_id', $this->categoryFilter))
                ->when($this->statusFilter, fn ($query) => $query->where('status', $this->statusFilter))
                ->when($this->typeFilter, fn ($query) => $query->where('type', $this->typeFilter))
                ->latest()
                ->paginate(15),
            'categories' => ProductCategory::active()->ordered()->get(),
            'stats' => [
                'total' => (int) $statusCounts->sum(),
                'active' => (int) ($statusCounts['active'] ?? 0),
                'draft' => (int) ($statusCounts['draft'] ?? 0),
                'inactive' => (int) ($statusCounts['inactive'] ?? 0),
            ],
        ];
    }

    public function delete(Product $product): void
    {
        $product->delete();

        $this->dispatch('product-deleted');
    }

    public function toggleStatus(Product $product): void
    {
        $newStatus = match ($product->status) {
            'active' => 'inactive',
            'inactive' => 'active',
            'draft' => 'active',
            default => 'inactive',
        };

        $product->update(['status' => $newStatus]);
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'categoryFilter', 'statusFilter', 'typeFilter']);
        $this->resetPage();
    }
}; ?>

<div>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <flux:heading size="xl">Products</flux:heading>
            <flux:text class="mt-1">Manage your product catalog and inventory</flux:text>
        </div>
        <flux:button variant="primary" href="{{ route('products.create') }}" icon="plus">
            Add Product
        </flux:button>
    </div>

    <!-- Summary stats -->
    @php
        $cards = [
            ['label' => 'Total Products', 'value' => $stats['total'], 'icon' => 'cube', 'chip' => 'bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400'],
            ['label' => 'Active', 'value' => $stats['active'], 'icon' => 'check-circle', 'chip' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400'],
            ['label' => 'Draft', 'value' => $stats['draft'], 'icon' => 'pencil-square', 'chip' => 'bg-amber-50 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400'],
            ['label' => 'Inactive', 'value' => $stats['inactive'], 'icon' => 'pause-circle', 'chip' => 'bg-zinc-100 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300'],
        ];
    @endphp
    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        @foreach($cards as $card)
            <div class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ $card['chip'] }}">
                    <flux:icon :name="$card['icon']" class="h-5 w-5" />
                </div>
                <div class="min-w-0">
                    <div class="text-xl font-semibold tabular-nums text-gray-900 dark:text-gray-100">{{ number_format($card['value']) }}</div>
                    <div class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $card['label'] }}</div>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Filters -->
    <div class="mb-4 rounded-xl border border-gray-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <div class="lg:col-span-2">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search by name, SKU or barcode..."
                    icon="magnifying-glass"
                    clearable
                />
            </div>

            <flux:select wire:model.live="categoryFilter" placeholder="All Categories">
                <flux:select.option value="">All Categories</flux:select.option>
                @foreach($categories as $category)
                    <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="statusFilter" placeholder="All Statuses">
                <flux:select.option value="">All Statuses</flux:select.option>
                <flux:select.option value="active">Active</flux:select.option>
                <flux:select.option value="inactive">Inactive</flux:select.option>
                <flux:select.option value="draft">Draft</flux:select.option>
            </flux:select>

            <flux:select wire:model.live="typeFilter" placeholder="All Types">
                <flux:select.option value="">All Types</flux:select.option>
                <flux:select.option value="simple">Simple</flux:select.option>
                <flux:select.option value="variable">Variable</flux:select.option>
            </flux:select>
        </div>
    </div>

    <!-- Result count + clear -->
    <div class="mb-3 flex items-center justify-between gap-3">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            @if($products->total() > 0)
                Showing
                <span class="font-medium tabular-nums text-gray-700 dark:text-gray-300">{{ $products->firstItem() }}–{{ $products->lastItem() }}</span>
                of
                <span class="font-medium tabular-nums text-gray-700 dark:text-gray-300">{{ number_format($products->total()) }}</span>
                {{ Str::plural('product', $products->total()) }}
            @else
                No products match your filters
            @endif
        </p>
        @if($search || $categoryFilter || $statusFilter || $typeFilter)
            <flux:button wire:click="clearFilters" variant="ghost" size="sm" icon="x-mark">
                Clear filters
            </flux:button>
        @endif
    </div>

    <!-- Products Table -->
    <div
        class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm transition-opacity dark:border-zinc-700 dark:bg-zinc-800"
        wire:loading.class.delay="opacity-60"
    >
        <div class="overflow-x-auto">
            <table class="min-w-full table-fixed divide-y divide-gray-200 dark:divide-zinc-700">
                <colgroup>
                    <col class="w-[33%]"> <!-- Product name -->
                    <col class="w-[12%]"> <!-- Category -->
                    <col class="w-[11%]"> <!-- SKU -->
                    <col class="w-[10%]"> <!-- Price -->
                    <col class="w-[12%]"> <!-- Stock -->
                    <col class="w-[8%]">  <!-- Status -->
                    <col class="w-[8%]">  <!-- Type -->
                    <col class="w-[6%]">  <!-- Actions -->
                </colgroup>
                <thead class="bg-gray-50 dark:bg-zinc-700/50">
                    <tr>
                        <th scope="col" class="py-3 pl-4 pr-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 sm:pl-6">Product</th>
                        <th scope="col" class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Category</th>
                        <th scope="col" class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">SKU</th>
                        <th scope="col" class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Price</th>
                        <th scope="col" class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Stock</th>
                        <th scope="col" class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Status</th>
                        <th scope="col" class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Type</th>
                        <th scope="col" class="py-3 pl-3 pr-4 text-right sm:pr-6">
                            <span class="sr-only">Actions</span>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white dark:divide-zinc-700 dark:bg-zinc-800">
                    @forelse($products as $product)
                        @php
                            $tracks = $product->track_quantity;
                            $totalStock = $tracks ? $product->stockLevels->sum('quantity') : null;
                            $availableStock = $tracks ? $product->stockLevels->sum('available_quantity') : null;
                        @endphp
                        <tr wire:key="product-{{ $product->id }}" class="group hover:bg-gray-50 dark:hover:bg-zinc-700/40">
                            <td class="py-3 pl-4 pr-3 sm:pl-6">
                                <div class="flex items-center gap-3">
                                    @if($product->primaryImage)
                                        <img src="{{ $product->primaryImage->url }}"
                                             alt="{{ $product->name }}"
                                             class="h-11 w-11 shrink-0 rounded-lg object-cover ring-1 ring-gray-200 dark:ring-zinc-700">
                                    @else
                                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-gray-100 ring-1 ring-gray-200 dark:bg-zinc-700 dark:ring-zinc-600">
                                            <flux:icon name="photo" class="h-5 w-5 text-gray-400 dark:text-gray-500" />
                                        </div>
                                    @endif
                                    <div class="min-w-0 flex-1">
                                        <a href="{{ route('products.show', $product) }}"
                                           class="block truncate font-medium text-gray-900 transition-colors hover:text-emerald-600 dark:text-gray-100 dark:hover:text-emerald-400">
                                            {{ $product->name }}
                                        </a>
                                        @if($product->creatorFighter)
                                            <span class="mt-0.5 inline-flex items-center gap-1 rounded-full bg-orange-50 px-2 py-0.5 text-[11px] font-semibold text-orange-700 ring-1 ring-orange-600/20 dark:bg-orange-500/10 dark:text-orange-300">
                                                <span class="h-1.5 w-1.5 rounded-full bg-orange-500"></span>
                                                Fighter: {{ $product->creatorFighter->name }}
                                            </span>
                                        @elseif($product->short_description)
                                            <div class="truncate text-sm text-gray-500 dark:text-gray-400">{{ $product->short_description }}</div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-3">
                                <flux:badge variant="outline" size="sm">
                                    {{ $product->category?->name ?? 'Uncategorized' }}
                                </flux:badge>
                            </td>
                            <td class="px-3 py-3">
                                <code class="inline-block max-w-full truncate rounded bg-gray-100 px-1.5 py-0.5 align-middle font-mono text-xs text-gray-600 dark:bg-zinc-700 dark:text-gray-300">{{ $product->sku }}</code>
                            </td>
                            <td class="whitespace-nowrap px-3 py-3 text-right text-sm font-medium tabular-nums text-gray-900 dark:text-gray-100">
                                {{ $product->formatted_price }}
                            </td>
                            <td class="px-3 py-3">
                                @if(! $tracks)
                                    <span class="text-sm text-gray-400 dark:text-gray-500">Not tracked</span>
                                @elseif($availableStock <= 0)
                                    <span class="inline-flex items-center gap-1 rounded-md bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900/30 dark:text-red-400">
                                        <flux:icon name="exclamation-triangle" class="h-3.5 w-3.5" />
                                        Out of stock
                                    </span>
                                @else
                                    <div class="leading-tight">
                                        <div class="text-sm font-medium tabular-nums {{ $availableStock <= 10 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-900 dark:text-gray-100' }}">
                                            {{ number_format($totalStock) }}
                                        </div>
                                        <div class="whitespace-nowrap text-xs tabular-nums text-gray-500 dark:text-gray-400">{{ number_format($availableStock) }} available</div>
                                    </div>
                                @endif
                            </td>
                            <td class="px-3 py-3">
                                <flux:badge :variant="match($product->status) {
                                    'active' => 'success',
                                    'inactive' => 'gray',
                                    'draft' => 'warning',
                                    default => 'gray'
                                }" size="sm">
                                    {{ ucfirst($product->status) }}
                                </flux:badge>
                            </td>
                            <td class="px-3 py-3">
                                <flux:badge variant="outline" size="sm">
                                    {{ ucfirst($product->type) }}
                                </flux:badge>
                            </td>
                            <td class="py-3 pl-3 pr-4 sm:pr-6">
                                <div class="flex items-center justify-end gap-0.5 opacity-80 transition-opacity group-hover:opacity-100">
                                    <flux:tooltip content="View">
                                        <flux:button href="{{ route('products.show', $product) }}" variant="ghost" size="sm" icon="eye" square aria-label="View {{ $product->name }}" />
                                    </flux:tooltip>
                                    <flux:tooltip content="Edit">
                                        <flux:button href="{{ route('products.edit', $product) }}" variant="ghost" size="sm" icon="pencil" square aria-label="Edit {{ $product->name }}" />
                                    </flux:tooltip>
                                    <flux:tooltip :content="$product->isActive() ? 'Deactivate' : 'Activate'">
                                        <flux:button
                                            wire:click="toggleStatus({{ $product->id }})"
                                            variant="ghost"
                                            size="sm"
                                            :icon="$product->isActive() ? 'pause' : 'play'"
                                            square
                                            aria-label="{{ $product->isActive() ? 'Deactivate' : 'Activate' }} {{ $product->name }}"
                                        />
                                    </flux:tooltip>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-16 text-center">
                                <flux:icon name="cube" class="mx-auto h-12 w-12 text-gray-300 dark:text-zinc-600" />
                                <h3 class="mt-3 text-sm font-medium text-gray-900 dark:text-gray-100">No products found</h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    @if($search || $categoryFilter || $statusFilter || $typeFilter)
                                        Try adjusting your search or filters.
                                    @else
                                        Get started by creating your first product.
                                    @endif
                                </p>
                                <div class="mt-6 flex items-center justify-center gap-2">
                                    @if($search || $categoryFilter || $statusFilter || $typeFilter)
                                        <flux:button wire:click="clearFilters" variant="outline" icon="x-mark">Clear filters</flux:button>
                                    @endif
                                    <flux:button variant="primary" href="{{ route('products.create') }}" icon="plus">Add Product</flux:button>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    @if($products->hasPages())
        <div class="mt-6">
            {{ $products->links() }}
        </div>
    @endif
</div>
