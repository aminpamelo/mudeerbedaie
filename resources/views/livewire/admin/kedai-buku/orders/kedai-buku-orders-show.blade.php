<?php

use App\Models\Agent;
use App\Models\ProductOrder;
use Livewire\Volt\Component;

new class extends Component {
    public ProductOrder $order;

    public function mount(ProductOrder $order): void
    {
        // Verify order belongs to a bookstore
        if (! $order->agent || ! $order->agent->isBookstore()) {
            abort(404, 'Order not found.');
        }

        $this->order = $order->load(['agent', 'items.product', 'addresses', 'payments', 'notes']);
    }

    public function updateStatus(string $status): void
    {
        if (! in_array($status, ['pending', 'processing', 'delivered', 'cancelled'])) {
            session()->flash('error', 'Invalid status.');
            return;
        }

        $this->order->update(['status' => $status]);
        $this->order->refresh();

        session()->flash('success', 'Order status updated to ' . ucfirst($status) . '.');
    }

    public function updatePaymentStatus(string $paymentStatus): void
    {
        if (! in_array($paymentStatus, ['pending', 'partial', 'paid'])) {
            session()->flash('error', 'Invalid payment status.');
            return;
        }

        $this->order->update(['payment_status' => $paymentStatus]);
        $this->order->refresh();

        session()->flash('success', 'Payment status updated to ' . ucfirst($paymentStatus) . '.');
    }
}; ?>

