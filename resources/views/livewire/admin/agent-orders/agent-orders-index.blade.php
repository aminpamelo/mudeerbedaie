<?php

use App\Models\Agent;
use App\Models\ProductOrder;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $agentFilter = '';

    public string $statusFilter = '';

    public string $dateFilter = '';

    public string $sortBy = 'created_at';

    public string $sortDirection = 'desc';

    protected $queryString = [
        'search' => ['except' => ''],
        'agentFilter' => ['except' => ''],
        'statusFilter' => ['except' => ''],
        'dateFilter' => ['except' => ''],
        'sortBy' => ['except' => 'created_at'],
        'sortDirection' => ['except' => 'desc'],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingAgentFilter(): void
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

    public function clearFilters(): void
    {
        $this->reset(['search', 'agentFilter', 'statusFilter', 'dateFilter']);
        $this->resetPage();
    }

    public function updateOrderStatus(int $orderId, string $status): void
    {
        $order = ProductOrder::findOrFail($orderId);

        match ($status) {
            'confirmed' => $order->markAsConfirmed(),
            'processing' => $order->markAsProcessing(),
            'shipped' => $order->markAsShipped(),
            'delivered' => $order->markAsDelivered(),
            'cancelled' => $order->markAsCancelled('Cancelled by admin'),
            default => $order->update(['status' => $status])
        };

        session()->flash('success', "Order {$order->order_number} status updated to {$status}");
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

    public function exportOrders(): void
    {
        $orders = $this->getOrdersQuery()->get();

        $filename = 'agent-orders-' . now()->format('Y-m-d-His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($orders) {
            $file = fopen('php://output', 'w');

            // Header row
            fputcsv($file, [
                'Order ID',
                'Order Number',
                'Agent Code',
                'Agent Name',
                'Order Date',
                'Total Amount',
                'Items Count',
                'Status',
                'Payment Status',
                'Created At',
            ]);

            foreach ($orders as $order) {
                $isPaid = $order->isPaid() ? 'Paid' : 'Unpaid';
                fputcsv($file, [
                    $order->id,
                    $order->order_number,
                    $order->agent?->agent_code ?? 'N/A',
                    $order->agent?->name ?? 'N/A',
                    $order->order_date?->format('Y-m-d H:i:s') ?? $order->created_at->format('Y-m-d H:i:s'),
                    number_format($order->total_amount, 2),
                    $order->items->count(),
                    $order->status,
                    $isPaid,
                    $order->created_at->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($file);
        };

        response()->stream($callback, 200, $headers)->send();
    }

    private function getOrdersQuery()
    {
        return ProductOrder::query()
            ->with(['agent', 'items.product', 'items.warehouse', 'payments'])
            ->whereNotNull('agent_id')
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('order_number', 'like', '%' . $this->search . '%')
                        ->orWhereHas('agent', function ($agentQuery) {
                            $agentQuery->where('name', 'like', '%' . $this->search . '%')
                                ->orWhere('agent_code', 'like', '%' . $this->search . '%');
                        });
                });
            })
            ->when($this->agentFilter, function ($query) {
                $query->where('agent_id', $this->agentFilter);
            })
            ->when($this->statusFilter, function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->when($this->dateFilter, function ($query) {
                match ($this->dateFilter) {
                    'today' => $query->whereDate('created_at', today()),
                    'week' => $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]),
                    'month' => $query->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year),
                    'past' => $query->whereDate('created_at', '<', today()),
                    default => $query
                };
            })
            ->orderBy($this->sortBy, $this->sortDirection);
    }

    public function with(): array
    {
        $orders = $this->getOrdersQuery()->paginate(20);

        return [
            'orders' => $orders,
            'agents' => Agent::query()->active()->orderBy('name')->get(['id', 'name', 'agent_code']),
            'statuses' => $this->getOrderStatuses(),
            'totalAgentOrders' => ProductOrder::whereNotNull('agent_id')->count(),
            'pendingAgentOrders' => ProductOrder::whereNotNull('agent_id')->where('status', 'pending')->count(),
            'totalRevenue' => ProductOrder::whereNotNull('agent_id')->whereNotIn('status', ['cancelled', 'refunded'])->sum('total_amount'),
        ];
    }
}; ?>

