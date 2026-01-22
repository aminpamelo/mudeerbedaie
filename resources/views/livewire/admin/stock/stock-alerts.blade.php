<?php

use App\Models\StockAlert;
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
    public $statusFilter = '';

    public function with(): array
    {
        return [
            'alerts' => StockAlert::query()
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
                ->when($this->statusFilter, fn($query) => $query->where('status', $this->statusFilter))
                ->latest()
                ->paginate(20),
            'warehouses' => Warehouse::active()->get(),
            'categories' => ProductCategory::active()->ordered()->get(),
            'activeAlertsCount' => StockAlert::triggered()->count(),
            'totalAlertsCount' => StockAlert::count(),
            'lowStockProducts' => StockLevel::with(['product', 'warehouse'])
                ->whereHas('product', function($q) {
                    $q->whereColumn('stock_levels.quantity', '<=', 'products.min_quantity');
                })
                ->limit(10)
                ->get(),
        ];
    }

    public function markAsRead(StockAlert $alert): void
    {
        $alert->update(['status' => 'read']);

        session()->flash('success', 'Alert marked as read.');
    }

    public function markAsResolved(StockAlert $alert): void
    {
        $alert->update(['status' => 'resolved']);

        session()->flash('success', 'Alert marked as resolved.');
    }

    public function delete(StockAlert $alert): void
    {
        $alert->delete();

        session()->flash('success', 'Alert deleted successfully.');
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'warehouseFilter', 'categoryFilter', 'statusFilter']);
        $this->resetPage();
    }

    public function generateAlertsForLowStock(): void
    {
        $lowStockLevels = StockLevel::with(['product', 'warehouse'])
            ->whereHas('product', function($q) {
                $q->whereColumn('stock_levels.quantity', '<=', 'products.min_quantity');
            })
            ->get();

        $alertsCreated = 0;

        foreach ($lowStockLevels as $stockLevel) {
            // Check if alert already exists for this product/warehouse combination
            $existingAlert = StockAlert::where('product_id', $stockLevel->product_id)
                ->where('warehouse_id', $stockLevel->warehouse_id)
                ->where('type', 'low_stock')
                ->where('status', '!=', 'resolved')
                ->first();

            if (!$existingAlert) {
                StockAlert::create([
                    'product_id' => $stockLevel->product_id,
                    'warehouse_id' => $stockLevel->warehouse_id,
                    'type' => 'low_stock',
                    'threshold' => $stockLevel->product->min_quantity,
                    'current_quantity' => $stockLevel->quantity,
                    'message' => "Low stock alert: {$stockLevel->product->name} has {$stockLevel->quantity} units remaining (minimum: {$stockLevel->product->min_quantity})",
                    'status' => 'active',
                    'triggered_at' => now(),
                ]);
                $alertsCreated++;
            }
        }

        session()->flash('success', "Generated {$alertsCreated} new stock alerts.");
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Stock Alerts</flux:heading>
            <flux:text class="mt-2">Monitor and manage inventory alerts</flux:text>
        </div>
        <div class="flex items-center space-x-3">
            <flux:button wire:click="generateAlertsForLowStock" variant="outline" icon="exclamation-triangle">
                Generate Alerts
            </flux:button>
            <flux:button variant="primary" href="{{ route('stock.movements.create') }}" icon="plus">
                Add Movement
            </flux:button>
        </div>
    </div>

    <!-- Summary Stats -->
    <div class="grid grid-cols-1 gap-6 md:grid-cols-3 mb-6">
        <div class="rounded-lg border border-gray-200 bg-white p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <flux:icon name="exclamation-triangle" class="h-8 w-8 text-red-600" />
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 truncate">Active Alerts</dt>
                        <dd class="text-lg font-medium text-red-600">{{ number_format($activeAlertsCount) }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <flux:icon name="bell" class="h-8 w-8 text-gray-600" />
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 truncate">Total Alerts</dt>
                        <dd class="text-lg font-medium text-gray-900">{{ number_format($totalAlertsCount) }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <flux:icon name="chart-bar" class="h-8 w-8 text-yellow-600" />
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 truncate">Low Stock Products</dt>
                        <dd class="text-lg font-medium text-yellow-600">{{ $lowStockProducts->count() }}</dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <!-- Alert for Low Stock Products -->
    @if($lowStockProducts->count() > 0)
        <div class="mb-6 rounded-md border-l-4 border-yellow-400 bg-yellow-50 p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <flux:icon name="exclamation-triangle" class="h-5 w-5 text-yellow-400" />
                </div>
                <div class="ml-3 flex-1">
                    <p class="text-sm text-yellow-700">
                        <strong>{{ $lowStockProducts->count() }} products are running low on stock.</strong>
                        Consider restocking these items soon.
                    </p>
                    <div class="mt-2">
                        <flux:button wire:click="generateAlertsForLowStock" variant="outline" size="sm">
                            Generate Alerts for Low Stock
                        </flux:button>
                    </div>
                </div>
            </div>
        </div>
    @endif

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

        <flux:select wire:model.live="statusFilter" placeholder="All Statuses">
            <flux:select.option value="">All Statuses</flux:select.option>
            <flux:select.option value="active">Active</flux:select.option>
            <flux:select.option value="read">Read</flux:select.option>
            <flux:select.option value="resolved">Resolved</flux:select.option>
        </flux:select>

        <flux:button wire:click="clearFilters" variant="outline" icon="x-mark">
            Clear
        </flux:button>
    </div>

    <!-- Alerts Table -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full border-collapse border-0">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-6">Product</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Warehouse</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Type</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Current/Threshold</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Status</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Triggered</th>
                        <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                            <span class="sr-only">Actions</span>
                            <span class="text-sm font-semibold text-gray-900">Actions</span>
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white">
                    @forelse($alerts as $alert)
                        <tr wire:key="alert-{{ $alert->id }}" class="border-b border-gray-200 hover:bg-gray-50 {{ $alert->status === 'active' ? 'bg-red-50' : '' }}">
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm sm:pl-6">
                            <div>
                                <div class="font-medium text-gray-900">{{ $alert->product->name }}</div>
                                <div class="text-gray-500">SKU: {{ $alert->product->sku }}</div>
                            </div>
                        </td>
                        <td class="px-3 py-4 text-sm text-gray-900">
                            <flux:badge variant="outline" size="sm">
                                {{ $alert->warehouse->name }}
                            </flux:badge>
                        </td>
                        <td class="px-3 py-4 text-sm text-gray-900">
                            <flux:badge :variant="match($alert->type) {
                                'low_stock' => 'warning',
                                'out_of_stock' => 'danger',
                                'overstocked' => 'info',
                                default => 'outline'
                            }" size="sm">
                                {{ ucfirst(str_replace('_', ' ', $alert->type)) }}
                            </flux:badge>
                        </td>
                        <td class="px-3 py-4 text-sm text-gray-900">
                            <div class="text-sm">
                                <div class="font-medium {{ $alert->current_quantity <= $alert->threshold ? 'text-red-600' : 'text-gray-900' }}">
                                    Current: {{ number_format($alert->current_quantity) }}
                                </div>
                                <div class="text-gray-500">Threshold: {{ number_format($alert->threshold) }}</div>
                            </div>
                        </td>
                        <td class="px-3 py-4 text-sm text-gray-900">
                            <flux:badge :variant="match($alert->status) {
                                'active' => 'danger',
                                'read' => 'warning',
                                'resolved' => 'success',
                                default => 'outline'
                            }" size="sm">
                                {{ ucfirst($alert->status) }}
                            </flux:badge>
                        </td>
                        <td class="px-3 py-4 text-sm text-gray-900">
                            <div class="text-sm">
                                <div>{{ $alert->triggered_at?->format('M j, Y') }}</div>
                                <div class="text-gray-500">{{ $alert->triggered_at?->format('g:i A') }}</div>
                            </div>
                        </td>
                        <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                            <div class="flex items-center justify-end space-x-2">
                                @if($alert->status === 'active')
                                    <flux:button
                                        wire:click="markAsRead({{ $alert->id }})"
                                        variant="outline"
                                        size="sm"
                                        icon="eye"
                                    >
                                        Mark Read
                                    </flux:button>
                                @endif
                                @if($alert->status !== 'resolved')
                                    <flux:button
                                        wire:click="markAsResolved({{ $alert->id }})"
                                        variant="outline"
                                        size="sm"
                                        icon="check"
                                    >
                                        Resolve
                                    </flux:button>
                                @endif
                                <flux:button
                                    wire:click="delete({{ $alert->id }})"
                                    wire:confirm="Are you sure you want to delete this alert?"
                                    variant="outline"
                                    size="sm"
                                    icon="trash"
                                    class="text-red-600 border-red-200 hover:bg-red-50"
                                >
                                    Delete
                                </flux:button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center">
                            <div>
                                <flux:icon name="bell" class="mx-auto h-12 w-12 text-gray-400" />
                                <h3 class="mt-2 text-sm font-medium text-gray-900">No alerts found</h3>
                                <p class="mt-1 text-sm text-gray-500">
                                    @if($search || $warehouseFilter || $categoryFilter || $statusFilter)
                                        Try adjusting your filters or search terms.
                                    @else
                                        Stock alerts will appear here when inventory levels need attention.
                                    @endif
                                </p>
                                @if(!$search && !$warehouseFilter && !$categoryFilter && !$statusFilter && $lowStockProducts->count() > 0)
                                    <div class="mt-6">
                                        <flux:button wire:click="generateAlertsForLowStock" variant="primary" icon="exclamation-triangle">
                                            Generate Alerts for Low Stock
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
    </div>

    <!-- Pagination -->
    <div class="mt-6">
        {{ $alerts->links() }}
    </div>
</div>