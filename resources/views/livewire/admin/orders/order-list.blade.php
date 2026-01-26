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

    public string $sourceTab = 'all';

    public string $productFilter = '';

    public string $dateFilter = '';

    public string $sortBy = 'created_at';

    public string $sortDirection = 'desc';

    // Inline phone editing
    public ?int $editingPhoneOrderId = null;

    public string $editingPhoneValue = '';

    public function startEditingPhone(int $orderId, ?string $currentPhone): void
    {
        $this->editingPhoneOrderId = $orderId;
        $this->editingPhoneValue = $currentPhone ?? '';
    }

    public function savePhone(): void
    {
        if ($this->editingPhoneOrderId) {
            $order = ProductOrder::findOrFail($this->editingPhoneOrderId);
            $order->update(['customer_phone' => $this->editingPhoneValue]);

            $this->dispatch('order-updated', message: "Phone number updated for order {$order->order_number}");
        }

        $this->cancelEditingPhone();
    }

    public function cancelEditingPhone(): void
    {
        $this->editingPhoneOrderId = null;
        $this->editingPhoneValue = '';
    }

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

    public function updatingSourceTab(): void
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
                'student',
                'agent',
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
            ->when($this->sourceTab !== 'all', function ($query) {
                match ($this->sourceTab) {
                    'platform' => $query->whereNotNull('platform_id'),
                    'agent_company' => $query->whereNull('platform_id')->where(function ($q) {
                        $q->where('source', '!=', 'funnel')
                          ->orWhereNull('source');
                    }),
                    'funnel' => $query->where('source', 'funnel'),
                    default => $query
                };
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

    public function getOrderSource(ProductOrder $order): array
    {
        if ($order->platform_id) {
            return [
                'type' => 'platform',
                'label' => $order->platform?->name ?? 'Platform',
                'color' => 'purple',
                'icon' => 'globe-alt',
            ];
        }

        if ($order->agent_id) {
            return [
                'type' => 'agent',
                'label' => 'Agent',
                'color' => 'blue',
                'icon' => 'user-group',
            ];
        }

        if ($order->source === 'funnel') {
            return [
                'type' => 'funnel',
                'label' => 'Sales Funnel',
                'color' => 'green',
                'icon' => 'funnel',
            ];
        }

        return [
            'type' => 'company',
            'label' => 'Company',
            'color' => 'cyan',
            'icon' => 'building-office',
        ];
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
        $query = ProductOrder::query();

        // Apply source filter based on current sourceTab
        if ($this->sourceTab !== 'all') {
            match ($this->sourceTab) {
                'platform' => $query->whereNotNull('platform_id'),
                'agent_company' => $query->whereNull('platform_id')->where(function ($q) {
                    $q->where('source', '!=', 'funnel')
                      ->orWhereNull('source');
                }),
                'funnel' => $query->where('source', 'funnel'),
                default => $query
            };
        }

        // Apply status filter
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        return $query->count();
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

    public function getSourceCounts(): array
    {
        $counts = ProductOrder::selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN platform_id IS NOT NULL THEN 1 ELSE 0 END) as platform,
            SUM(CASE WHEN source = 'funnel' THEN 1 ELSE 0 END) as funnel,
            SUM(CASE WHEN platform_id IS NULL AND (source IS NULL OR source != 'funnel') THEN 1 ELSE 0 END) as agent_company
        ")->first();

        return [
            'all' => $counts->total ?? 0,
            'platform' => $counts->platform ?? 0,
            'agent_company' => $counts->agent_company ?? 0,
            'funnel' => $counts->funnel ?? 0,
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

    <!-- Action Needed Section - Compact Inline -->
    @php
        $actionStats = $this->getActionNeededStats();
        $totalActionNeeded = array_sum($actionStats);
    @endphp

    @if($totalActionNeeded > 0)
        <div class="mb-4 flex items-center gap-3 flex-wrap">
            <div class="flex items-center gap-2">
                <flux:icon name="exclamation-triangle" class="w-4 h-4 text-amber-600 dark:text-amber-400" />
                <flux:text size="sm" class="font-medium text-gray-700 dark:text-gray-300">Action Needed:</flux:text>
            </div>

            @if($actionStats['pending_confirmation'] > 0)
                <button wire:click="$set('activeTab', 'pending')"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium transition-colors
                           bg-yellow-100 text-yellow-800 hover:bg-yellow-200 dark:bg-yellow-900/30 dark:text-yellow-400 dark:hover:bg-yellow-900/50">
                    <span class="w-2 h-2 rounded-full bg-yellow-500"></span>
                    Pending
                    <flux:badge size="sm" color="yellow">{{ $actionStats['pending_confirmation'] }}</flux:badge>
                </button>
            @endif

            @if($actionStats['unpaid_orders'] > 0)
                <div class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium
                            bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400">
                    <span class="w-2 h-2 rounded-full bg-red-500"></span>
                    Unpaid
                    <flux:badge size="sm" color="red">{{ $actionStats['unpaid_orders'] }}</flux:badge>
                </div>
            @endif

            @if($actionStats['processing'] > 0)
                <button wire:click="$set('activeTab', 'processing')"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium transition-colors
                           bg-purple-100 text-purple-800 hover:bg-purple-200 dark:bg-purple-900/30 dark:text-purple-400 dark:hover:bg-purple-900/50">
                    <span class="w-2 h-2 rounded-full bg-purple-500"></span>
                    Processing
                    <flux:badge size="sm" color="purple">{{ $actionStats['processing'] }}</flux:badge>
                </button>
            @endif

            @if($actionStats['ready_to_ship'] > 0)
                <button wire:click="$set('activeTab', 'confirmed')"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium transition-colors
                           bg-blue-100 text-blue-800 hover:bg-blue-200 dark:bg-blue-900/30 dark:text-blue-400 dark:hover:bg-blue-900/50">
                    <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                    Ready to Ship
                    <flux:badge size="sm" color="blue">{{ $actionStats['ready_to_ship'] }}</flux:badge>
                </button>
            @endif
        </div>
    @endif

    <!-- Source Tabs -->
    @php
        $sourceCounts = $this->getSourceCounts();
    @endphp
    <div class="mb-4 bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700">
        <div class="border-b border-gray-200 dark:border-zinc-700">
            <nav class="flex gap-4 px-6" aria-label="Source Tabs">
                <button wire:click="$set('sourceTab', 'all')"
                    class="py-3 px-1 border-b-2 font-medium text-sm transition-colors {{ $sourceTab === 'all' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-zinc-600' }}">
                    <div class="flex items-center gap-2">
                        <flux:icon name="squares-2x2" class="w-4 h-4" />
                        All Sources
                        <flux:badge size="sm">{{ $sourceCounts['all'] }}</flux:badge>
                    </div>
                </button>

                <button wire:click="$set('sourceTab', 'platform')"
                    class="py-3 px-1 border-b-2 font-medium text-sm transition-colors {{ $sourceTab === 'platform' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-zinc-600' }}">
                    <div class="flex items-center gap-2">
                        <flux:icon name="globe-alt" class="w-4 h-4" />
                        Platform Orders
                        <flux:badge size="sm" color="purple">{{ $sourceCounts['platform'] }}</flux:badge>
                    </div>
                </button>

                <button wire:click="$set('sourceTab', 'agent_company')"
                    class="py-3 px-1 border-b-2 font-medium text-sm transition-colors {{ $sourceTab === 'agent_company' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-zinc-600' }}">
                    <div class="flex items-center gap-2">
                        <flux:icon name="building-office" class="w-4 h-4" />
                        Agent & Company
                        <flux:badge size="sm" color="blue">{{ $sourceCounts['agent_company'] }}</flux:badge>
                    </div>
                </button>

                <button wire:click="$set('sourceTab', 'funnel')"
                    class="py-3 px-1 border-b-2 font-medium text-sm transition-colors {{ $sourceTab === 'funnel' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-zinc-600' }}">
                    <div class="flex items-center gap-2">
                        <flux:icon name="funnel" class="w-4 h-4" />
                        Sales Funnel
                        <flux:badge size="sm" color="green">{{ $sourceCounts['funnel'] }}</flux:badge>
                    </div>
                </button>
            </nav>
        </div>
    </div>

    <!-- Status Tabs -->
    <div class="mb-6 bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700">
        <div class="border-b border-gray-200 dark:border-zinc-700">
            <nav class="flex gap-4 px-6" aria-label="Tabs">
                <button
                    wire:click="$set('activeTab', 'all')"
                    class="py-4 px-1 border-b-2 font-medium text-sm transition-colors {{ $activeTab === 'all' ? 'border-cyan-500 text-cyan-600 dark:text-cyan-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-zinc-600' }}"
                >
                    All
                    <flux:badge size="sm" class="ml-2">{{ $this->getStatusCount('all') }}</flux:badge>
                </button>

                <button
                    wire:click="$set('activeTab', 'pending')"
                    class="py-4 px-1 border-b-2 font-medium text-sm transition-colors {{ $activeTab === 'pending' ? 'border-cyan-500 text-cyan-600 dark:text-cyan-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-zinc-600' }}"
                >
                    Pending
                    <flux:badge size="sm" color="yellow" class="ml-2">{{ $this->getStatusCount('pending') }}</flux:badge>
                </button>

                <button
                    wire:click="$set('activeTab', 'confirmed')"
                    class="py-4 px-1 border-b-2 font-medium text-sm transition-colors {{ $activeTab === 'confirmed' ? 'border-cyan-500 text-cyan-600 dark:text-cyan-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-zinc-600' }}"
                >
                    Confirmed
                    <flux:badge size="sm" color="blue" class="ml-2">{{ $this->getStatusCount('confirmed') }}</flux:badge>
                </button>

                <button
                    wire:click="$set('activeTab', 'processing')"
                    class="py-4 px-1 border-b-2 font-medium text-sm transition-colors {{ $activeTab === 'processing' ? 'border-cyan-500 text-cyan-600 dark:text-cyan-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-zinc-600' }}"
                >
                    Processing
                    <flux:badge size="sm" color="purple" class="ml-2">{{ $this->getStatusCount('processing') }}</flux:badge>
                </button>

                <button
                    wire:click="$set('activeTab', 'shipped')"
                    class="py-4 px-1 border-b-2 font-medium text-sm transition-colors {{ $activeTab === 'shipped' ? 'border-cyan-500 text-cyan-600 dark:text-cyan-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-zinc-600' }}"
                >
                    Shipped
                    <flux:badge size="sm" color="cyan" class="ml-2">{{ $this->getStatusCount('shipped') }}</flux:badge>
                </button>

                <button
                    wire:click="$set('activeTab', 'delivered')"
                    class="py-4 px-1 border-b-2 font-medium text-sm transition-colors {{ $activeTab === 'delivered' ? 'border-cyan-500 text-cyan-600 dark:text-cyan-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-zinc-600' }}"
                >
                    Delivered
                    <flux:badge size="sm" color="green" class="ml-2">{{ $this->getStatusCount('delivered') }}</flux:badge>
                </button>

                <button
                    wire:click="$set('activeTab', 'cancelled')"
                    class="py-4 px-1 border-b-2 font-medium text-sm transition-colors {{ $activeTab === 'cancelled' ? 'border-cyan-500 text-cyan-600 dark:text-cyan-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-zinc-600' }}"
                >
                    Cancelled
                    <flux:badge size="sm" color="red" class="ml-2">{{ $this->getStatusCount('cancelled') }}</flux:badge>
                </button>
            </nav>
        </div>
    </div>

    <!-- Filters -->
    <div class="mb-6 bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <!-- Search -->
            <div class="md:col-span-2">
                <flux:label>Search</flux:label>
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Order number, customer name, email..."
                    class="w-full"
                />
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
        <div class="flex items-center justify-between mt-4 pt-4 border-t border-gray-200 dark:border-zinc-700">
            <div class="flex items-center gap-2">
                @if($search || $sourceTab !== 'all' || $productFilter || $dateFilter)
                    <flux:text size="sm" class="text-gray-600 dark:text-gray-400">
                        Active filters:
                    </flux:text>
                    @if($search)
                        <flux:badge color="gray">
                            Search: {{ Str::limit($search, 20) }}
                            <button wire:click="$set('search', '')" class="ml-1 hover:text-red-600">×</button>
                        </flux:badge>
                    @endif
                    @if($sourceTab !== 'all')
                        <flux:badge color="gray">
                            Source: {{ match($sourceTab) {
                                'platform' => 'Platform Orders',
                                'agent_company' => 'Agent & Company',
                                'funnel' => 'Sales Funnel',
                                default => $sourceTab
                            } }}
                            <button wire:click="$set('sourceTab', 'all')" class="ml-1 hover:text-red-600">×</button>
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
                    <flux:button variant="ghost" size="sm" wire:click="$set('search', ''); $set('sourceTab', 'all'); $set('productFilter', ''); $set('dateFilter', '')">
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
    <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full border-collapse border-0">
                <thead class="bg-gray-50 dark:bg-zinc-700/50 border-b border-gray-200 dark:border-zinc-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            <button wire:click="sortBy('order_number')" class="flex items-center space-x-1 hover:text-gray-700 dark:hover:text-gray-300">
                                <span>Order</span>
                                @if($sortBy === 'order_number')
                                    <flux:icon name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-4 h-4" />
                                @endif
                            </button>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Source
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Customer
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Phone
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Items
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            <button wire:click="sortBy('total_amount')" class="flex items-center space-x-1 hover:text-gray-700 dark:hover:text-gray-300">
                                <span>Total</span>
                                @if($sortBy === 'total_amount')
                                    <flux:icon name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-4 h-4" />
                                @endif
                            </button>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Status
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Payment
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            <button wire:click="sortBy('created_at')" class="flex items-center space-x-1 hover:text-gray-700 dark:hover:text-gray-300">
                                <span>Date</span>
                                @if($sortBy === 'created_at')
                                    <flux:icon name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-4 h-4" />
                                @endif
                            </button>
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-zinc-800">
                    @forelse($this->getOrders() as $order)
                        <tr class="border-b border-gray-200 dark:border-zinc-700 hover:bg-gray-50 dark:hover:bg-zinc-700/50" wire:key="order-{{ $order->id }}">
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

                            <!-- Source -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                @php
                                    $source = $this->getOrderSource($order);
                                @endphp
                                @if($order->platform_id && $order->platformAccount)
                                    <a href="{{ route('platforms.accounts.show', ['platform' => $order->platform, 'account' => $order->platformAccount]) }}?tab=orders" class="block group">
                                        <div class="flex items-center space-x-2">
                                            <flux:badge size="sm" color="{{ $source['color'] }}" class="group-hover:opacity-80 transition-opacity">
                                                <div class="flex items-center justify-center">
                                                    <flux:icon name="{{ $source['icon'] }}" class="w-3 h-3 mr-1" />
                                                    {{ $source['label'] }}
                                                </div>
                                            </flux:badge>
                                        </div>
                                        <flux:text size="xs" class="text-gray-500 mt-1 group-hover:text-blue-600 transition-colors">{{ $order->platformAccount->name }}</flux:text>
                                    </a>
                                @elseif($order->agent_id && $order->agent)
                                    <a href="{{ route('agents.show', $order->agent) }}" class="block group">
                                        <div class="flex items-center space-x-2">
                                            <flux:badge size="sm" color="{{ $source['color'] }}" class="group-hover:opacity-80 transition-opacity">
                                                <div class="flex items-center justify-center">
                                                    <flux:icon name="{{ $source['icon'] }}" class="w-3 h-3 mr-1" />
                                                    {{ $source['label'] }}
                                                </div>
                                            </flux:badge>
                                        </div>
                                        <flux:text size="xs" class="text-gray-500 mt-1 group-hover:text-blue-600 transition-colors">{{ $order->agent->name }}</flux:text>
                                    </a>
                                @elseif($order->source === 'funnel')
                                    <div>
                                        <div class="flex items-center space-x-2">
                                            <flux:badge size="sm" color="{{ $source['color'] }}">
                                                <div class="flex items-center justify-center">
                                                    <flux:icon name="{{ $source['icon'] }}" class="w-3 h-3 mr-1" />
                                                    {{ $source['label'] }}
                                                </div>
                                            </flux:badge>
                                        </div>
                                        @if($order->source_reference)
                                            <flux:text size="xs" class="text-gray-500 mt-1">{{ $order->source_reference }}</flux:text>
                                        @endif
                                    </div>
                                @else
                                    <div class="flex items-center space-x-2">
                                        <flux:badge size="sm" color="{{ $source['color'] }}">
                                            <div class="flex items-center justify-center">
                                                <flux:icon name="{{ $source['icon'] }}" class="w-3 h-3 mr-1" />
                                                {{ $source['label'] }}
                                            </div>
                                        </flux:badge>
                                    </div>
                                @endif
                            </td>

                            <!-- Customer -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($order->student)
                                    <a href="{{ route('students.show', $order->student) }}" class="block group">
                                        <flux:text class="font-medium group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">{{ $order->getCustomerName() }}</flux:text>
                                        <flux:text size="sm" class="text-gray-500">{{ $order->getCustomerEmail() }}</flux:text>
                                    </a>
                                @else
                                    <div>
                                        <flux:text class="font-medium">{{ $order->getCustomerName() }}</flux:text>
                                        <flux:text size="sm" class="text-gray-500">{{ $order->getCustomerEmail() }}</flux:text>
                                    </div>
                                @endif
                            </td>

                            <!-- Phone -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($editingPhoneOrderId === $order->id)
                                    <div class="flex items-center space-x-1">
                                        <input
                                            type="text"
                                            wire:model="editingPhoneValue"
                                            wire:keydown.enter="savePhone"
                                            wire:keydown.escape="cancelEditingPhone"
                                            class="w-36 px-2 py-1 text-sm border border-gray-300 dark:border-zinc-600 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-zinc-700 dark:text-white"
                                            placeholder="Phone number"
                                            autofocus
                                        />
                                        <button wire:click="savePhone" class="p-1 text-green-600 hover:text-green-700 hover:bg-green-50 dark:hover:bg-green-900/20 rounded">
                                            <flux:icon name="check" class="w-4 h-4" />
                                        </button>
                                        <button wire:click="cancelEditingPhone" class="p-1 text-red-600 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-900/20 rounded">
                                            <flux:icon name="x-mark" class="w-4 h-4" />
                                        </button>
                                    </div>
                                @else
                                    <button
                                        wire:click="startEditingPhone({{ $order->id }}, {{ json_encode($order->customer_phone ?? '') }})"
                                        class="group flex items-center space-x-1 hover:text-blue-600 transition-colors"
                                        title="Click to edit phone number"
                                    >
                                        <flux:text size="sm">{{ $order->getCustomerPhone() }}</flux:text>
                                        <flux:icon name="pencil" class="w-3 h-3 opacity-0 group-hover:opacity-100 transition-opacity text-gray-400" />
                                    </button>
                                @endif
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
                            <td colspan="10" class="px-6 py-12 text-center">
                                <div class="text-gray-500 dark:text-gray-400">
                                    <flux:icon name="shopping-bag" class="w-12 h-12 mx-auto mb-4 text-gray-300 dark:text-gray-600" />
                                    <flux:text>No orders found</flux:text>
                                    @if($search || $activeTab !== 'all' || $dateFilter || $sourceTab !== 'all' || $productFilter)
                                        <flux:button variant="ghost" wire:click="$set('search', ''); $set('activeTab', 'all'); $set('dateFilter', ''); $set('sourceTab', 'all'); $set('productFilter', '')" class="mt-2">
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
            <div class="px-6 py-4 border-t border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-700/50">
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

        <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-4">
            <flux:text size="sm" class="text-gray-600 dark:text-gray-400">Total Orders</flux:text>
            <flux:text class="text-2xl font-bold">{{ number_format($totalOrders) }}</flux:text>
        </div>

        <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-4">
            <flux:text size="sm" class="text-gray-600 dark:text-gray-400">Pending Orders</flux:text>
            <flux:text class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">{{ number_format($pendingOrders) }}</flux:text>
        </div>

        <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-4">
            <flux:text size="sm" class="text-gray-600 dark:text-gray-400">Today's Orders</flux:text>
            <flux:text class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ number_format($todayOrders) }}</flux:text>
        </div>

        <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-4">
            <flux:text size="sm" class="text-gray-600 dark:text-gray-400">Total Revenue</flux:text>
            <flux:text class="text-2xl font-bold text-green-600 dark:text-green-400">MYR {{ number_format($totalRevenue, 2) }}</flux:text>
        </div>
    </div>

    <!-- Toast Notification -->
    <div
        x-data="{ show: false, message: '' }"
        x-on:order-updated.window="message = $event.detail.message; show = true; setTimeout(() => show = false, 3000)"
        x-show="show"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 transform translate-y-2"
        x-transition:enter-end="opacity-100 transform translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 transform translate-y-0"
        x-transition:leave-end="opacity-0 transform translate-y-2"
        class="fixed bottom-4 right-4 z-50"
        style="display: none;"
    >
        <div class="bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg flex items-center space-x-3">
            <flux:icon name="check-circle" class="w-5 h-5" />
            <span x-text="message"></span>
        </div>
    </div>
</div>