<div>
    <!-- Header -->
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">All Agent's Order</flux:heading>
            <flux:text class="mt-2">Manage orders from all agents (agent, company, kedai buku)</flux:text>
        </div>

        <div class="flex gap-3">
            <flux:button variant="outline" wire:click="exportOrders">
                <div class="flex items-center justify-center">
                    <flux:icon name="arrow-down-tray" class="w-4 h-4 mr-2" />
                    Export CSV
                </div>
            </flux:button>
            <flux:button variant="primary" :href="route('agent-orders.create')" wire:navigate>
                <div class="flex items-center justify-center">
                    <flux:icon name="plus" class="w-4 h-4 mr-2" />
                    Buat Pesanan Baru
                </div>
            </flux:button>
        </div>
    </div>

    <!-- Summary Stats -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <flux:text class="text-gray-600 dark:text-zinc-400">Total Agent Orders</flux:text>
                    <flux:heading size="lg">{{ number_format($totalAgentOrders) }}</flux:heading>
                </div>
                <flux:icon name="shopping-bag" class="w-8 h-8 text-blue-500" />
            </div>
        </flux:card>

        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <flux:text class="text-gray-600 dark:text-zinc-400">Pending Orders</flux:text>
                    <flux:heading size="lg" class="text-yellow-600">{{ number_format($pendingAgentOrders) }}</flux:heading>
                </div>
                <flux:icon name="clock" class="w-8 h-8 text-yellow-500" />
            </div>
        </flux:card>

        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <flux:text class="text-gray-600 dark:text-zinc-400">Total Revenue</flux:text>
                    <flux:heading size="lg" class="text-green-600">RM {{ number_format($totalRevenue, 2) }}</flux:heading>
                </div>
                <flux:icon name="banknotes" class="w-8 h-8 text-green-500" />
            </div>
        </flux:card>
    </div>

    <!-- Filters -->
    <div class="mb-6 bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-4">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <!-- Search -->
            <div class="md:col-span-2">
                <flux:label>Search</flux:label>
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Order number, agent name, agent code..."
                    icon="magnifying-glass"
                />
            </div>

            <!-- Agent Filter -->
            <div>
                <flux:label>Agent</flux:label>
                <flux:select wire:model.live="agentFilter" placeholder="All Agents">
                    <flux:select.option value="">All Agents</flux:select.option>
                    @foreach($agents as $agent)
                        <flux:select.option value="{{ $agent->id }}">{{ $agent->name }} ({{ $agent->agent_code }})</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <!-- Status Filter -->
            <div>
                <flux:label>Status</flux:label>
                <flux:select wire:model.live="statusFilter" placeholder="All Statuses">
                    <flux:select.option value="">All Statuses</flux:select.option>
                    @foreach($statuses as $value => $label)
                        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <!-- Date Filter -->
            <div>
                <flux:label>Period</flux:label>
                <flux:select wire:model.live="dateFilter" placeholder="All Time">
                    <flux:select.option value="">All Time</flux:select.option>
                    <flux:select.option value="today">Today</flux:select.option>
                    <flux:select.option value="week">This Week</flux:select.option>
                    <flux:select.option value="month">This Month</flux:select.option>
                    <flux:select.option value="past">Past Orders</flux:select.option>
                </flux:select>
            </div>
        </div>

        <!-- Clear Filters -->
        @if($search || $agentFilter || $statusFilter || $dateFilter)
            <div class="mt-4 pt-4 border-t border-gray-200 dark:border-zinc-700 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <flux:text size="sm" class="text-gray-600 dark:text-zinc-400">Active filters:</flux:text>
                    @if($search)
                        <flux:badge color="gray">
                            Search: {{ Str::limit($search, 20) }}
                            <button wire:click="$set('search', '')" class="ml-1 hover:text-red-600">&times;</button>
                        </flux:badge>
                    @endif
                    @if($agentFilter)
                        <flux:badge color="gray">
                            Agent
                            <button wire:click="$set('agentFilter', '')" class="ml-1 hover:text-red-600">&times;</button>
                        </flux:badge>
                    @endif
                    @if($statusFilter)
                        <flux:badge color="gray">
                            Status: {{ $statuses[$statusFilter] ?? $statusFilter }}
                            <button wire:click="$set('statusFilter', '')" class="ml-1 hover:text-red-600">&times;</button>
                        </flux:badge>
                    @endif
                    @if($dateFilter)
                        <flux:badge color="gray">
                            Period: {{ ucfirst($dateFilter) }}
                            <button wire:click="$set('dateFilter', '')" class="ml-1 hover:text-red-600">&times;</button>
                        </flux:badge>
                    @endif
                </div>
                <flux:button variant="ghost" size="sm" wire:click="clearFilters">
                    Clear all
                </flux:button>
            </div>
        @endif
    </div>

    <!-- Orders Table -->
    <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full border-collapse border-0">
                <thead class="bg-gray-50 dark:bg-zinc-900 border-b border-gray-200 dark:border-zinc-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase tracking-wider">
                            <button wire:click="sortBy('order_number')" class="flex items-center space-x-1 hover:text-gray-700 dark:hover:text-zinc-200">
                                <span>Order ID</span>
                                @if($sortBy === 'order_number')
                                    <flux:icon name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-4 h-4" />
                                @endif
                            </button>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase tracking-wider">
                            Agent
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase tracking-wider">
                            <button wire:click="sortBy('created_at')" class="flex items-center space-x-1 hover:text-gray-700 dark:hover:text-zinc-200">
                                <span>Order Date</span>
                                @if($sortBy === 'created_at')
                                    <flux:icon name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-4 h-4" />
                                @endif
                            </button>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase tracking-wider">
                            <button wire:click="sortBy('total_amount')" class="flex items-center space-x-1 hover:text-gray-700 dark:hover:text-zinc-200">
                                <span>Total (RM)</span>
                                @if($sortBy === 'total_amount')
                                    <flux:icon name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-4 h-4" />
                                @endif
                            </button>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase tracking-wider">
                            Items
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase tracking-wider">
                            Status
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase tracking-wider">
                            Payment
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-zinc-800">
                    @forelse($orders as $order)
                        <tr wire:key="order-{{ $order->id }}" class="border-b border-gray-200 dark:border-zinc-700 hover:bg-gray-50 dark:hover:bg-zinc-700/50">
                            <!-- Order Number -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <flux:text class="font-semibold">{{ $order->order_number }}</flux:text>
                            </td>

                            <!-- Agent -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($order->agent)
                                    <div>
                                        <flux:text class="font-medium">{{ $order->agent->name }}</flux:text>
                                        <flux:text size="sm" class="text-gray-500 dark:text-zinc-400">{{ $order->agent->agent_code }}</flux:text>
                                    </div>
                                @else
                                    <flux:text class="text-gray-500 dark:text-zinc-400">N/A</flux:text>
                                @endif
                            </td>

                            <!-- Order Date -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <flux:text>{{ ($order->order_date ?? $order->created_at)->format('M j, Y') }}</flux:text>
                                <flux:text size="sm" class="text-gray-500 dark:text-zinc-400">{{ ($order->order_date ?? $order->created_at)->format('g:i A') }}</flux:text>
                            </td>

                            <!-- Total -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <flux:text class="font-semibold">RM {{ number_format($order->total_amount, 2) }}</flux:text>
                            </td>

                            <!-- Items -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <flux:text>{{ $order->items->count() }} item{{ $order->items->count() !== 1 ? 's' : '' }}</flux:text>
                                <flux:text size="sm" class="text-gray-500 dark:text-zinc-400">{{ $order->items->sum('quantity_ordered') }} qty</flux:text>
                            </td>

                            <!-- Status -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <flux:badge size="sm" color="{{ $this->getStatusColor($order->status) }}">
                                    {{ $statuses[$order->status] ?? $order->status }}
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

                            <!-- Actions -->
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex items-center justify-end space-x-2">
                                    <flux:button variant="ghost" size="sm" :href="route('agent-orders.show', $order)" wire:navigate>
                                        <flux:icon name="eye" class="w-4 h-4" />
                                    </flux:button>

                                    <flux:button variant="ghost" size="sm" :href="route('agent-orders.edit', $order)" wire:navigate>
                                        <flux:icon name="pencil" class="w-4 h-4" />
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
                                <div class="text-gray-500 dark:text-zinc-400">
                                    <flux:icon name="shopping-bag" class="w-12 h-12 mx-auto mb-4 text-gray-300 dark:text-zinc-600" />
                                    <flux:heading size="lg">Tiada pesanan ejen ditemui</flux:heading>
                                    <flux:text class="mt-2">Mulakan dengan membuat pesanan ejen pertama anda.</flux:text>
                                    <div class="mt-4">
                                        <flux:button variant="primary" :href="route('agent-orders.create')" wire:navigate>
                                            <div class="flex items-center justify-center">
                                                <flux:icon name="plus" class="w-4 h-4 mr-2" />
                                                Buat Pesanan Baru
                                            </div>
                                        </flux:button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($orders->hasPages())
            <div class="px-6 py-4 border-t border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-900">
                {{ $orders->links() }}
            </div>
        @endif
    </div>
</div>
