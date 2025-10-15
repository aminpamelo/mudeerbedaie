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
        return [
            'products' => Product::query()
                ->with(['category', 'stockLevels', 'media'])
                ->when($this->search, fn ($query) => $query->search($this->search))
                ->when($this->categoryFilter, fn ($query) => $query->where('category_id', $this->categoryFilter))
                ->when($this->statusFilter, fn ($query) => $query->where('status', $this->statusFilter))
                ->when($this->typeFilter, fn ($query) => $query->where('type', $this->typeFilter))
                ->latest()
                ->paginate(15),
            'categories' => ProductCategory::active()->ordered()->get(),
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
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Products</flux:heading>
            <flux:text class="mt-2">Manage your product catalog and inventory</flux:text>
        </div>
        <flux:button variant="primary" href="{{ route('products.create') }}" icon="plus">
            Add Product
        </flux:button>
    </div>

    <!-- Filters -->
    <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-5">
        <flux:input
            wire:model.live.debounce.300ms="search"
            placeholder="Search products..."
            icon="magnifying-glass"
        />

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

        <flux:button wire:click="clearFilters" variant="outline" icon="x-mark">
            Clear Filters
        </flux:button>
    </div>

    <!-- Products Table -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full border-collapse border-0 table-fixed">
                <colgroup>
                    <col class="w-[35%]"> <!-- Product name -->
                    <col class="w-[12%]"> <!-- Category -->
                    <col class="w-[10%]"> <!-- SKU -->
                    <col class="w-[8%]"> <!-- Price -->
                    <col class="w-[10%]"> <!-- Stock -->
                    <col class="w-[8%]"> <!-- Status -->
                    <col class="w-[8%]"> <!-- Type -->
                    <col class="w-[9%]"> <!-- Actions -->
                </colgroup>
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-6">Product</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Category</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">SKU</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Price</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Stock</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Status</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Type</th>
                        <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                            <span class="sr-only">Actions</span>
                            <span class="text-sm font-semibold text-gray-900">Actions</span>
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white">
                    @forelse($products as $product)
                        <tr wire:key="product-{{ $product->id }}" class="border-b border-gray-200 hover:bg-gray-50">
                        <td class="py-4 pl-4 pr-3 text-sm sm:pl-6">
                            <div class="flex items-center space-x-3">
                                @if($product->primaryImage)
                                    <img src="{{ $product->primaryImage->url }}"
                                         alt="{{ $product->name }}"
                                         class="h-10 w-10 rounded object-cover">
                                @else
                                    <div class="flex h-10 w-10 items-center justify-center rounded bg-gray-100">
                                        <flux:icon name="photo" class="h-5 w-5 text-gray-400" />
                                    </div>
                                @endif
                                <div class="min-w-0 flex-1">
                                    <div class="font-medium text-gray-900 break-words">{{ $product->name }}</div>
                                    @if($product->short_description)
                                        <div class="text-sm text-gray-500 break-words line-clamp-2">{{ $product->short_description }}</div>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="px-3 py-4 text-sm text-gray-900">
                            <flux:badge variant="outline" size="sm">
                                {{ $product->category?->name ?? 'Uncategorized' }}
                            </flux:badge>
                        </td>
                        <td class="px-3 py-4 text-sm text-gray-900">
                            <code class="text-sm">{{ $product->sku }}</code>
                        </td>
                        <td class="px-3 py-4 text-sm text-gray-900">{{ $product->formatted_price }}</td>
                        <td class="px-3 py-4 text-sm text-gray-900">
                            <div class="text-sm">
                                <div class="font-medium">{{ $product->total_stock }}</div>
                                <div class="text-gray-500">Available: {{ $product->available_stock }}</div>
                            </div>
                        </td>
                        <td class="px-3 py-4 text-sm text-gray-900">
                            <flux:badge :variant="match($product->status) {
                                'active' => 'success',
                                'inactive' => 'gray',
                                'draft' => 'warning',
                                default => 'gray'
                            }" size="sm">
                                {{ ucfirst($product->status) }}
                            </flux:badge>
                        </td>
                        <td class="px-3 py-4 text-sm text-gray-900">
                            <flux:badge variant="outline" size="sm">
                                {{ ucfirst($product->type) }}
                            </flux:badge>
                        </td>
                        <td class="relative py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                            <div class="flex items-center justify-end gap-1">
                                <flux:button
                                    href="{{ route('products.show', $product) }}"
                                    variant="ghost"
                                    size="sm"
                                    icon="eye"
                                    square
                                />
                                <flux:button
                                    href="{{ route('products.edit', $product) }}"
                                    variant="ghost"
                                    size="sm"
                                    icon="pencil"
                                    square
                                />
                                <flux:button
                                    wire:click="toggleStatus({{ $product->id }})"
                                    variant="ghost"
                                    size="sm"
                                    :icon="$product->isActive() ? 'pause' : 'play'"
                                    square
                                />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-6 py-12 text-center">
                            <div>
                                <flux:icon name="cube" class="mx-auto h-12 w-12 text-gray-400" />
                                <h3 class="mt-2 text-sm font-medium text-gray-900">No products found</h3>
                                <p class="mt-1 text-sm text-gray-500">Get started by creating your first product.</p>
                                <div class="mt-6">
                                    <flux:button variant="primary" href="{{ route('products.create') }}" icon="plus">
                                        Add Product
                                    </flux:button>
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <div class="mt-6">
        {{ $products->links() }}
    </div>
</div>