<?php

use App\Models\ProductOrder;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    public function layout()
    {
        return 'components.layouts.app.sidebar';
    }

    use WithPagination;

    public string $search = '';

    public string $activeTab = 'all';

    public string $orderTypeFilter = '';

    public string $productFilter = '';

    public string $dateFilter = '';

    public string $sortBy = 'created_at';

    public string $sortDirection = 'desc';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingActiveTab(): void
    {
        $this->resetPage();
    }

    public function updatingDateFilter(): void
    {
        $this->resetPage();
    }

    public function updatingOrderTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatingProductFilter(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortBy === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $field;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    public function updateOrderStatus(int $orderId, string $status): void
    {
        $order = ProductOrder::findOrFail($orderId);

        // Call appropriate status method based on status
        match ($status) {
            'confirmed' => $order->markAsConfirmed(),
            'processing' => $order->markAsProcessing(),
            'shipped' => $order->markAsShipped(),
            'delivered' => $order->markAsDelivered(),
            'cancelled' => $order->markAsCancelled('Cancelled by admin'),
            default => $order->update(['status' => $status])
        };

        $this->dispatch('order-updated', message: "Order {$order->order_number} status updated to {$status}");
    }

    public function getOrders()
    {
        return ProductOrder::query()
            ->with([
                'customer',
                'items.product',
                'items.warehouse',
                'payments',
                'platform',
                'platformAccount',
            ])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('order_number', 'like', '%'.$this->search.'%')
                        ->orWhere('platform_order_id', 'like', '%'.$this->search.'%')
                        ->orWhere('platform_order_number', 'like', '%'.$this->search.'%')
                        ->orWhere('customer_name', 'like', '%'.$this->search.'%')
                        ->orWhere('guest_email', 'like', '%'.$this->search.'%')
                        ->orWhereHas('customer', function ($customerQuery) {
                            $customerQuery->where('name', 'like', '%'.$this->search.'%')
                                ->orWhere('email', 'like', '%'.$this->search.'%');
                        })
                        ->orWhereRaw("JSON_EXTRACT(metadata, '$.package_name') LIKE ?", ['%'.$this->search.'%']);
                });
            })
            ->when($this->activeTab !== 'all', function ($query) {
                $query->where('status', $this->activeTab);
            })
            ->when($this->orderTypeFilter, function ($query) {
                $query->where('order_type', $this->orderTypeFilter);
            })
            ->when($this->productFilter, function ($query) {
                if (str_starts_with($this->productFilter, 'package:')) {
                    // Filter by specific package ID in order items
                    $packageId = str_replace('package:', '', $this->productFilter);
                    $query->whereHas('items', function ($itemQuery) use ($packageId) {
                        $itemQuery->where('package_id', $packageId);
                    });
                } else {
                    // Filter by product ID
                    $query->whereHas('items', function ($itemQuery) {
                        $itemQuery->where('product_id', $this->productFilter);
                    });
                }
            })
            ->when($this->dateFilter, function ($query) {
                match ($this->dateFilter) {
                    'today' => $query->whereDate('created_at', today()),
                    'week' => $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]),
                    'month' => $query->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year),
                    'year' => $query->whereYear('created_at', now()->year),
                    default => $query
                };
            })
            ->orderBy($this->sortBy, $this->sortDirection)
            ->paginate(20);
    }

    public function getOrderStatuses(): array
    {
        return [
            'draft' => 'Draft',
            'pending' => 'Pending',
            'confirmed' => 'Confirmed',
            'processing' => 'Processing',
            'partially_shipped' => 'Partially Shipped',
            'shipped' => 'Shipped',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
            'refunded' => 'Refunded',
            'returned' => 'Returned',
        ];
    }

    public function getStatusColor(string $status): string
    {
        return match ($status) {
            'draft' => 'gray',
            'pending' => 'yellow',
            'confirmed' => 'blue',
            'processing' => 'purple',
            'partially_shipped' => 'orange',
            'shipped' => 'cyan',
            'delivered' => 'green',
            'cancelled', 'refunded', 'returned' => 'red',
            default => 'gray'
        };
    }

    public function getProductsAndPackages(): array
    {
        $items = [];

        // Get only products that have been ordered
        $productIds = \App\Models\ProductOrderItem::query()
            ->whereNotNull('product_id')
            ->distinct()
            ->pluck('product_id');

        $products = \App\Models\Product::query()
            ->whereIn('id', $productIds)
            ->orderBy('name')
            ->get(['id', 'name', 'sku']);

        foreach ($products as $product) {
            $items[] = [
                'value' => $product->id,
                'label' => $product->name.($product->sku ? " ({$product->sku})" : ''),
                'type' => 'product',
            ];
        }

        // Get only packages that have been ordered
        $packageIds = \App\Models\ProductOrderItem::query()
            ->whereNotNull('package_id')
            ->distinct()
            ->pluck('package_id');

        $packages = \App\Models\Package::query()
            ->whereIn('id', $packageIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        foreach ($packages as $package) {
            $items[] = [
                'value' => 'package:'.$package->id,
                'label' => $package->name.' (Package)',
                'type' => 'package',
            ];
        }

        return $items;
    }

    public function getStatusCount(string $status): int
    {
        if ($status === 'all') {
            return ProductOrder::count();
        }

        return ProductOrder::where('status', $status)->count();
    }

    public function getActionNeededStats(): array
    {
        return [
            'pending_confirmation' => ProductOrder::where('status', 'pending')->count(),
            'unpaid_orders' => ProductOrder::whereHas('payments', function ($query) {
                $query->where('status', '!=', 'paid');
            })->whereNotIn('status', ['cancelled', 'refunded'])->count(),
            'processing' => ProductOrder::where('status', 'processing')->count(),
            'ready_to_ship' => ProductOrder::where('status', 'confirmed')->count(),
        ];
    }
}; ?>

