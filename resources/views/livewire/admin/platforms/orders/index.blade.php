<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Platform;
use App\Models\ProductOrder;
use Carbon\Carbon;

new class extends Component {
    use WithPagination;

    public Platform $platform;

    // Filters
    public $search = '';
    public $status_filter = '';
    public $account_filter = '';
    public $date_range = '';

    // Stats
    public $totalOrders = 0;
    public $totalValue = 0;
    public $statusCounts = [];

    public function mount(Platform $platform)
    {
        $this->platform = $platform;
        $this->loadStats();
    }

    public function loadStats()
    {
        $query = $this->platform->orders();

        $this->totalOrders = $query->count();
        $this->totalValue = $query->sum('total_amount');

        $this->statusCounts = $query->groupBy('status')
            ->selectRaw('status, count(*) as count')
            ->pluck('count', 'status')
            ->toArray();
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedStatusFilter()
    {
        $this->resetPage();
    }

    public function updatedAccountFilter()
    {
        $this->resetPage();
    }

    public function updatedDateRange()
    {
        $this->resetPage();
    }

    public function resetFilters()
    {
        $this->search = '';
        $this->status_filter = '';
        $this->account_filter = '';
        $this->date_range = '';
        $this->resetPage();
    }

    public function with()
    {
        $query = ProductOrder::query()
            ->where('platform_id', $this->platform->id)
            ->with(['platformAccount'])
            ->orderBy('order_date', 'desc');

        // Apply search filter
        if ($this->search) {
            $query->where(function($q) {
                $q->where('platform_order_id', 'like', '%' . $this->search . '%')
                  ->orWhere('platform_order_number', 'like', '%' . $this->search . '%')
                  ->orWhere('customer_name', 'like', '%' . $this->search . '%')
                  ->orWhere('buyer_username', 'like', '%' . $this->search . '%')
                  ->orWhere('tracking_id', 'like', '%' . $this->search . '%');
            });
        }

        // Apply status filter
        if ($this->status_filter) {
            $query->where('status', $this->status_filter);
        }

        // Apply account filter
        if ($this->account_filter) {
            $query->where('platform_account_id', $this->account_filter);
        }

        // Apply date range filter
        if ($this->date_range) {
            switch ($this->date_range) {
                case 'today':
                    $query->whereDate('order_date', today());
                    break;
                case 'week':
                    $query->whereBetween('order_date', [now()->startOfWeek(), now()->endOfWeek()]);
                    break;
                case 'month':
                    $query->whereMonth('order_date', now()->month)
                          ->whereYear('order_date', now()->year);
                    break;
                case 'last_month':
                    $query->whereMonth('order_date', now()->subMonth()->month)
                          ->whereYear('order_date', now()->subMonth()->year);
                    break;
            }
        }

        return [
            'orders' => $query->paginate(20),
            'accounts' => $this->platform->accounts()->orderBy('name')->get(),
        ];
    }

    public function getStatusOptions()
    {
        return [
            'pending' => 'Pending',
            'confirmed' => 'Confirmed',
            'processing' => 'Processing',
            'shipped' => 'Shipped',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
            'refunded' => 'Refunded',
            'completed' => 'Completed',
        ];
    }

    public function getStatusColor($status)
    {
        return match($status) {
            'pending' => 'amber',
            'confirmed' => 'blue',
            'processing' => 'purple',
            'shipped' => 'indigo',
            'delivered' => 'green',
            'completed' => 'green',
            'cancelled' => 'red',
            'refunded' => 'red',
            default => 'zinc',
        };
    }

    public function getStatusIcon($status)
    {
        return match($status) {
            'pending' => 'clock',
            'confirmed' => 'check-circle',
            'processing' => 'cog',
            'shipped' => 'truck',
            'delivered' => 'home',
            'completed' => 'check-badge',
            'cancelled' => 'x-circle',
            'refunded' => 'arrow-uturn-left',
            default => 'question-mark-circle',
        };
    }

    public function exportOrders()
    {
        $query = ProductOrder::query()
            ->where('platform_id', $this->platform->id)
            ->with(['platformAccount']);

        // Apply same filters as the table
        if ($this->search) {
            $query->where(function($q) {
                $q->where('platform_order_id', 'like', '%' . $this->search . '%')
                  ->orWhere('platform_order_number', 'like', '%' . $this->search . '%')
                  ->orWhere('customer_name', 'like', '%' . $this->search . '%')
                  ->orWhere('buyer_username', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->status_filter) {
            $query->where('status', $this->status_filter);
        }

        if ($this->account_filter) {
            $query->where('platform_account_id', $this->account_filter);
        }

        $orders = $query->orderBy('order_date', 'desc')->get();

        $csv = "Order ID,Account,Order Date,Status,Customer Name,Customer Email,Total Amount,Currency,Items Count,Platform Fees,Tracking Number,Import Date\n";

        foreach ($orders as $order) {
            $csv .= implode(',', [
                '"' . $order->platform_order_id . '"',
                '"' . $order->platformAccount->name . '"',
                '"' . ($order->order_date ? $order->order_date->format('Y-m-d H:i:s') : '') . '"',
                '"' . $order->status . '"',
                '"' . ($order->customer_name ?? '') . '"',
                '"' . ($order->guest_email ?? '') . '"',
                $order->total_amount,
                '"' . $order->currency . '"',
                $order->items->count(),
                $order->shipping_cost ?? 0,
                '"' . ($order->tracking_id ?? '') . '"',
                '"' . $order->created_at->format('Y-m-d H:i:s') . '"',
            ]) . "\n";
        }

        return response()->streamDownload(function() use ($csv) {
            echo $csv;
        }, "orders-{$this->platform->slug}-" . now()->format('Y-m-d') . ".csv", [
            'Content-Type' => 'text/csv',
        ]);
    }
}; ?>

<div>
    {{-- Breadcrumb Navigation --}}
    <div class="mb-6">
        <nav class="flex" aria-label="Breadcrumb">
            <ol class="flex items-center space-x-4">
                <li>
                    <div>
                        <flux:button variant="ghost" size="sm" :href="route('platforms.index')" wire:navigate>
                            <div class="flex items-center justify-center">
                                <flux:icon name="chevron-left" class="w-4 h-4 mr-1" />
                                Back to Platforms
                            </div>
                        </flux:button>
                    </div>
                </li>
                <li>
                    <div class="flex items-center">
                        <flux:icon name="chevron-right" class="w-5 h-5 text-zinc-400" />
                        <flux:button variant="ghost" size="sm" :href="route('platforms.show', $platform)" wire:navigate class="ml-4">
                            {{ $platform->display_name }}
                        </flux:button>
                    </div>
                </li>
                <li>
                    <div class="flex items-center">
                        <flux:icon name="chevron-right" class="w-5 h-5 text-zinc-400" />
                        <span class="ml-4 text-sm font-medium text-zinc-500">Orders</span>
                    </div>
                </li>
            </ol>
        </nav>
    </div>

    {{-- Header Section --}}
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ $platform->display_name }} Orders</flux:heading>
            <flux:text class="mt-2">Manage and track imported orders from {{ $platform->display_name }}</flux:text>
        </div>
        <div class="flex gap-3">
            <flux:button variant="outline" wire:click="exportOrders">
                <div class="flex items-center justify-center">
                    <flux:icon name="arrow-down-tray" class="w-4 h-4 mr-1" />
                    Export CSV
                </div>
            </flux:button>
            <flux:button variant="primary" :href="route('platforms.orders.import', $platform)" wire:navigate>
                <div class="flex items-center justify-center">
                    <flux:icon name="arrow-up-tray" class="w-4 h-4 mr-1" />
                    Import Orders
                </div>
            </flux:button>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="mb-6 grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-4">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-blue-100 dark:bg-blue-900/30 rounded-lg flex items-center justify-center">
                        <flux:icon name="shopping-bag" class="w-4 h-4 text-blue-600 dark:text-blue-400" />
                    </div>
                </div>
                <div class="ml-3">
                    <flux:text size="sm" class="text-zinc-600 dark:text-zinc-400">Total Orders</flux:text>
                    <flux:text class="font-semibold">{{ number_format($totalOrders) }}</flux:text>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-4">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-green-100 dark:bg-green-900/30 rounded-lg flex items-center justify-center">
                        <flux:icon name="currency-dollar" class="w-4 h-4 text-green-600 dark:text-green-400" />
                    </div>
                </div>
                <div class="ml-3">
                    <flux:text size="sm" class="text-zinc-600 dark:text-zinc-400">Total Value</flux:text>
                    <flux:text class="font-semibold">${{ number_format($totalValue, 2) }}</flux:text>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-4">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-green-100 dark:bg-green-900/30 rounded-lg flex items-center justify-center">
                        <flux:icon name="check-circle" class="w-4 h-4 text-green-600 dark:text-green-400" />
                    </div>
                </div>
                <div class="ml-3">
                    <flux:text size="sm" class="text-zinc-600 dark:text-zinc-400">Completed</flux:text>
                    <flux:text class="font-semibold">{{ $statusCounts['completed'] ?? 0 }}</flux:text>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-4">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-amber-100 dark:bg-amber-900/30 rounded-lg flex items-center justify-center">
                        <flux:icon name="clock" class="w-4 h-4 text-amber-600 dark:text-amber-400" />
                    </div>
                </div>
                <div class="ml-3">
                    <flux:text size="sm" class="text-zinc-600 dark:text-zinc-400">Pending</flux:text>
                    <flux:text class="font-semibold">{{ $statusCounts['pending'] ?? 0 }}</flux:text>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="mb-6 bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-4">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div>
                <flux:field>
                    <flux:label>Search</flux:label>
                    <flux:input wire:model.live.debounce.300ms="search" placeholder="Order ID, customer..." />
                </flux:field>
            </div>

            <div>
                <flux:field>
                    <flux:label>Status</flux:label>
                    <flux:select wire:model.live="status_filter">
                        <flux:select.option value="">All Statuses</flux:select.option>
                        @foreach($this->getStatusOptions() as $value => $label)
                            <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>
            </div>

            <div>
                <flux:field>
                    <flux:label>Account</flux:label>
                    <flux:select wire:model.live="account_filter">
                        <flux:select.option value="">All Accounts</flux:select.option>
                        @foreach($accounts as $account)
                            <flux:select.option value="{{ $account->id }}">{{ $account->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>
            </div>

            <div>
                <flux:field>
                    <flux:label>Date Range</flux:label>
                    <flux:select wire:model.live="date_range">
                        <flux:select.option value="">All Time</flux:select.option>
                        <flux:select.option value="today">Today</flux:select.option>
                        <flux:select.option value="week">This Week</flux:select.option>
                        <flux:select.option value="month">This Month</flux:select.option>
                        <flux:select.option value="last_month">Last Month</flux:select.option>
                    </flux:select>
                </flux:field>
            </div>

            <div class="flex items-end">
                <flux:button variant="outline" wire:click="resetFilters" class="w-full">
                    <div class="flex items-center justify-center">
                        <flux:icon name="arrow-path" class="w-4 h-4 mr-1" />
                        Reset
                    </div>
                </flux:button>
            </div>
        </div>
    </div>

    {{-- Orders Table --}}
    <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 overflow-hidden">
        @if($orders->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-zinc-700">
                    <thead class="bg-gray-50 dark:bg-zinc-700/50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Order</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Account</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Customer</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-zinc-800 divide-y divide-gray-200 dark:divide-zinc-700">
                        @foreach($orders as $order)
                            <tr class="hover:bg-gray-50 dark:hover:bg-zinc-700/50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div>
                                        <flux:text class="font-medium">{{ $order->platform_order_number ?: $order->platform_order_id }}</flux:text>
                                        @if($order->tracking_id)
                                            <flux:text size="sm" class="text-zinc-600">Track: {{ $order->tracking_id }}</flux:text>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <flux:text size="sm">{{ $order->platformAccount->name }}</flux:text>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div>
                                        @if($order->customer_name)
                                            <flux:text size="sm" class="font-medium">{{ $order->customer_name }}</flux:text>
                                        @endif
                                        @if($order->buyer_username)
                                            <flux:text size="sm" class="text-zinc-600">@{{ $order->buyer_username }}</flux:text>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <flux:badge size="sm" :color="$this->getStatusColor($order->status)">
                                        {{ $this->getStatusOptions()[$order->status] ?? $order->status }}
                                    </flux:badge>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div>
                                        <flux:text class="font-medium">{{ $order->currency }} {{ number_format($order->total_amount, 2) }}</flux:text>
                                        <flux:text size="sm" class="text-zinc-600">{{ $order->items->count() }} items</flux:text>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($order->order_date)
                                        <div>
                                            <flux:text size="sm">{{ $order->order_date->format('M j, Y') }}</flux:text>
                                            <flux:text size="sm" class="text-zinc-600">{{ $order->order_date->format('g:i A') }}</flux:text>
                                        </div>
                                    @else
                                        <flux:text size="sm" class="text-zinc-500">No date</flux:text>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <flux:button variant="ghost" size="sm" :href="route('platforms.orders.show', [$platform, $order])" wire:navigate>
                                        <div class="flex items-center justify-center">
                                            <flux:icon name="eye" class="w-4 h-4 mr-1" />
                                            View
                                        </div>
                                    </flux:button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            <div class="px-6 py-3 border-t border-gray-200 dark:border-zinc-700">
                {{ $orders->links() }}
            </div>
        @else
            <div class="p-12 text-center">
                <div class="mx-auto w-12 h-12 bg-zinc-100 dark:bg-zinc-700 rounded-lg flex items-center justify-center mb-4">
                    <flux:icon name="shopping-bag" class="w-6 h-6 text-zinc-400" />
                </div>
                <flux:heading size="lg" class="mb-2">No Orders Found</flux:heading>
                <flux:text class="text-zinc-600 mb-4">
                    @if($search || $status_filter || $account_filter || $date_range)
                        No orders match your current filters. Try adjusting your search criteria.
                    @else
                        You haven't imported any orders for this platform yet.
                    @endif
                </flux:text>
                @if(!$search && !$status_filter && !$account_filter && !$date_range)
                    <flux:button variant="primary" :href="route('platforms.orders.import', $platform)" wire:navigate>
                        <div class="flex items-center justify-center">
                            <flux:icon name="arrow-up-tray" class="w-4 h-4 mr-1" />
                            Import Your First Orders
                        </div>
                    </flux:button>
                @else
                    <flux:button variant="outline" wire:click="resetFilters">
                        <div class="flex items-center justify-center">
                            <flux:icon name="arrow-path" class="w-4 h-4 mr-1" />
                            Clear Filters
                        </div>
                    </flux:button>
                @endif
            </div>
        @endif
    </div>
</div>