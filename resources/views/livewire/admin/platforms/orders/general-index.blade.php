<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Platform;
use App\Models\PlatformOrder;
use App\Models\PlatformAccount;
use Carbon\Carbon;

new class extends Component {
    use WithPagination;

    // Filters
    public $search = '';
    public $status_filter = '';
    public $platform_filter = '';
    public $account_filter = '';
    public $date_range = '';

    // Stats
    public $totalOrders = 0;
    public $totalValue = 0;
    public $statusCounts = [];

    public function mount()
    {
        $this->loadStats();
    }

    public function loadStats()
    {
        $this->totalOrders = PlatformOrder::count();
        $this->totalValue = PlatformOrder::sum('total_amount') ?? 0;

        $this->statusCounts = PlatformOrder::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
    }

    public function updatedPlatformFilter()
    {
        $this->resetPage();
        $this->account_filter = ''; // Reset account filter when platform changes
    }

    public function updatedAccountFilter()
    {
        $this->resetPage();
    }

    public function updatedStatusFilter()
    {
        $this->resetPage();
    }

    public function updatedDateRange()
    {
        $this->resetPage();
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function resetFilters()
    {
        $this->platform_filter = '';
        $this->account_filter = '';
        $this->status_filter = '';
        $this->date_range = '';
        $this->search = '';
        $this->resetPage();
    }

    public function with()
    {
        $query = PlatformOrder::with(['platform', 'platformAccount', 'platformCustomer'])
            ->latest();

        // Apply platform filter
        if ($this->platform_filter) {
            $query->where('platform_id', $this->platform_filter);
        }

        // Apply account filter
        if ($this->account_filter) {
            $query->where('platform_account_id', $this->account_filter);
        }

        // Apply status filter
        if ($this->status_filter) {
            $query->where('status', $this->status_filter);
        }

        // Apply date range filter
        if ($this->date_range) {
            switch ($this->date_range) {
                case 'today':
                    $query->whereDate('platform_created_at', today());
                    break;
                case 'week':
                    $query->whereBetween('platform_created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                    break;
                case 'month':
                    $query->whereMonth('platform_created_at', now()->month)
                          ->whereYear('platform_created_at', now()->year);
                    break;
                case 'last_month':
                    $query->whereMonth('platform_created_at', now()->subMonth()->month)
                          ->whereYear('platform_created_at', now()->subMonth()->year);
                    break;
            }
        }

        // Apply search filter
        if ($this->search) {
            $query->where(function($q) {
                $q->where('display_order_id', 'like', '%' . $this->search . '%')
                  ->orWhere('customer_name', 'like', '%' . $this->search . '%')
                  ->orWhere('customer_email', 'like', '%' . $this->search . '%')
                  ->orWhereHas('platform', function($subq) {
                      $subq->where('name', 'like', '%' . $this->search . '%')
                           ->orWhere('display_name', 'like', '%' . $this->search . '%');
                  });
            });
        }

        return [
            'orders' => $query->paginate(20),
            'platforms' => Platform::orderBy('display_name')->get(),
            'accounts' => $this->platform_filter ?
                Platform::find($this->platform_filter)?->accounts()->orderBy('name')->get() ?? collect() :
                collect(),
        ];
    }

    public function getStatusColorAttribute()
    {
        return function($status) {
            return match($status) {
                'pending' => 'gray',
                'confirmed' => 'blue',
                'processing' => 'yellow',
                'shipped' => 'purple',
                'delivered' => 'green',
                'cancelled' => 'red',
                default => 'gray'
            };
        };
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
            <div>
                <flux:heading size="xl">Platform Orders</flux:heading>
                <flux:text class="mt-2">Manage orders imported from all platforms</flux:text>
            </div>
            <div class="flex gap-3">
                <flux:button variant="outline" :href="route('platforms.orders.import')" wire:navigate>
                    <div class="flex items-center justify-center">
                        <flux:icon name="document-arrow-up" class="w-4 h-4 mr-1" />
                        Import Orders
                    </div>
                </flux:button>
                <flux:button variant="primary" :href="route('platforms.index')" wire:navigate>
                    <div class="flex items-center justify-center">
                        <flux:icon name="squares-2x2" class="w-4 h-4 mr-1" />
                        Manage Platforms
                    </div>
                </flux:button>
            </div>
        </div>

    {{-- Stats Cards --}}
    <div class="mb-6 grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-lg border p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                            <flux:icon name="shopping-bag" class="w-4 h-4 text-blue-600" />
                        </div>
                    </div>
                    <div class="ml-3">
                        <flux:text size="sm" class="text-zinc-600">Total Orders</flux:text>
                        <flux:text class="font-semibold">{{ number_format($totalOrders) }}</flux:text>
                    </div>
                </div>
            </div>

        <div class="bg-white rounded-lg border p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
                            <flux:icon name="currency-dollar" class="w-4 h-4 text-green-600" />
                        </div>
                    </div>
                    <div class="ml-3">
                        <flux:text size="sm" class="text-zinc-600">Total Value</flux:text>
                        <flux:text class="font-semibold">RM {{ number_format($totalValue, 2) }}</flux:text>
                    </div>
                </div>
            </div>

        <div class="bg-white rounded-lg border p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center">
                            <flux:icon name="check-circle" class="w-4 h-4 text-purple-600" />
                        </div>
                    </div>
                    <div class="ml-3">
                        <flux:text size="sm" class="text-zinc-600">Delivered</flux:text>
                        <flux:text class="font-semibold">{{ number_format($statusCounts['delivered'] ?? 0) }}</flux:text>
                    </div>
                </div>
            </div>

        <div class="bg-white rounded-lg border p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-amber-100 rounded-lg flex items-center justify-center">
                            <flux:icon name="clock" class="w-4 h-4 text-amber-600" />
                        </div>
                    </div>
                    <div class="ml-3">
                        <flux:text size="sm" class="text-zinc-600">Processing</flux:text>
                        <flux:text class="font-semibold">{{ number_format($statusCounts['processing'] ?? 0) }}</flux:text>
                    </div>
                </div>
            </div>
        </div>

    {{-- Filters --}}
    <div class="mb-6 bg-white rounded-lg border p-4">
            <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
                <div>
                    <flux:field>
                        <flux:label>Search</flux:label>
                        <flux:input wire:model.live.debounce.300ms="search" placeholder="Order ID, customer..." />
                    </flux:field>
                </div>

                <div>
                    <flux:field>
                        <flux:label>Platform</flux:label>
                        <flux:select wire:model.live="platform_filter">
                        <flux:select.option value="">All Platforms</flux:select.option>
                        @foreach($platforms as $platform)
                            <flux:select.option value="{{ $platform->id }}">{{ $platform->display_name }}</flux:select.option>
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
                        <flux:label>Status</flux:label>
                    <flux:select wire:model.live="status_filter">
                        <flux:select.option value="">All Status</flux:select.option>
                        <flux:select.option value="pending">Pending</flux:select.option>
                        <flux:select.option value="confirmed">Confirmed</flux:select.option>
                        <flux:select.option value="processing">Processing</flux:select.option>
                        <flux:select.option value="shipped">Shipped</flux:select.option>
                        <flux:select.option value="delivered">Delivered</flux:select.option>
                        <flux:select.option value="cancelled">Cancelled</flux:select.option>
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
    <div class="bg-white rounded-lg border overflow-hidden">
            @if($orders->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Platform</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($orders as $order)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div>
                                        <flux:text class="font-medium">{{ $order->display_order_id }}</flux:text>
                                        @if($order->reference_number)
                                            <flux:text size="sm" class="text-zinc-600">{{ $order->reference_number }}</flux:text>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        @if($order->platform->logo_url)
                                            <img src="{{ $order->platform->logo_url }}" alt="{{ $order->platform->name }}" class="w-6 h-6 rounded mr-3">
                                        @else
                                            <div class="w-6 h-6 rounded flex items-center justify-center text-white text-xs font-bold mr-3"
                                                 style="background: {{ $order->platform->color_primary ?? '#6b7280' }}">
                                                {{ substr($order->platform->name, 0, 1) }}
                                            </div>
                                        @endif
                                        <div>
                                            <flux:text size="sm" class="font-medium">{{ $order->platform->display_name }}</flux:text>
                                            @if($order->platformAccount)
                                                <flux:text size="sm" class="text-zinc-600">{{ $order->platformAccount->name }}</flux:text>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div>
                                        <flux:text class="font-medium">{{ $order->customer_name }}</flux:text>
                                        @if($order->customer_email)
                                            <flux:text size="sm" class="text-zinc-600">{{ $order->customer_email }}</flux:text>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <flux:text class="font-medium">{{ $order->currency }} {{ number_format($order->total_amount, 2) }}</flux:text>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <flux:badge size="sm" :color="$this->getStatusColorAttribute()($order->status)">
                                        {{ ucfirst($order->status) }}
                                    </flux:badge>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($order->platform_created_at)
                                        <flux:text size="sm">{{ $order->platform_created_at->format('M j, Y') }}</flux:text>
                                        <flux:text size="sm" class="text-zinc-500">{{ $order->platform_created_at->format('g:i A') }}</flux:text>
                                    @else
                                        <flux:text size="sm" class="text-zinc-500">No date</flux:text>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <flux:button variant="ghost" size="sm" :href="route('platforms.orders.show', [$order->platform, $order])" wire:navigate>
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
            <div class="px-6 py-3 border-t border-gray-200">
                {{ $orders->links() }}
            </div>
        @else
            <div class="p-12 text-center">
                <div class="mx-auto w-12 h-12 bg-zinc-100 rounded-lg flex items-center justify-center mb-4">
                    <flux:icon name="shopping-bag" class="w-6 h-6 text-zinc-400" />
                </div>
                <flux:heading size="lg" class="mb-2">No Orders Found</flux:heading>
                <flux:text class="text-zinc-600 mb-4">
                    @if($search || $platform_filter || $account_filter || $status_filter || $date_range)
                        No orders match your current filters. Try adjusting your search criteria.
                    @else
                        You haven't imported any orders yet. Start by importing orders from a platform.
                    @endif
                </flux:text>
                @if(!$search && !$platform_filter && !$account_filter && !$status_filter && !$date_range)
                    <flux:button variant="primary" :href="route('platforms.orders.import')" wire:navigate>
                        <div class="flex items-center justify-center">
                            <flux:icon name="document-arrow-up" class="w-4 h-4 mr-1" />
                            Import Orders
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