<div>
    <!-- Header -->
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Orders & Package Sales</flux:heading>
            <flux:text class="mt-2">Manage customer orders including product purchases and package sales</flux:text>
        </div>

        <div class="flex gap-3">
            <flux:button variant="primary" :href="route('admin.orders.create')" wire:navigate>
                <div class="flex items-center justify-center">
                    <flux:icon name="plus" class="w-4 h-4 mr-2" />
                    Create Order
                </div>
            </flux:button>
        </div>
    </div>

    <!-- Action Needed Section -->
    @php
        $actionStats = $this->getActionNeededStats();
        $totalActionNeeded = array_sum($actionStats);
    @endphp

    @if($totalActionNeeded > 0)
        <div class="mb-6 bg-gradient-to-r from-cyan-50 to-blue-50 border-2 border-cyan-400 rounded-lg p-6">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                    <flux:icon name="exclamation-triangle" class="w-5 h-5 text-cyan-600" />
                    <flux:heading size="lg" class="text-cyan-900">Action Needed</flux:heading>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                @if($actionStats['pending_confirmation'] > 0)
                    <button wire:click="$set('activeTab', 'pending')" class="bg-white rounded-lg p-4 text-left hover:shadow-md transition-shadow border border-gray-200">
                        <flux:text size="sm" class="text-gray-600">Pending Confirmation</flux:text>
                        <div class="flex items-center justify-between mt-2">
                            <flux:text class="text-2xl font-bold text-yellow-600">{{ $actionStats['pending_confirmation'] }}</flux:text>
                            <flux:badge color="yellow" size="sm">Action Required</flux:badge>
                        </div>
                    </button>
                @endif

                @if($actionStats['unpaid_orders'] > 0)
                    <div class="bg-white rounded-lg p-4 border border-gray-200">
                        <flux:text size="sm" class="text-gray-600">Unpaid Orders</flux:text>
                        <div class="flex items-center justify-between mt-2">
                            <flux:text class="text-2xl font-bold text-red-600">{{ $actionStats['unpaid_orders'] }}</flux:text>
                            <flux:badge color="red" size="sm">Payment Due</flux:badge>
                        </div>
                    </div>
                @endif

                @if($actionStats['processing'] > 0)
                    <button wire:click="$set('activeTab', 'processing')" class="bg-white rounded-lg p-4 text-left hover:shadow-md transition-shadow border border-gray-200">
                        <flux:text size="sm" class="text-gray-600">Processing</flux:text>
                        <div class="flex items-center justify-between mt-2">
                            <flux:text class="text-2xl font-bold text-purple-600">{{ $actionStats['processing'] }}</flux:text>
                            <flux:badge color="purple" size="sm">In Progress</flux:badge>
                        </div>
                    </button>
                @endif

                @if($actionStats['ready_to_ship'] > 0)
                    <button wire:click="$set('activeTab', 'confirmed')" class="bg-white rounded-lg p-4 text-left hover:shadow-md transition-shadow border border-gray-200">
                        <flux:text size="sm" class="text-gray-600">Ready to Ship</flux:text>
                        <div class="flex items-center justify-between mt-2">
                            <flux:text class="text-2xl font-bold text-blue-600">{{ $actionStats['ready_to_ship'] }}</flux:text>
                            <flux:badge color="blue" size="sm">Ready</flux:badge>
                        </div>
                    </button>
                @endif
            </div>
        </div>
    @endif

    <!-- Status Tabs -->
    <div class="mb-6 bg-white rounded-lg border border-gray-200">
        <div class="border-b border-gray-200">
            <nav class="flex gap-4 px-6" aria-label="Tabs">
                <button
                    wire:click="$set('activeTab', 'all')"
                    class="py-4 px-1 border-b-2 font-medium text-sm transition-colors {{ $activeTab === 'all' ? 'border-cyan-500 text-cyan-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}"
                >
                    All
                    <flux:badge size="sm" class="ml-2">{{ $this->getStatusCount('all') }}</flux:badge>
                </button>

                <button
                    wire:click="$set('activeTab', 'pending')"
                    class="py-4 px-1 border-b-2 font-medium text-sm transition-colors {{ $activeTab === 'pending' ? 'border-cyan-500 text-cyan-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}"
                >
                    Pending
                    <flux:badge size="sm" color="yellow" class="ml-2">{{ $this->getStatusCount('pending') }}</flux:badge>
                </button>

                <button
                    wire:click="$set('activeTab', 'confirmed')"
                    class="py-4 px-1 border-b-2 font-medium text-sm transition-colors {{ $activeTab === 'confirmed' ? 'border-cyan-500 text-cyan-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}"
                >
                    Confirmed
                    <flux:badge size="sm" color="blue" class="ml-2">{{ $this->getStatusCount('confirmed') }}</flux:badge>
                </button>

                <button
                    wire:click="$set('activeTab', 'processing')"
                    class="py-4 px-1 border-b-2 font-medium text-sm transition-colors {{ $activeTab === 'processing' ? 'border-cyan-500 text-cyan-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}"
                >
                    Processing
                    <flux:badge size="sm" color="purple" class="ml-2">{{ $this->getStatusCount('processing') }}</flux:badge>
                </button>

                <button
                    wire:click="$set('activeTab', 'shipped')"
                    class="py-4 px-1 border-b-2 font-medium text-sm transition-colors {{ $activeTab === 'shipped' ? 'border-cyan-500 text-cyan-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}"
                >
                    Shipped
                    <flux:badge size="sm" color="cyan" class="ml-2">{{ $this->getStatusCount('shipped') }}</flux:badge>
                </button>

                <button
                    wire:click="$set('activeTab', 'delivered')"
                    class="py-4 px-1 border-b-2 font-medium text-sm transition-colors {{ $activeTab === 'delivered' ? 'border-cyan-500 text-cyan-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}"
                >
                    Delivered
                    <flux:badge size="sm" color="green" class="ml-2">{{ $this->getStatusCount('delivered') }}</flux:badge>
                </button>

                <button
                    wire:click="$set('activeTab', 'cancelled')"
                    class="py-4 px-1 border-b-2 font-medium text-sm transition-colors {{ $activeTab === 'cancelled' ? 'border-cyan-500 text-cyan-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}"
                >
                    Cancelled
                    <flux:badge size="sm" color="red" class="ml-2">{{ $this->getStatusCount('cancelled') }}</flux:badge>
                </button>
            </nav>
        </div>
    </div>

    <!-- Filters -->
    <div class="mb-6 bg-white rounded-lg border p-4">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <!-- Search -->
            <div class="md:col-span-2">
                <flux:label>Search</flux:label>
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Order number, customer name, email..."
                    class="w-full"
                />
            </div>

            <!-- Order Type Filter -->
            <div>
                <flux:label>Order Type</flux:label>
                <flux:select wire:model.live="orderTypeFilter" placeholder="All Types">
                    <option value="">All Types</option>
                    <option value="retail">Retail Orders</option>
                    <option value="wholesale">Wholesale Orders</option>
                    <option value="b2b">B2B Orders</option>
                    <option value="package">Package Orders</option>
                </flux:select>
            </div>

            <!-- Product/Package Filter -->
            <div>
                <flux:label>Product/Package</flux:label>
                <flux:select wire:model.live="productFilter" placeholder="All Products">
                    <option value="">All Products & Packages</option>
                    @foreach($this->getProductsAndPackages() as $item)
                        <option value="{{ $item['value'] }}">{{ $item['label'] }}</option>
                    @endforeach
                </flux:select>
            </div>

            <!-- Date Filter -->
            <div>
                <flux:label>Period</flux:label>
                <flux:select wire:model.live="dateFilter" placeholder="All Time">
                    <option value="">All Time</option>
                    <option value="today">Today</option>
                    <option value="week">This Week</option>
                    <option value="month">This Month</option>
                    <option value="year">This Year</option>
                </flux:select>
            </div>
        </div>

        <!-- Filter Actions -->
        <div class="flex items-center justify-between mt-4 pt-4 border-t border-gray-200">
            <div class="flex items-center gap-2">
                @if($search || $orderTypeFilter || $productFilter || $dateFilter)
                    <flux:text size="sm" class="text-gray-600">
                        Active filters:
                    </flux:text>
                    @if($search)
                        <flux:badge color="gray">
                            Search: {{ Str::limit($search, 20) }}
                            <button wire:click="$set('search', '')" class="ml-1 hover:text-red-600">×</button>
                        </flux:badge>
                    @endif
                    @if($orderTypeFilter)
                        <flux:badge color="gray">
                            Type: {{ ucfirst($orderTypeFilter) }}
                            <button wire:click="$set('orderTypeFilter', '')" class="ml-1 hover:text-red-600">×</button>
                        </flux:badge>
                    @endif
                    @if($productFilter)
                        <flux:badge color="gray">
                            Product/Package
                            <button wire:click="$set('productFilter', '')" class="ml-1 hover:text-red-600">×</button>
                        </flux:badge>
                    @endif
                    @if($dateFilter)
                        <flux:badge color="gray">
                            Period: {{ ucfirst($dateFilter) }}
                            <button wire:click="$set('dateFilter', '')" class="ml-1 hover:text-red-600">×</button>
                        </flux:badge>
                    @endif
                    <flux:button variant="ghost" size="sm" wire:click="$set('search', ''); $set('orderTypeFilter', ''); $set('productFilter', ''); $set('dateFilter', '')">
                        Clear all
                    </flux:button>
                @endif
            </div>

            <div class="flex items-center gap-2">
                <flux:button variant="outline" wire:click="$refresh" size="sm">
                    <div class="flex items-center justify-center">
                        <flux:icon name="arrow-path" class="w-4 h-4 mr-1" />
                        Refresh
                    </div>
                </flux:button>
                <flux:button variant="outline" size="sm">
                    <div class="flex items-center justify-center">
                        <flux:icon name="arrow-down-tray" class="w-4 h-4 mr-1" />
                        Export
                    </div>
                </flux:button>
            </div>
        </div>
    </div>

    <!-- Orders Table -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full border-collapse border-0">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <button wire:click="sortBy('order_number')" class="flex items-center space-x-1 hover:text-gray-700">
                                <span>Order</span>
                                @if($sortBy === 'order_number')
                                    <flux:icon name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-4 h-4" />
                                @endif
                            </button>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Customer
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Items
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <button wire:click="sortBy('total_amount')" class="flex items-center space-x-1 hover:text-gray-700">
                                <span>Total</span>
                                @if($sortBy === 'total_amount')
                                    <flux:icon name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-4 h-4" />
                                @endif
                            </button>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Status
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Payment
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <button wire:click="sortBy('created_at')" class="flex items-center space-x-1 hover:text-gray-700">
                                <span>Date</span>
                                @if($sortBy === 'created_at')
                                    <flux:icon name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-4 h-4" />
                                @endif
                            </button>
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white">
                    @forelse($this->getOrders() as $order)
                        <tr class="border-b border-gray-200 hover:bg-gray-50" wire:key="order-{{ $order->id }}">
                            <!-- Order Number -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div>
                                    <div class="flex items-center space-x-2">
                                        <flux:text class="font-semibold">{{ $order->order_number }}</flux:text>
                                        @if($order->order_type === 'package')
                                            <flux:badge size="xs" color="purple">Package</flux:badge>
                                        @endif
                                    </div>
                                    @if($order->order_type === 'package' && isset($order->metadata['package_name']))
                                        <flux:text size="sm" class="text-purple-600">{{ $order->metadata['package_name'] }}</flux:text>
                                    @elseif($order->customer_notes)
                                        <flux:text size="sm" class="text-gray-500">{{ Str::limit($order->customer_notes, 30) }}</flux:text>
                                    @endif
                                </div>
                            </td>

                            <!-- Customer -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div>
                                    <flux:text class="font-medium">{{ $order->getCustomerName() }}</flux:text>
                                    <flux:text size="sm" class="text-gray-500">{{ $order->getCustomerEmail() }}</flux:text>
                                </div>
                            </td>

                            <!-- Items -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <flux:text>{{ $order->items->count() }} item{{ $order->items->count() !== 1 ? 's' : '' }}</flux:text>
                                <flux:text size="sm" class="text-gray-500">
                                    {{ $order->items->sum('quantity_ordered') }} qty
                                </flux:text>
                            </td>

                            <!-- Total -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <flux:text class="font-semibold">MYR {{ number_format($order->total_amount, 2) }}</flux:text>
                                @if($order->discount_amount > 0)
                                    <flux:text size="sm" class="text-green-600">-MYR {{ number_format($order->discount_amount, 2) }}</flux:text>
                                @endif
                            </td>

                            <!-- Status -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <flux:badge size="sm" color="{{ $this->getStatusColor($order->status) }}">
                                    {{ $this->getOrderStatuses()[$order->status] ?? $order->status }}
                                </flux:badge>
                            </td>

                            <!-- Payment Status -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($order->isPaid())
                                    <flux:badge size="sm" color="green">Paid</flux:badge>
                                @else
                                    <flux:badge size="sm" color="red">Unpaid</flux:badge>
                                @endif
                            </td>

                            <!-- Date -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <flux:text>{{ $order->created_at->format('M j, Y') }}</flux:text>
                                <flux:text size="sm" class="text-gray-500">{{ $order->created_at->format('g:i A') }}</flux:text>
                            </td>

                            <!-- Actions -->
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex items-center justify-end space-x-2">
                                    <flux:button variant="ghost" size="sm" href="{{ route('admin.orders.show', $order) }}">
                                        <flux:icon name="eye" class="w-4 h-4" />
                                    </flux:button>

                                    @if($order->canBeCancelled())
                                        <flux:dropdown>
                                            <flux:button variant="ghost" size="sm">
                                                <flux:icon name="ellipsis-horizontal" class="w-4 h-4" />
                                            </flux:button>

                                            <flux:menu>
                                                @if($order->status === 'pending')
                                                    <flux:menu.item wire:click="updateOrderStatus({{ $order->id }}, 'confirmed')">
                                                        <flux:icon name="check" class="w-4 h-4 mr-2" />
                                                        Mark as Confirmed
                                                    </flux:menu.item>
                                                @endif

                                                @if(in_array($order->status, ['confirmed', 'pending']))
                                                    <flux:menu.item wire:click="updateOrderStatus({{ $order->id }}, 'processing')">
                                                        <flux:icon name="cog" class="w-4 h-4 mr-2" />
                                                        Mark as Processing
                                                    </flux:menu.item>
                                                @endif

                                                @if(in_array($order->status, ['processing', 'confirmed']))
                                                    <flux:menu.item wire:click="updateOrderStatus({{ $order->id }}, 'shipped')">
                                                        <flux:icon name="truck" class="w-4 h-4 mr-2" />
                                                        Mark as Shipped
                                                    </flux:menu.item>
                                                @endif

                                                @if($order->status === 'shipped')
                                                    <flux:menu.item wire:click="updateOrderStatus({{ $order->id }}, 'delivered')">
                                                        <flux:icon name="check-circle" class="w-4 h-4 mr-2" />
                                                        Mark as Delivered
                                                    </flux:menu.item>
                                                @endif

                                                <flux:menu.separator />

                                                <flux:menu.item wire:click="updateOrderStatus({{ $order->id }}, 'cancelled')" class="text-red-600">
                                                    <flux:icon name="x-circle" class="w-4 h-4 mr-2" />
                                                    Cancel Order
                                                </flux:menu.item>
                                            </flux:menu>
                                        </flux:dropdown>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center">
                                <div class="text-gray-500">
                                    <flux:icon name="shopping-bag" class="w-12 h-12 mx-auto mb-4 text-gray-300" />
                                    <flux:text>No orders found</flux:text>
                                    @if($search || $activeTab !== 'all' || $dateFilter || $orderTypeFilter || $productFilter)
                                        <flux:button variant="ghost" wire:click="$set('search', ''); $set('activeTab', 'all'); $set('dateFilter', ''); $set('orderTypeFilter', ''); $set('productFilter', '')" class="mt-2">
                                            Clear filters
                                        </flux:button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($this->getOrders()->hasPages())
            <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
                {{ $this->getOrders()->links() }}
            </div>
        @endif
    </div>

    <!-- Order Summary Stats -->
    <div class="mt-6 grid grid-cols-1 md:grid-cols-4 gap-4">
        @php
            $totalOrders = ProductOrder::count();
            $pendingOrders = ProductOrder::where('status', 'pending')->count();
            $todayOrders = ProductOrder::whereDate('created_at', today())->count();
            $totalRevenue = ProductOrder::whereNotIn('status', ['cancelled', 'refunded'])->sum('total_amount');
        @endphp

        <div class="bg-white rounded-lg border p-4">
            <flux:text size="sm" class="text-gray-600">Total Orders</flux:text>
            <flux:text class="text-2xl font-bold">{{ number_format($totalOrders) }}</flux:text>
        </div>

        <div class="bg-white rounded-lg border p-4">
            <flux:text size="sm" class="text-gray-600">Pending Orders</flux:text>
            <flux:text class="text-2xl font-bold text-yellow-600">{{ number_format($pendingOrders) }}</flux:text>
        </div>

        <div class="bg-white rounded-lg border p-4">
            <flux:text size="sm" class="text-gray-600">Today's Orders</flux:text>
            <flux:text class="text-2xl font-bold text-blue-600">{{ number_format($todayOrders) }}</flux:text>
        </div>

        <div class="bg-white rounded-lg border p-4">
            <flux:text size="sm" class="text-gray-600">Total Revenue</flux:text>
            <flux:text class="text-2xl font-bold text-green-600">MYR {{ number_format($totalRevenue, 2) }}</flux:text>
        </div>
    </div>
</div>

<script>
    document.addEventListener('livewire:init', function () {
        Livewire.on('order-updated', (event) => {
            // You can replace this with toast notifications
            alert(event.message);
        });
    });
</script>