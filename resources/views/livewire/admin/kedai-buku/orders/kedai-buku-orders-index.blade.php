<?php

use App\Models\Agent;
use App\Models\ProductOrder;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public string $search = '';
    public string $agentFilter = '';
    public string $statusFilter = '';
    public string $paymentStatusFilter = '';
    public string $dateFrom = '';
    public string $dateTo = '';

    public function with(): array
    {
        $query = ProductOrder::query()
            ->whereHas('agent', function ($q) {
                $q->where('type', Agent::TYPE_BOOKSTORE);
            })
            ->with(['agent', 'items.product']);

        // Search
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('order_number', 'like', "%{$this->search}%")
                    ->orWhereHas('agent', function ($subQ) {
                        $subQ->where('name', 'like', "%{$this->search}%")
                            ->orWhere('agent_code', 'like', "%{$this->search}%");
                    });
            });
        }

        // Filters
        if ($this->agentFilter) {
            $query->where('agent_id', $this->agentFilter);
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        if ($this->paymentStatusFilter) {
            $query->where('payment_status', $this->paymentStatusFilter);
        }

        if ($this->dateFrom) {
            $query->whereDate('created_at', '>=', $this->dateFrom);
        }

        if ($this->dateTo) {
            $query->whereDate('created_at', '<=', $this->dateTo);
        }

        $orders = $query->latest()->paginate(20);

        // Calculate statistics
        $totalOrders = ProductOrder::whereHas('agent', fn($q) => $q->where('type', Agent::TYPE_BOOKSTORE))->count();
        $pendingOrders = ProductOrder::whereHas('agent', fn($q) => $q->where('type', Agent::TYPE_BOOKSTORE))
            ->whereIn('status', ['pending', 'processing'])->count();
        $totalRevenue = ProductOrder::whereHas('agent', fn($q) => $q->where('type', Agent::TYPE_BOOKSTORE))
            ->where('status', 'delivered')->sum('total_amount');
        $outstandingPayments = ProductOrder::whereHas('agent', fn($q) => $q->where('type', Agent::TYPE_BOOKSTORE))
            ->where('payment_status', '!=', 'paid')->sum('total_amount');

        return [
            'orders' => $orders,
            'bookstores' => Agent::bookstores()->active()->orderBy('name')->get(),
            'totalOrders' => $totalOrders,
            'pendingOrders' => $pendingOrders,
            'totalRevenue' => $totalRevenue,
            'outstandingPayments' => $outstandingPayments,
        ];
    }

    public function updateStatus(int $orderId, string $status): void
    {
        $order = ProductOrder::findOrFail($orderId);

        if (! in_array($status, ['pending', 'processing', 'delivered', 'cancelled'])) {
            session()->flash('error', 'Invalid status.');
            return;
        }

        $order->update(['status' => $status]);
        session()->flash('success', 'Order status updated.');
    }

    public function updatePaymentStatus(int $orderId, string $paymentStatus): void
    {
        $order = ProductOrder::findOrFail($orderId);

        if (! in_array($paymentStatus, ['pending', 'partial', 'paid'])) {
            session()->flash('error', 'Invalid payment status.');
            return;
        }

        $order->update(['payment_status' => $paymentStatus]);
        session()->flash('success', 'Payment status updated.');
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'agentFilter', 'statusFilter', 'paymentStatusFilter', 'dateFrom', 'dateTo']);
        $this->resetPage();
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Pesanan Kedai Buku</flux:heading>
            <flux:text class="mt-2">Manage wholesale orders from bookstore agents</flux:text>
        </div>
        <flux:button variant="primary" href="{{ route('agents-kedai-buku.orders.create') }}" icon="plus">
            Buat Pesanan Baru
        </flux:button>
    </div>

    <!-- Summary Statistics -->
    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-4">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <flux:icon name="shopping-bag" class="h-8 w-8 text-blue-500" />
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-zinc-400">Total Orders</p>
                    <p class="text-2xl font-semibold text-gray-900 dark:text-zinc-100">{{ $totalOrders }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-4">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <flux:icon name="clock" class="h-8 w-8 text-yellow-500" />
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-zinc-400">Pending</p>
                    <p class="text-2xl font-semibold {{ $pendingOrders > 0 ? 'text-yellow-600 dark:text-yellow-400' : 'text-gray-900 dark:text-zinc-100' }}">{{ $pendingOrders }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-4">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <flux:icon name="banknotes" class="h-8 w-8 text-green-500" />
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-zinc-400">Total Revenue</p>
                    <p class="text-2xl font-semibold text-gray-900 dark:text-zinc-100">RM {{ number_format($totalRevenue, 2) }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-4">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <flux:icon name="exclamation-circle" class="h-8 w-8 text-amber-500" />
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-zinc-400">Outstanding</p>
                    <p class="text-2xl font-semibold {{ $outstandingPayments > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-900 dark:text-zinc-100' }}">RM {{ number_format($outstandingPayments, 2) }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="mb-6 bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-4">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3 lg:grid-cols-6">
            <flux:input
                wire:model.live.debounce.300ms="search"
                placeholder="Search orders..."
                icon="magnifying-glass"
            />

            <flux:select wire:model.live="agentFilter" placeholder="All Kedai Buku">
                <flux:select.option value="">All Kedai Buku</flux:select.option>
                @foreach($bookstores as $bookstore)
                    <flux:select.option value="{{ $bookstore->id }}">{{ $bookstore->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="statusFilter" placeholder="All Status">
                <flux:select.option value="">All Status</flux:select.option>
                <flux:select.option value="pending">Pending</flux:select.option>
                <flux:select.option value="processing">Processing</flux:select.option>
                <flux:select.option value="delivered">Delivered</flux:select.option>
                <flux:select.option value="cancelled">Cancelled</flux:select.option>
            </flux:select>

            <flux:select wire:model.live="paymentStatusFilter" placeholder="All Payment Status">
                <flux:select.option value="">All Payment Status</flux:select.option>
                <flux:select.option value="pending">Pending</flux:select.option>
                <flux:select.option value="partial">Partial</flux:select.option>
                <flux:select.option value="paid">Paid</flux:select.option>
            </flux:select>

            <flux:input wire:model.live="dateFrom" type="date" placeholder="From Date" />

            <flux:input wire:model.live="dateTo" type="date" placeholder="To Date" />
        </div>

        <div class="mt-4 flex justify-end">
            <flux:button wire:click="clearFilters" variant="outline" size="sm" icon="x-mark">
                Clear Filters
            </flux:button>
        </div>
    </div>

    <!-- Orders Table -->
    <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full border-collapse border-0">
                <thead class="bg-gray-50 dark:bg-zinc-900 border-b border-gray-200 dark:border-zinc-700">
                    <tr>
                        <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 dark:text-zinc-100 sm:pl-6">Order</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-zinc-100">Kedai Buku</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-zinc-100">Items</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-zinc-100">Total</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-zinc-100">Status</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-zinc-100">Payment</th>
                        <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                            <span class="sr-only">Actions</span>
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-zinc-800">
                    @forelse($orders as $order)
                        <tr wire:key="order-{{ $order->id }}" class="border-b border-gray-200 dark:border-zinc-700 hover:bg-gray-50 dark:hover:bg-zinc-700/50">
                            <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm sm:pl-6">
                                <a href="{{ route('agents-kedai-buku.orders.show', $order) }}" class="text-blue-600 dark:text-blue-400 hover:underline font-medium">
                                    {{ $order->order_number }}
                                </a>
                                <div class="text-gray-500 dark:text-zinc-400">{{ $order->created_at->format('d M Y') }}</div>
                            </td>
                            <td class="px-3 py-4 text-sm">
                                <a href="{{ route('agents-kedai-buku.show', $order->agent) }}" class="text-gray-900 dark:text-zinc-100 hover:text-blue-600 dark:hover:text-blue-400">
                                    {{ $order->agent?->name ?? '-' }}
                                </a>
                                <div class="text-gray-500 dark:text-zinc-400">{{ $order->agent?->agent_code ?? '-' }}</div>
                            </td>
                            <td class="px-3 py-4 text-sm text-gray-900 dark:text-zinc-100">
                                {{ $order->items->count() }} items
                            </td>
                            <td class="px-3 py-4 text-sm font-semibold text-gray-900 dark:text-zinc-100">
                                RM {{ number_format($order->total_amount, 2) }}
                            </td>
                            <td class="px-3 py-4 text-sm">
                                @php
                                    $statusVariant = match($order->status) {
                                        'pending' => 'warning',
                                        'processing' => 'info',
                                        'delivered' => 'success',
                                        'cancelled' => 'danger',
                                        default => 'outline',
                                    };
                                @endphp
                                <flux:badge :variant="$statusVariant" size="sm">
                                    {{ ucfirst($order->status) }}
                                </flux:badge>
                            </td>
                            <td class="px-3 py-4 text-sm">
                                @php
                                    $paymentVariant = match($order->payment_status) {
                                        'paid' => 'success',
                                        'partial' => 'warning',
                                        default => 'outline',
                                    };
                                @endphp
                                <flux:badge :variant="$paymentVariant" size="sm">
                                    {{ ucfirst($order->payment_status ?? 'pending') }}
                                </flux:badge>
                            </td>
                            <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                                <flux:dropdown>
                                    <flux:button variant="outline" size="sm" icon="ellipsis-vertical" />
                                    <flux:menu>
                                        <flux:menu.item href="{{ route('agents-kedai-buku.orders.show', $order) }}" icon="eye">
                                            View Details
                                        </flux:menu.item>
                                        <flux:menu.item href="{{ route('agents-kedai-buku.orders.edit', $order) }}" icon="pencil">
                                            Edit Order
                                        </flux:menu.item>
                                        <flux:menu.separator />
                                        <flux:menu.submenu heading="Update Status">
                                            <flux:menu.item wire:click="updateStatus({{ $order->id }}, 'pending')">Pending</flux:menu.item>
                                            <flux:menu.item wire:click="updateStatus({{ $order->id }}, 'processing')">Processing</flux:menu.item>
                                            <flux:menu.item wire:click="updateStatus({{ $order->id }}, 'delivered')">Delivered</flux:menu.item>
                                            <flux:menu.item wire:click="updateStatus({{ $order->id }}, 'cancelled')">Cancelled</flux:menu.item>
                                        </flux:menu.submenu>
                                        <flux:menu.submenu heading="Update Payment">
                                            <flux:menu.item wire:click="updatePaymentStatus({{ $order->id }}, 'pending')">Pending</flux:menu.item>
                                            <flux:menu.item wire:click="updatePaymentStatus({{ $order->id }}, 'partial')">Partial</flux:menu.item>
                                            <flux:menu.item wire:click="updatePaymentStatus({{ $order->id }}, 'paid')">Paid</flux:menu.item>
                                        </flux:menu.submenu>
                                        <flux:menu.separator />
                                        <flux:menu.item href="{{ route('agents-kedai-buku.orders.invoice', $order) }}" icon="document-text">
                                            Generate Invoice
                                        </flux:menu.item>
                                        <flux:menu.item href="{{ route('agents-kedai-buku.orders.delivery-note', $order) }}" icon="truck">
                                            Generate Delivery Note
                                        </flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center">
                                <div>
                                    <flux:icon name="shopping-bag" class="mx-auto h-12 w-12 text-gray-400 dark:text-zinc-500" />
                                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-zinc-100">Tiada pesanan kedai buku</h3>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-zinc-400">Get started by creating your first bookstore order.</p>
                                    <div class="mt-6">
                                        <flux:button variant="primary" href="{{ route('agents-kedai-buku.orders.create') }}" icon="plus">
                                            Buat Pesanan Baru
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
        {{ $orders->links() }}
    </div>
</div>
