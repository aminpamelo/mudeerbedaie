<?php

use App\Models\StockLevel;
use App\Models\Warehouse;
use App\Models\ProductCategory;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public $search = '';
    public $warehouseFilter = '';
    public $categoryFilter = '';
    public $stockFilter = '';

    public function with(): array
    {
        return [
            'stockLevels' => StockLevel::query()
                ->with(['product.category', 'warehouse'])
                ->when($this->search, function($query) {
                    $query->whereHas('product', function($q) {
                        $q->where('name', 'like', "%{$this->search}%")
                          ->orWhere('sku', 'like', "%{$this->search}%");
                    });
                })
                ->when($this->warehouseFilter, fn($query) => $query->where('warehouse_id', $this->warehouseFilter))
                ->when($this->categoryFilter, function($query) {
                    $query->whereHas('product', function($q) {
                        $q->where('category_id', $this->categoryFilter);
                    });
                })
                ->when($this->stockFilter, function($query) {
                    match($this->stockFilter) {
                        'in_stock' => $query->where('quantity', '>', 0),
                        'out_of_stock' => $query->where('quantity', '<=', 0),
                        'low_stock' => $query->whereHas('product', function($q) {
                            $q->whereColumn('stock_levels.quantity', '<=', 'products.min_quantity');
                        }),
                        default => $query,
                    };
                })
                ->orderBy('quantity', 'asc')
                ->paginate(20),
            'warehouses' => Warehouse::active()->get(),
            'categories' => ProductCategory::active()->ordered()->get(),
            'totalStock' => StockLevel::sum('quantity'),
            'totalValue' => StockLevel::with('product')->get()->sum(fn($level) => $level->quantity * $level->average_cost),
            'lowStockCount' => StockLevel::whereHas('product', function($q) {
                $q->whereColumn('stock_levels.quantity', '<=', 'products.min_quantity');
            })->count(),
            'outOfStockCount' => StockLevel::where('quantity', '<=', 0)->count(),
        ];
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'warehouseFilter', 'categoryFilter', 'stockFilter']);
        $this->resetPage();
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Stock Levels</flux:heading>
            <flux:text class="mt-2">Monitor current inventory levels across all warehouses</flux:text>
        </div>
        <flux:button variant="primary" href="{{ route('stock.movements.create') }}" icon="plus">
            Add Movement
        </flux:button>
    </div>

    <!-- Summary Stats -->
    <div class="grid grid-cols-1 gap-6 md:grid-cols-4 mb-6">
        <div class="rounded-lg border border-gray-200 bg-white p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <flux:icon name="cube" class="h-8 w-8 text-blue-600" />
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 truncate">Total Stock</dt>
                        <dd class="text-lg font-medium text-gray-900">{{ number_format($totalStock) }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <flux:icon name="currency-dollar" class="h-8 w-8 text-green-600" />
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 truncate">Total Value</dt>
                        <dd class="text-lg font-medium text-gray-900">RM {{ number_format($totalValue, 2) }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <flux:icon name="exclamation-triangle" class="h-8 w-8 text-yellow-600" />
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 truncate">Low Stock</dt>
                        <dd class="text-lg font-medium text-yellow-600">{{ number_format($lowStockCount) }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <flux:icon name="x-circle" class="h-8 w-8 text-red-600" />
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 truncate">Out of Stock</dt>
                        <dd class="text-lg font-medium text-red-600">{{ number_format($outOfStockCount) }}</dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-5">
        <flux:input
            wire:model.live.debounce.300ms="search"
            placeholder="Search products..."
            icon="magnifying-glass"
        />

        <flux:select wire:model.live="warehouseFilter" placeholder="All Warehouses">
            <flux:select.option value="">All Warehouses</flux:select.option>
            @foreach($warehouses as $warehouse)
                <flux:select.option value="{{ $warehouse->id }}">{{ $warehouse->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="categoryFilter" placeholder="All Categories">
            <flux:select.option value="">All Categories</flux:select.option>
            @foreach($categories as $category)
                <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="stockFilter" placeholder="All Stock Levels">
            <flux:select.option value="">All Stock Levels</flux:select.option>
            <flux:select.option value="in_stock">In Stock</flux:select.option>
            <flux:select.option value="out_of_stock">Out of Stock</flux:select.option>
            <flux:select.option value="low_stock">Low Stock</flux:select.option>
        </flux:select>

        <flux:button wire:click="clearFilters" variant="outline" icon="x-mark">
            Clear
        </flux:button>
    </div>

    <!-- Stock Levels Table -->
    <div class="overflow-hidden bg-white shadow ring-1 ring-black ring-opacity-5 sm:rounded-lg">
        <table class="min-w-full divide-y divide-gray-300">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-6">Product</th>
                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Category</th>
                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Warehouse</th>
                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Current Stock</th>
                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Reserved</th>
                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Available</th>
                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Min Quantity</th>
                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
                @forelse($stockLevels as $stockLevel)
                    <tr wire:key="stock-level-{{ $stockLevel->id }}" class="hover:bg-gray-50">
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm sm:pl-6">
                            <div>
                                <div class="font-medium text-gray-900">{{ $stockLevel->product->name }}</div>
                                <div class="text-gray-500">SKU: {{ $stockLevel->product->sku }}</div>
                            </div>
                        </td>
                        <td class="px-3 py-4 text-sm text-gray-900">
                            @if($stockLevel->product->category)
                                <flux:badge variant="outline" size="sm">
                                    {{ $stockLevel->product->category->name }}
                                </flux:badge>
                            @else
                                <span class="text-gray-400">Uncategorized</span>
                            @endif
                        </td>
                        <td class="px-3 py-4 text-sm text-gray-900">
                            <flux:badge variant="outline" size="sm">
                                {{ $stockLevel->warehouse->name }}
                            </flux:badge>
                        </td>
                        <td class="px-3 py-4 text-sm text-gray-900">
                            <span class="font-medium {{ $stockLevel->quantity <= 0 ? 'text-red-600' : ($stockLevel->quantity <= $stockLevel->product->min_quantity ? 'text-yellow-600' : 'text-gray-900') }}">
                                {{ number_format($stockLevel->quantity) }}
                            </span>
                        </td>
                        <td class="px-3 py-4 text-sm text-gray-900">{{ number_format($stockLevel->reserved_quantity) }}</td>
                        <td class="px-3 py-4 text-sm text-gray-900">
                            <span class="font-medium">{{ number_format($stockLevel->quantity - $stockLevel->reserved_quantity) }}</span>
                        </td>
                        <td class="px-3 py-4 text-sm text-gray-900">{{ number_format($stockLevel->product->min_quantity) }}</td>
                        <td class="px-3 py-4 text-sm text-gray-900">
                            @if($stockLevel->quantity <= 0)
                                <flux:badge variant="danger" size="sm">Out of Stock</flux:badge>
                            @elseif($stockLevel->quantity <= $stockLevel->product->min_quantity)
                                <flux:badge variant="warning" size="sm">Low Stock</flux:badge>
                            @else
                                <flux:badge variant="success" size="sm">In Stock</flux:badge>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-6 py-12 text-center">
                            <div>
                                <flux:icon name="cube" class="mx-auto h-12 w-12 text-gray-400" />
                                <h3 class="mt-2 text-sm font-medium text-gray-900">No stock levels found</h3>
                                <p class="mt-1 text-sm text-gray-500">
                                    @if($search || $warehouseFilter || $categoryFilter || $stockFilter)
                                        Try adjusting your filters or search terms.
                                    @else
                                        Stock levels will appear here as products are added to inventory.
                                    @endif
                                </p>
                                @if(!$search && !$warehouseFilter && !$categoryFilter && !$stockFilter)
                                    <div class="mt-6">
                                        <flux:button variant="primary" href="{{ route('stock.movements.create') }}" icon="plus">
                                            Add Movement
                                        </flux:button>
                                    </div>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="mt-6">
        {{ $stockLevels->links() }}
    </div>
</div>