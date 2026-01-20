<?php

use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\StockAlert;
use App\Models\Warehouse;
use Livewire\Volt\Component;

new class extends Component {
    public $selectedWarehouse = '';

    public function with(): array
    {
        $warehouseQuery = $this->selectedWarehouse
            ? fn($query) => $query->where('warehouse_id', $this->selectedWarehouse)
            : fn($query) => $query;

        return [
            'warehouses' => Warehouse::active()->get(),
            'totalProducts' => Product::count(),
            'totalStock' => StockLevel::when($this->selectedWarehouse, $warehouseQuery)->sum('quantity'),
            'totalValue' => StockLevel::when($this->selectedWarehouse, $warehouseQuery)
                ->get()
                ->sum(fn($level) => $level->quantity * $level->average_cost),
            'lowStockCount' => StockLevel::when($this->selectedWarehouse, $warehouseQuery)
                ->whereHas('product', function($q) {
                    $q->whereColumn('stock_levels.quantity', '<=', 'products.min_quantity');
                })
                ->count(),
            'outOfStockCount' => StockLevel::when($this->selectedWarehouse, $warehouseQuery)
                ->where('quantity', '<=', 0)
                ->count(),
            'activeAlertsCount' => StockAlert::when($this->selectedWarehouse, $warehouseQuery)
                ->triggered()
                ->count(),
            'recentMovements' => StockMovement::when($this->selectedWarehouse, $warehouseQuery)
                ->with(['product', 'warehouse', 'createdBy'])
                ->latest()
                ->limit(10)
                ->get(),
            'lowStockProducts' => StockLevel::when($this->selectedWarehouse, $warehouseQuery)
                ->with(['product', 'warehouse'])
                ->whereHas('product', function($q) {
                    $q->whereColumn('stock_levels.quantity', '<=', 'products.min_quantity');
                })
                ->limit(10)
                ->get(),
            'topProducts' => StockLevel::when($this->selectedWarehouse, $warehouseQuery)
                ->with('product')
                ->orderBy('quantity', 'desc')
                ->limit(10)
                ->get(),
        ];
    }

    public function updatedSelectedWarehouse(): void
    {
        // Refresh the view when warehouse selection changes
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Inventory Dashboard</flux:heading>
            <flux:text class="mt-2">Overview of your stock levels and recent activity</flux:text>
        </div>
        <div class="flex items-center space-x-3">
            <flux:select wire:model.live="selectedWarehouse" placeholder="All Warehouses" class="w-48">
                <flux:select.option value="">All Warehouses</flux:select.option>
                @foreach($warehouses as $warehouse)
                    <flux:select.option value="{{ $warehouse->id }}">{{ $warehouse->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:button variant="primary" href="{{ route('stock.movements.create') }}" icon="plus">
                Add Movement
            </flux:button>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4 mb-8">
        <div class="rounded-lg border border-gray-200 bg-white p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <flux:icon name="cube" class="h-8 w-8 text-blue-600" />
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 truncate">Total Products</dt>
                        <dd class="text-lg font-medium text-gray-900">{{ number_format($totalProducts) }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <flux:icon name="chart-bar" class="h-8 w-8 text-green-600" />
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
                    <flux:icon name="currency-dollar" class="h-8 w-8 text-yellow-600" />
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
                    <flux:icon name="exclamation-triangle" class="h-8 w-8 text-red-600" />
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 truncate">Active Alerts</dt>
                        <dd class="text-lg font-medium text-gray-900">{{ number_format($activeAlertsCount) }}</dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <!-- Alert Summary -->
    @if($lowStockCount > 0 || $outOfStockCount > 0)
        <div class="mb-6 rounded-md border-l-4 border-yellow-400 bg-yellow-50 p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <flux:icon name="exclamation-triangle" class="h-5 w-5 text-yellow-400" />
                </div>
                <div class="ml-3">
                    <p class="text-sm text-yellow-700">
                        <strong>Stock Alerts:</strong>
                        @if($outOfStockCount > 0)
                            {{ $outOfStockCount }} products are out of stock.
                        @endif
                        @if($lowStockCount > 0)
                            {{ $lowStockCount }} products are running low.
                        @endif
                        <a href="{{ route('stock.alerts') }}" class="font-medium underline">
                            View all alerts
                        </a>
                    </p>
                </div>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <!-- Recent Stock Movements -->
        <div class="rounded-lg border border-gray-200 bg-white">
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <flux:heading size="lg">Recent Movements</flux:heading>
                    <flux:button variant="outline" size="sm" href="{{ route('stock.movements') }}">
                        View All
                    </flux:button>
                </div>
            </div>
            <div class="p-6">
                @if($recentMovements->count() > 0)
                    <div class="space-y-4">
                        @foreach($recentMovements as $movement)
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-3">
                                    <div class="flex-shrink-0">
                                        <flux:badge
                                            :variant="match($movement->type) {
                                                'in' => 'success',
                                                'out' => 'danger',
                                                'adjustment' => 'warning',
                                                'transfer' => 'info',
                                                default => 'outline'
                                            }"
                                            size="sm"
                                        >
                                            {{ $movement->formatted_type }}
                                        </flux:badge>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-medium text-gray-900 truncate">
                                            {{ $movement->product->name }}
                                        </p>
                                        <p class="text-sm text-gray-500 truncate">
                                            {{ $movement->warehouse->name }} • {{ $movement->created_at->diffForHumans() }}
                                        </p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-medium {{ $movement->quantity >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                        {{ $movement->display_quantity }}
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        {{ $movement->createdBy?->name ?? 'System' }}
                                    </p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <flux:text class="text-center text-gray-500">No recent movements</flux:text>
                @endif
            </div>
        </div>

        <!-- Low Stock Products -->
        <div class="rounded-lg border border-gray-200 bg-white">
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <flux:heading size="lg">Low Stock Products</flux:heading>
                    <flux:button variant="outline" size="sm" href="{{ route('stock.alerts') }}">
                        View All
                    </flux:button>
                </div>
            </div>
            <div class="p-6">
                @if($lowStockProducts->count() > 0)
                    <div class="space-y-4">
                        @foreach($lowStockProducts as $stockLevel)
                            <div class="flex items-center justify-between">
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-gray-900 truncate">
                                        {{ $stockLevel->product->name }}
                                    </p>
                                    <p class="text-sm text-gray-500 truncate">
                                        {{ $stockLevel->warehouse->name }} • SKU: {{ $stockLevel->product->sku }}
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-medium text-red-600">
                                        {{ $stockLevel->quantity }} left
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        Min: {{ $stockLevel->product->min_quantity }}
                                    </p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <flux:text class="text-center text-gray-500">All products are well stocked</flux:text>
                @endif
            </div>
        </div>
    </div>
</div>