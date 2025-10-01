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
    public string $statusFilter = '';
    public string $orderTypeFilter = '';
    public string $dateFilter = '';
    public string $sortBy = 'created_at';
    public string $sortDirection = 'desc';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
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
        match($status) {
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
                'platformAccount'
            ])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('order_number', 'like', '%' . $this->search . '%')
                      ->orWhere('platform_order_id', 'like', '%' . $this->search . '%')
                      ->orWhere('platform_order_number', 'like', '%' . $this->search . '%')
                      ->orWhere('customer_name', 'like', '%' . $this->search . '%')
                      ->orWhere('guest_email', 'like', '%' . $this->search . '%')
                      ->orWhereHas('customer', function ($customerQuery) {
                          $customerQuery->where('name', 'like', '%' . $this->search . '%')
                                      ->orWhere('email', 'like', '%' . $this->search . '%');
                      })
                      ->orWhereRaw("JSON_EXTRACT(metadata, '$.package_name') LIKE ?", ['%' . $this->search . '%']);
                });
            })
            ->when($this->statusFilter, function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->when($this->orderTypeFilter, function ($query) {
                if ($this->orderTypeFilter === 'package') {
                    $query->where('order_type', 'package');
                } elseif ($this->orderTypeFilter === 'regular') {
                    $query->where(function ($q) {
                        $q->whereNull('order_type')->orWhere('order_type', '!=', 'package');
                    });
                }
            })
            ->when($this->dateFilter, function ($query) {
                match($this->dateFilter) {
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
        return match($status) {
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
}; ?>

<div>
    <!-- Header -->
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Orders & Package Sales</flux:heading>
            <flux:text class="mt-2">Manage customer orders including product purchases and package sales</flux:text>
        </div>

        <div class="flex space-x-3">
            <flux:button variant="outline">
                <flux:icon name="chart-bar" class="w-4 h-4 mr-2" />
                Reports
            </flux:button>
            <flux:button variant="primary" :href="route('admin.orders.create')" wire:navigate>
                <flux:icon name="plus" class="w-4 h-4 mr-2" />
                Create Order
            </flux:button>
        </div>
    </div>

    <!-- Filters -->
    <div class="mb-6 bg-white rounded-lg border p-4">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <!-- Search -->
            <div>
                <flux:label>Search</flux:label>
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Order number, customer name, email, package name..."
                    class="w-full"
                />
            </div>

            <!-- Status Filter -->
            <div>
                <flux:label>Status</flux:label>
                <flux:select wire:model.live="statusFilter" placeholder="All Statuses">
                    <option value="">All Statuses</option>
                    @foreach($this->getOrderStatuses() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </flux:select>
            </div>

            <!-- Order Type Filter -->
            <div>
                <flux:label>Order Type</flux:label>
                <flux:select wire:model.live="orderTypeFilter" placeholder="All Types">
                    <option value="">All Types</option>
                    <option value="regular">Regular Orders</option>
                    <option value="package">Package Orders</option>
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

            <!-- Actions -->
            <div class="flex items-end space-x-2">
                <flux:button variant="outline" wire:click="$refresh" size="sm">
                    <flux:icon name="arrow-path" class="w-4 h-4 mr-1" />
                    Refresh
                </flux:button>
                <flux:button variant="outline" size="sm">
                    <flux:icon name="arrow-down-tray" class="w-4 h-4 mr-1" />
                    Export
                </flux:button>
            </div>
        </div>
    </div>

    <!-- Orders Table -->
    <div class="bg-white rounded-lg border">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
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
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($this->getOrders() as $order)
                        <tr class="hover:bg-gray-50" wire:key="order-{{ $order->id }}">
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
                                    @if($search || $statusFilter || $dateFilter)
                                        <flux:button variant="ghost" wire:click="$set('search', ''); $set('statusFilter', ''); $set('dateFilter', '')" class="mt-2">
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
            <div class="px-6 py-4 border-t">
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