<div>
    <div class="mb-6">
        <div class="flex items-center gap-4 mb-4">
            <flux:button href="{{ route('agents-kedai-buku.orders.index') }}" variant="outline" size="sm">
                <div class="flex items-center justify-center">
                    <flux:icon name="arrow-left" class="w-4 h-4 mr-1" />
                    Back to Orders
                </div>
            </flux:button>
        </div>

        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">{{ $order->order_number }}</flux:heading>
                <flux:text class="mt-2">{{ $order->created_at->format('d M Y, h:i A') }}</flux:text>
            </div>
            <div class="flex items-center gap-3">
                @php
                    $statusVariant = match($order->status) {
                        'pending' => 'warning',
                        'processing' => 'info',
                        'delivered' => 'success',
                        'cancelled' => 'danger',
                        default => 'outline',
                    };
                    $paymentVariant = match($order->payment_status) {
                        'paid' => 'success',
                        'partial' => 'warning',
                        default => 'outline',
                    };
                @endphp
                <flux:badge :variant="$statusVariant" size="lg">
                    {{ ucfirst($order->status) }}
                </flux:badge>
                <flux:badge :variant="$paymentVariant" size="lg">
                    {{ ucfirst($order->payment_status ?? 'pending') }} Payment
                </flux:badge>
                <flux:button href="{{ route('agents-kedai-buku.orders.edit', $order) }}" variant="primary" icon="pencil">
                    Edit Order
                </flux:button>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Kedai Buku Details -->
            <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-zinc-100 mb-4">Kedai Buku Details</h3>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <flux:text class="text-sm font-medium text-gray-500 dark:text-zinc-400">Name</flux:text>
                        <a href="{{ route('agents-kedai-buku.show', $order->agent) }}" class="mt-1 text-blue-600 dark:text-blue-400 hover:underline block">
                            {{ $order->agent->name }}
                        </a>
                    </div>
                    <div>
                        <flux:text class="text-sm font-medium text-gray-500 dark:text-zinc-400">Agent Code</flux:text>
                        <flux:text class="mt-1 text-gray-900 dark:text-zinc-100">{{ $order->agent->agent_code }}</flux:text>
                    </div>
                    <div>
                        <flux:text class="text-sm font-medium text-gray-500 dark:text-zinc-400">Contact Person</flux:text>
                        <flux:text class="mt-1 text-gray-900 dark:text-zinc-100">{{ $order->agent->contact_person }}</flux:text>
                    </div>
                    <div>
                        <flux:text class="text-sm font-medium text-gray-500 dark:text-zinc-400">Phone</flux:text>
                        <a href="tel:{{ $order->agent->phone }}" class="mt-1 text-blue-600 dark:text-blue-400 hover:underline block">
                            {{ $order->agent->phone }}
                        </a>
                    </div>
                    <div class="md:col-span-2">
                        <flux:text class="text-sm font-medium text-gray-500 dark:text-zinc-400">Email</flux:text>
                        <a href="mailto:{{ $order->agent->email }}" class="mt-1 text-blue-600 dark:text-blue-400 hover:underline block">
                            {{ $order->agent->email }}
                        </a>
                    </div>
                    @if($order->agent->address)
                        <div class="md:col-span-2">
                            <flux:text class="text-sm font-medium text-gray-500 dark:text-zinc-400">Billing Address</flux:text>
                            <flux:text class="mt-1 text-gray-900 dark:text-zinc-100 whitespace-pre-line">{{ $order->agent->formatted_address }}</flux:text>
                        </div>
                    @endif
                    <div>
                        <flux:text class="text-sm font-medium text-gray-500 dark:text-zinc-400">Payment Terms</flux:text>
                        <flux:text class="mt-1 text-gray-900 dark:text-zinc-100">{{ $order->agent->payment_terms ?? 'Not specified' }}</flux:text>
                    </div>
                </div>
            </div>

            <!-- Order Items -->
            <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-zinc-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-zinc-100">Order Items</h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="bg-gray-50 dark:bg-zinc-900">
                            <tr>
                                <th class="py-3 px-6 text-left text-xs font-semibold text-gray-500 dark:text-zinc-400 uppercase">Product</th>
                                <th class="py-3 px-6 text-right text-xs font-semibold text-gray-500 dark:text-zinc-400 uppercase">Unit Price</th>
                                <th class="py-3 px-6 text-center text-xs font-semibold text-gray-500 dark:text-zinc-400 uppercase">Qty</th>
                                <th class="py-3 px-6 text-right text-xs font-semibold text-gray-500 dark:text-zinc-400 uppercase">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-zinc-700">
                            @foreach($order->items as $item)
                                <tr>
                                    <td class="py-4 px-6">
                                        <div class="font-medium text-gray-900 dark:text-zinc-100">{{ $item->product?->name ?? $item->product_name ?? 'Unknown Product' }}</div>
                                        <div class="text-sm text-gray-500 dark:text-zinc-400">{{ $item->sku ?? '-' }}</div>
                                    </td>
                                    <td class="py-4 px-6 text-right text-gray-900 dark:text-zinc-100">
                                        RM {{ number_format($item->unit_price, 2) }}
                                    </td>
                                    <td class="py-4 px-6 text-center text-gray-900 dark:text-zinc-100">
                                        {{ $item->quantity_ordered }}
                                    </td>
                                    <td class="py-4 px-6 text-right font-medium text-gray-900 dark:text-zinc-100">
                                        RM {{ number_format($item->total_price, 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50 dark:bg-zinc-900">
                            <tr>
                                <td colspan="3" class="py-3 px-6 text-right text-sm font-medium text-gray-500 dark:text-zinc-400">Subtotal</td>
                                <td class="py-3 px-6 text-right font-medium text-gray-900 dark:text-zinc-100">RM {{ number_format($order->subtotal, 2) }}</td>
                            </tr>
                            @if($order->shipping_cost > 0)
                                <tr>
                                    <td colspan="3" class="py-2 px-6 text-right text-sm text-gray-500 dark:text-zinc-400">Shipping</td>
                                    <td class="py-2 px-6 text-right text-gray-900 dark:text-zinc-100">RM {{ number_format($order->shipping_cost, 2) }}</td>
                                </tr>
                            @endif
                            @if($order->tax_amount > 0)
                                <tr>
                                    <td colspan="3" class="py-2 px-6 text-right text-sm text-gray-500 dark:text-zinc-400">Tax</td>
                                    <td class="py-2 px-6 text-right text-gray-900 dark:text-zinc-100">RM {{ number_format($order->tax_amount, 2) }}</td>
                                </tr>
                            @endif
                            @if($order->discount_amount > 0)
                                <tr>
                                    <td colspan="3" class="py-2 px-6 text-right text-sm text-gray-500 dark:text-zinc-400">Discount</td>
                                    <td class="py-2 px-6 text-right text-green-600 dark:text-green-400">-RM {{ number_format($order->discount_amount, 2) }}</td>
                                </tr>
                            @endif
                            <tr class="border-t-2 border-gray-300 dark:border-zinc-600">
                                <td colspan="3" class="py-4 px-6 text-right text-lg font-bold text-gray-900 dark:text-zinc-100">Grand Total</td>
                                <td class="py-4 px-6 text-right text-lg font-bold text-gray-900 dark:text-zinc-100">RM {{ number_format($order->total_amount, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Order Notes -->
            @if($order->customer_notes || $order->internal_notes)
                <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-zinc-100 mb-4">Notes</h3>

                    @if($order->customer_notes)
                        <div class="mb-4">
                            <flux:text class="text-sm font-medium text-gray-500 dark:text-zinc-400">Customer Notes</flux:text>
                            <flux:text class="mt-1 text-gray-900 dark:text-zinc-100 whitespace-pre-wrap">{{ $order->customer_notes }}</flux:text>
                        </div>
                    @endif

                    @if($order->internal_notes)
                        <div>
                            <flux:text class="text-sm font-medium text-gray-500 dark:text-zinc-400">Internal Notes</flux:text>
                            <flux:text class="mt-1 text-gray-900 dark:text-zinc-100 whitespace-pre-wrap">{{ $order->internal_notes }}</flux:text>
                        </div>
                    @endif
                </div>
            @endif
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Order Status Update -->
            <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-zinc-100 mb-4">Update Status</h3>

                <div class="space-y-4">
                    <div>
                        <flux:text class="text-sm font-medium text-gray-500 dark:text-zinc-400 mb-2">Order Status</flux:text>
                        <div class="flex flex-wrap gap-2">
                            <flux:button wire:click="updateStatus('pending')" size="sm" :variant="$order->status === 'pending' ? 'warning' : 'outline'">
                                Pending
                            </flux:button>
                            <flux:button wire:click="updateStatus('processing')" size="sm" :variant="$order->status === 'processing' ? 'info' : 'outline'">
                                Processing
                            </flux:button>
                            <flux:button wire:click="updateStatus('delivered')" size="sm" :variant="$order->status === 'delivered' ? 'success' : 'outline'">
                                Delivered
                            </flux:button>
                            <flux:button wire:click="updateStatus('cancelled')" size="sm" :variant="$order->status === 'cancelled' ? 'danger' : 'outline'">
                                Cancelled
                            </flux:button>
                        </div>
                    </div>

                    <div>
                        <flux:text class="text-sm font-medium text-gray-500 dark:text-zinc-400 mb-2">Payment Status</flux:text>
                        <div class="flex flex-wrap gap-2">
                            <flux:button wire:click="updatePaymentStatus('pending')" size="sm" :variant="($order->payment_status ?? 'pending') === 'pending' ? 'warning' : 'outline'">
                                Pending
                            </flux:button>
                            <flux:button wire:click="updatePaymentStatus('partial')" size="sm" :variant="$order->payment_status === 'partial' ? 'info' : 'outline'">
                                Partial
                            </flux:button>
                            <flux:button wire:click="updatePaymentStatus('paid')" size="sm" :variant="$order->payment_status === 'paid' ? 'success' : 'outline'">
                                Paid
                            </flux:button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-zinc-100 mb-4">Actions</h3>

                <div class="flex flex-col gap-3">
                    <flux:button href="{{ route('agents-kedai-buku.orders.invoice', $order) }}" variant="outline" class="w-full" icon="document-text">
                        Generate Invoice
                    </flux:button>
                    <flux:button href="{{ route('agents-kedai-buku.orders.delivery-note', $order) }}" variant="outline" class="w-full" icon="truck">
                        Generate Delivery Note
                    </flux:button>
                    <flux:button href="{{ route('agents-kedai-buku.orders.edit', $order) }}" variant="outline" class="w-full" icon="pencil">
                        Edit Order
                    </flux:button>
                </div>
            </div>

            <!-- Order Information -->
            <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-zinc-100 mb-4">Order Information</h3>

                <div class="space-y-3">
                    <div>
                        <flux:text class="text-sm font-medium text-gray-500 dark:text-zinc-400">Order Date</flux:text>
                        <flux:text class="mt-1 text-sm text-gray-900 dark:text-zinc-100">{{ $order->created_at->format('M d, Y h:i A') }}</flux:text>
                    </div>

                    @if($order->confirmed_at)
                        <div>
                            <flux:text class="text-sm font-medium text-gray-500 dark:text-zinc-400">Confirmed At</flux:text>
                            <flux:text class="mt-1 text-sm text-gray-900 dark:text-zinc-100">{{ $order->confirmed_at->format('M d, Y h:i A') }}</flux:text>
                        </div>
                    @endif

                    @if($order->delivered_at)
                        <div>
                            <flux:text class="text-sm font-medium text-gray-500 dark:text-zinc-400">Delivered At</flux:text>
                            <flux:text class="mt-1 text-sm text-gray-900 dark:text-zinc-100">{{ $order->delivered_at->format('M d, Y h:i A') }}</flux:text>
                        </div>
                    @endif

                    @if($order->cancelled_at)
                        <div>
                            <flux:text class="text-sm font-medium text-gray-500 dark:text-zinc-400">Cancelled At</flux:text>
                            <flux:text class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $order->cancelled_at->format('M d, Y h:i A') }}</flux:text>
                        </div>
                    @endif

                    <div>
                        <flux:text class="text-sm font-medium text-gray-500 dark:text-zinc-400">Last Updated</flux:text>
                        <flux:text class="mt-1 text-sm text-gray-900 dark:text-zinc-100">{{ $order->updated_at->format('M d, Y h:i A') }}</flux:text>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
