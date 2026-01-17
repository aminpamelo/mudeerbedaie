<?php

use App\Models\Agent;
use App\Models\ProductOrder;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public Agent $kedaiBuku;
    public string $statusFilter = '';
    public string $paymentStatusFilter = '';

    public function mount(Agent $kedaiBuku): void
    {
        if (! $kedaiBuku->isBookstore()) {
            abort(404, 'Kedai Buku not found.');
        }

        $this->kedaiBuku = $kedaiBuku;
    }

    public function with(): array
    {
        $query = $this->kedaiBuku->orders()->with(['items.product']);

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        if ($this->paymentStatusFilter) {
            $query->where('payment_status', $this->paymentStatusFilter);
        }

        return [
            'orders' => $query->latest()->paginate(15),
        ];
    }

    public function updateStatus(int $orderId, string $status): void
    {
        $order = ProductOrder::findOrFail($orderId);

        if ($order->agent_id !== $this->kedaiBuku->id) {
            session()->flash('error', 'Invalid order.');
            return;
        }

        $order->update(['status' => $status]);
        session()->flash('success', 'Order status updated.');
    }

    public function updatePaymentStatus(int $orderId, string $paymentStatus): void
    {
        $order = ProductOrder::findOrFail($orderId);

        if ($order->agent_id !== $this->kedaiBuku->id) {
            session()->flash('error', 'Invalid order.');
            return;
        }

        $order->update(['payment_status' => $paymentStatus]);
        session()->flash('success', 'Payment status updated.');
    }

    public function clearFilters(): void
    {
        $this->reset(['statusFilter', 'paymentStatusFilter']);
        $this->resetPage();
    }
}; ?>

<div>
    <div class="mb-6">
        <div class="flex items-center gap-4 mb-4">
            <flux:button href="{{ route('agents-kedai-buku.show', $kedaiBuku) }}" variant="outline" size="sm">
                <div class="flex items-center justify-center">
                    <flux:icon name="arrow-left" class="w-4 h-4 mr-1" />
                    Back to Details
                </div>
            </flux:button>
        </div>

        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">Pesanan - {{ $kedaiBuku->name }}</flux:heading>
                <flux:text class="mt-2">{{ $kedaiBuku->agent_code }} - {{ ucfirst($kedaiBuku->pricing_tier ?? 'standard') }} Tier</flux:text>
            </div>
            <flux:button variant="primary" href="{{ route('agents-kedai-buku.orders.create', ['agent_id' => $kedaiBuku->id]) }}" icon="plus">
                Buat Pesanan Baru
            </flux:button>
        </div>
    </div>

    <!-- Bookstore Info Card -->
    <div class="mb-6 bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-zinc-400">Contact</p>
                    <p class="text-gray-900 dark:text-zinc-100">{{ $kedaiBuku->contact_person }}</p>
                </div>
                <div class="border-l border-gray-200 dark:border-zinc-700 pl-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-zinc-400">Phone</p>
                    <p class="text-gray-900 dark:text-zinc-100">{{ $kedaiBuku->phone }}</p>
                </div>
                <div class="border-l border-gray-200 dark:border-zinc-700 pl-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-zinc-400">Credit Limit</p>
                    <p class="text-gray-900 dark:text-zinc-100">RM {{ number_format($kedaiBuku->credit_limit, 2) }}</p>
                </div>
                <div class="border-l border-gray-200 dark:border-zinc-700 pl-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-zinc-400">Available Credit</p>
                    <p class="{{ $kedaiBuku->available_credit <= 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                        RM {{ number_format($kedaiBuku->available_credit, 2) }}
                    </p>
                </div>
            </div>
            <flux:badge :variant="$kedaiBuku->is_active ? 'success' : 'gray'">
                {{ $kedaiBuku->is_active ? 'Active' : 'Inactive' }}
            </flux:badge>
        </div>
    </div>

    <!-- Filters -->
    <div class="mb-6 flex items-center gap-4">
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

        <flux:button wire:click="clearFilters" variant="outline" size="sm" icon="x-mark">
            Clear Filters
        </flux:button>
    </div>

    <!-- Orders Table -->
    <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full border-collapse border-0">
                <thead class="bg-gray-50 dark:bg-zinc-900 border-b border-gray-200 dark:border-zinc-700">
                    <tr>
                        <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 dark:text-zinc-100 sm:pl-6">Order</th>
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
                                <div class="text-gray-500 dark:text-zinc-400">{{ $order->created_at->format('d M Y, h:i A') }}</div>
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
                                <div class="flex items-center justify-end gap-2">
                                    <flux:button href="{{ route('agents-kedai-buku.orders.show', $order) }}" variant="outline" size="sm" icon="eye">
                                        View
                                    </flux:button>
                                    <flux:dropdown>
                                        <flux:button variant="outline" size="sm" icon="ellipsis-vertical" />
                                        <flux:menu>
                                            <flux:menu.item href="{{ route('agents-kedai-buku.orders.edit', $order) }}" icon="pencil">
                                                Edit
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
                                        </flux:menu>
                                    </flux:dropdown>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <div>
                                    <flux:icon name="shopping-bag" class="mx-auto h-12 w-12 text-gray-400 dark:text-zinc-500" />
                                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-zinc-100">No orders found</h3>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-zinc-400">This bookstore doesn't have any orders yet.</p>
                                    <div class="mt-6">
                                        <flux:button variant="primary" href="{{ route('agents-kedai-buku.orders.create', ['agent_id' => $kedaiBuku->id]) }}" icon="plus">
                                            Create Order
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
