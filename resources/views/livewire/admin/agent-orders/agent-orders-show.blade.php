<?php

use App\Models\ProductOrder;
use App\Models\StockLevel;
use App\Models\StockMovement;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Component;

new class extends Component
{
    public ProductOrder $order;

    public string $paymentStatus = 'pending';

    public string $orderStatus = 'draft';

    public function mount(ProductOrder $order): void
    {
        // Ensure this is an agent order
        if (! $order->agent_id) {
            abort(404, 'This is not an agent order');
        }

        $this->order = $order->load([
            'agent',
            'items.product',
            'items.warehouse',
            'payments',
            'notes.user',
        ]);

        $latestPayment = $this->order->payments()->latest()->first();
        $this->paymentStatus = $latestPayment?->status ?? 'pending';
        $this->orderStatus = $this->order->status;
    }

    public function updateStatus(string $status): void
    {
        DB::transaction(function () use ($status) {
            $previousStatus = $this->order->status;

            $this->order->update(['status' => $status]);

            $this->handleStockManagement($previousStatus, $status);

            $this->order->addSystemNote("Order status changed from {$previousStatus} to {$status}");

            $this->orderStatus = $status;
        });

        session()->flash('success', 'Order status updated successfully!');
        $this->order->refresh();
    }

    public function updatePaymentStatus(string $paymentStatus): void
    {
        DB::transaction(function () use ($paymentStatus) {
            $payment = $this->order->payments()->latest()->first();

            if (! $payment) {
                $payment = $this->order->payments()->create([
                    'payment_method' => 'cash',
                    'amount' => $this->order->total_amount,
                    'currency' => $this->order->currency,
                    'status' => $paymentStatus,
                    'paid_at' => $paymentStatus === 'completed' ? now() : null,
                ]);
            } else {
                $payment->update([
                    'payment_method' => 'cash',
                    'amount' => $this->order->total_amount,
                    'status' => $paymentStatus,
                    'paid_at' => $paymentStatus === 'completed' ? now() : null,
                ]);
            }

            $this->order->addSystemNote("Payment status changed to {$paymentStatus}");

            $this->paymentStatus = $paymentStatus;
        });

        session()->flash('success', 'Payment status updated successfully!');
        $this->order->refresh();
    }

    public function downloadPdf()
    {
        $order = $this->order;

        $pdf = Pdf::loadView('livewire.admin.agent-orders.agent-orders-receipt-pdf', compact('order'))
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'defaultFont' => 'DejaVu Sans',
                'isHtml5ParserEnabled' => true,
                'isPhpEnabled' => false,
                'isRemoteEnabled' => false,
            ]);

        $filename = 'receipt-' . $order->order_number . '.pdf';

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->stream();
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function downloadDeliveryNote()
    {
        $order = $this->order;

        $pdf = Pdf::loadView('livewire.admin.agent-orders.agent-orders-delivery-note-pdf', compact('order'))
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'defaultFont' => 'DejaVu Sans',
                'isHtml5ParserEnabled' => true,
                'isPhpEnabled' => false,
                'isRemoteEnabled' => false,
            ]);

        $filename = 'delivery-note-' . $order->order_number . '.pdf';

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->stream();
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    private function handleStockManagement(string $previousStatus, string $newStatus): void
    {
        $stockDeductionStatuses = ['processing', 'shipped', 'delivered'];
        $stockRestorationStatuses = ['draft', 'pending', 'cancelled', 'refunded', 'returned'];

        $wasStockDeducted = in_array($previousStatus, $stockDeductionStatuses);
        $shouldDeductStock = in_array($newStatus, $stockDeductionStatuses);
        $shouldRestoreStock = in_array($newStatus, $stockRestorationStatuses);

        if (! $wasStockDeducted && $shouldDeductStock) {
            $this->deductStock("Order status changed to {$newStatus}");
        } elseif ($wasStockDeducted && $shouldRestoreStock) {
            $this->restoreStock("Order status changed to {$newStatus}");
        }
    }

    private function deductStock(string $reason): void
    {
        foreach ($this->order->items as $item) {
            if (! $item->warehouse_id) {
                continue;
            }

            $stockLevel = StockLevel::firstOrCreate(
                [
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'warehouse_id' => $item->warehouse_id,
                ],
                [
                    'quantity' => 0,
                    'reserved_quantity' => 0,
                    'available_quantity' => 0,
                    'average_cost' => $item->unit_cost ?? 0,
                ]
            );

            $quantityBefore = $stockLevel->quantity;
            $quantityAfter = $quantityBefore - $item->quantity_ordered;

            $stockLevel->update([
                'quantity' => $quantityAfter,
                'available_quantity' => $stockLevel->available_quantity - $item->quantity_ordered,
                'last_movement_at' => now(),
            ]);

            StockMovement::create([
                'product_id' => $item->product_id,
                'product_variant_id' => $item->product_variant_id,
                'warehouse_id' => $item->warehouse_id,
                'type' => 'out',
                'quantity' => -$item->quantity_ordered,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $quantityAfter,
                'unit_cost' => $item->unit_cost,
                'reference_type' => 'App\\Models\\ProductOrder',
                'reference_id' => $this->order->id,
                'notes' => "Stock deducted: {$reason} (Order #{$this->order->order_number})",
                'created_by' => auth()->id(),
            ]);
        }
    }

    private function restoreStock(string $reason): void
    {
        foreach ($this->order->items as $item) {
            $stockLevel = StockLevel::where([
                'product_id' => $item->product_id,
                'product_variant_id' => $item->product_variant_id,
                'warehouse_id' => $item->warehouse_id,
            ])->first();

            if ($stockLevel) {
                $quantityBefore = $stockLevel->quantity;
                $quantityAfter = $quantityBefore + $item->quantity_ordered;

                $stockLevel->update([
                    'quantity' => $quantityAfter,
                    'available_quantity' => $stockLevel->available_quantity + $item->quantity_ordered,
                    'last_movement_at' => now(),
                ]);

                StockMovement::create([
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'warehouse_id' => $item->warehouse_id,
                    'type' => 'in',
                    'quantity' => $item->quantity_ordered,
                    'quantity_before' => $quantityBefore,
                    'quantity_after' => $quantityAfter,
                    'unit_cost' => $item->unit_cost,
                    'reference_type' => 'App\\Models\\ProductOrder',
                    'reference_id' => $this->order->id,
                    'notes' => "Stock restored: {$reason} (Order #{$this->order->order_number})",
                    'created_by' => auth()->id(),
                ]);
            }
        }
    }
}; ?>

<div>
    <!-- Header -->
    <div class="mb-6 flex items-center justify-between">
        <div>
            <div class="flex items-center gap-3">
                <flux:heading size="xl">Order #{{ $order->order_number }}</flux:heading>
                <flux:badge color="purple">
                    <flux:icon name="building-storefront" class="w-3 h-3 mr-1" />
                    Agent Order
                </flux:badge>
            </div>
            <flux:text class="mt-2">
                Created {{ $order->created_at->format('M j, Y \a\t g:i A') }}
            </flux:text>
        </div>
        <div class="flex flex-wrap gap-2">
            <flux:button variant="outline" wire:click="downloadPdf" size="sm">
                <div class="flex items-center justify-center">
                    <flux:icon name="arrow-down-tray" class="w-4 h-4 mr-1" />
                    Download PDF
                </div>
            </flux:button>
            <flux:button variant="outline" wire:click="downloadDeliveryNote" size="sm">
                <div class="flex items-center justify-center">
                    <flux:icon name="truck" class="w-4 h-4 mr-1" />
                    Delivery Note
                </div>
            </flux:button>
            <flux:button variant="outline" :href="route('agent-orders.edit', $order)" wire:navigate size="sm">
                <div class="flex items-center justify-center">
                    <flux:icon name="pencil" class="w-4 h-4 mr-1" />
                    Edit Order
                </div>
            </flux:button>
            <flux:button variant="outline" :href="route('agent-orders.index')" wire:navigate size="sm">
                <div class="flex items-center justify-center">
                    <flux:icon name="arrow-left" class="w-4 h-4 mr-1" />
                    Back to List
                </div>
            </flux:button>
        </div>
    </div>

    <div class="grid lg:grid-cols-3 gap-6">
        <!-- Left Column - Order Details -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Agent Information -->
            <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6">
                <flux:heading size="lg" class="mb-4">Agent Information</flux:heading>

                @if($order->agent)
                    <div class="grid md:grid-cols-2 gap-6">
                        <div>
                            <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">Agent Name</flux:text>
                            <flux:text class="font-medium">{{ $order->agent->name }}</flux:text>
                        </div>
                        <div>
                            <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">Agent Code</flux:text>
                            <flux:text class="font-medium">{{ $order->agent->agent_code }}</flux:text>
                        </div>
                        @if($order->agent->company_name)
                            <div>
                                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">Company Name</flux:text>
                                <flux:text class="font-medium">{{ $order->agent->company_name }}</flux:text>
                            </div>
                        @endif
                        <div>
                            <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">Type</flux:text>
                            <flux:badge :variant="$order->agent->type === 'company' ? 'info' : 'outline'" size="sm">
                                {{ ucfirst($order->agent->type) }}
                            </flux:badge>
                        </div>
                        @if($order->agent->contact_person)
                            <div>
                                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">Contact Person</flux:text>
                                <flux:text class="font-medium">{{ $order->agent->contact_person }}</flux:text>
                            </div>
                        @endif
                        @if($order->agent->phone)
                            <div>
                                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">Phone</flux:text>
                                <flux:text class="font-medium">{{ $order->agent->phone }}</flux:text>
                            </div>
                        @endif
                        @if($order->agent->email)
                            <div>
                                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">Email</flux:text>
                                <flux:text class="font-medium">{{ $order->agent->email }}</flux:text>
                            </div>
                        @endif
                        @if($order->agent->payment_terms)
                            <div>
                                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">Payment Terms</flux:text>
                                <flux:text class="font-medium">{{ $order->agent->payment_terms }}</flux:text>
                            </div>
                        @endif
                    </div>

                    @if($order->agent->formatted_address)
                        <div class="mt-4 pt-4 border-t border-gray-200 dark:border-zinc-700">
                            <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">Address</flux:text>
                            <flux:text class="font-medium">{{ $order->agent->formatted_address }}</flux:text>
                        </div>
                    @endif
                @else
                    <flux:text class="text-gray-500 dark:text-zinc-400">No agent information available</flux:text>
                @endif
            </div>

            <!-- Order Status -->
            <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6">
                <flux:heading size="lg" class="mb-4">Order Status</flux:heading>

                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <flux:field>
                            <flux:label>Order Status</flux:label>
                            <flux:select wire:model="orderStatus" wire:change="updateStatus($event.target.value)">
                                <option value="draft">Draft</option>
                                <option value="pending">Pending</option>
                                <option value="processing">Processing</option>
                                <option value="shipped">Shipped</option>
                                <option value="delivered">Delivered</option>
                                <option value="cancelled">Cancelled</option>
                                <option value="refunded">Refunded</option>
                                <option value="returned">Returned</option>
                            </flux:select>
                        </flux:field>
                    </div>

                    <div>
                        <flux:field>
                            <flux:label>Payment Status</flux:label>
                            <flux:select wire:model="paymentStatus" wire:change="updatePaymentStatus($event.target.value)">
                                <option value="pending">Pending</option>
                                <option value="completed">Completed</option>
                                <option value="failed">Failed</option>
                                <option value="refunded">Refunded</option>
                            </flux:select>
                        </flux:field>
                    </div>
                </div>

                <!-- Order Timeline -->
                <div class="mt-6 pt-6 border-t border-gray-200 dark:border-zinc-700">
                    <flux:heading size="sm" class="mb-3">Order Timeline</flux:heading>
                    <div class="space-y-3">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                                <flux:icon name="check" class="w-4 h-4 text-green-600 dark:text-green-400" />
                            </div>
                            <div>
                                <flux:text class="font-medium">Order Created</flux:text>
                                <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">{{ $order->created_at->format('M j, Y g:i A') }}</flux:text>
                            </div>
                        </div>

                        @if($order->confirmed_at)
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                                    <flux:icon name="check-circle" class="w-4 h-4 text-blue-600 dark:text-blue-400" />
                                </div>
                                <div>
                                    <flux:text class="font-medium">Confirmed</flux:text>
                                    <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">{{ $order->confirmed_at->format('M j, Y g:i A') }}</flux:text>
                                </div>
                            </div>
                        @endif

                        @if($order->shipped_at)
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center">
                                    <flux:icon name="truck" class="w-4 h-4 text-purple-600 dark:text-purple-400" />
                                </div>
                                <div>
                                    <flux:text class="font-medium">Shipped</flux:text>
                                    <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">{{ $order->shipped_at->format('M j, Y g:i A') }}</flux:text>
                                </div>
                            </div>
                        @endif

                        @if($order->delivered_at)
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                                    <flux:icon name="home" class="w-4 h-4 text-green-600 dark:text-green-400" />
                                </div>
                                <div>
                                    <flux:text class="font-medium">Delivered</flux:text>
                                    <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">{{ $order->delivered_at->format('M j, Y g:i A') }}</flux:text>
                                </div>
                            </div>
                        @endif

                        @if($order->cancelled_at)
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center">
                                    <flux:icon name="x-circle" class="w-4 h-4 text-red-600 dark:text-red-400" />
                                </div>
                                <div>
                                    <flux:text class="font-medium">Cancelled</flux:text>
                                    <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">{{ $order->cancelled_at->format('M j, Y g:i A') }}</flux:text>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Order Items -->
            <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6">
                <flux:heading size="lg" class="mb-4">Order Items</flux:heading>

                <div class="space-y-4">
                    @foreach($order->items as $item)
                        <div class="flex items-center justify-between border-b border-gray-200 dark:border-zinc-700 pb-4">
                            <div class="flex items-center space-x-4">
                                <div class="w-16 h-16 bg-zinc-100 dark:bg-zinc-700 rounded-lg flex items-center justify-center">
                                    <flux:icon name="cube" class="w-8 h-8 text-zinc-400 dark:text-zinc-500" />
                                </div>
                                <div>
                                    <flux:heading class="font-medium">{{ $item->product?->name ?? $item->product_name ?? 'Unknown Product' }}</flux:heading>
                                    @if($item->product)
                                        <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                                            SKU: {{ $item->product->sku }}
                                        </flux:text>
                                    @endif
                                    <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                                        Warehouse: {{ $item->warehouse?->name ?? 'Not assigned' }}
                                    </flux:text>
                                </div>
                            </div>
                            <div class="text-right">
                                <flux:text class="font-medium">{{ $item->quantity_ordered }} x RM {{ number_format($item->unit_price, 2) }}</flux:text>
                                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">RM {{ number_format($item->total_price, 2) }}</flux:text>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Order Notes -->
            @if($order->notes->count() > 0 || $order->internal_notes || $order->customer_notes)
                <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6">
                    <flux:heading size="lg" class="mb-4">Order Notes</flux:heading>

                    @if($order->customer_notes)
                        <div class="mb-4 p-4 bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800 rounded-lg">
                            <flux:text class="text-sm font-medium text-blue-900 dark:text-blue-100">Customer Notes</flux:text>
                            <flux:text class="text-sm text-blue-800 dark:text-blue-200 mt-1">{{ $order->customer_notes }}</flux:text>
                        </div>
                    @endif

                    @if($order->internal_notes)
                        <div class="mb-4 p-4 bg-yellow-50 dark:bg-yellow-900/30 border border-yellow-200 dark:border-yellow-800 rounded-lg">
                            <flux:text class="text-sm font-medium text-yellow-900 dark:text-yellow-100">Internal Notes</flux:text>
                            <flux:text class="text-sm text-yellow-800 dark:text-yellow-200 mt-1">{{ $order->internal_notes }}</flux:text>
                        </div>
                    @endif

                    @if($order->notes->count() > 0)
                        <div class="space-y-4">
                            @foreach($order->notes()->orderBy('created_at', 'desc')->get() as $note)
                                <div class="border-l-4 @if($note->type === 'system') border-blue-500 @elseif($note->type === 'customer') border-green-500 @else border-amber-500 @endif pl-4 py-2">
                                    <flux:text class="text-zinc-900 dark:text-zinc-100">{{ $note->message }}</flux:text>
                                    <div class="flex items-center gap-3 mt-2">
                                        <flux:badge size="sm">{{ ucfirst($note->type) }}</flux:badge>
                                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ $note->created_at->format('M j, Y g:i A') }}</flux:text>
                                        @if($note->user)
                                            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">by {{ $note->user->name }}</flux:text>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
        </div>

        <!-- Right Column - Order Summary -->
        <div class="lg:col-span-1">
            <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6 sticky top-6">
                <flux:heading size="lg" class="mb-4">Order Summary</flux:heading>

                <!-- Order Totals -->
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <flux:text>Subtotal</flux:text>
                        <flux:text>RM {{ number_format($order->subtotal, 2) }}</flux:text>
                    </div>

                    @if($order->discount_amount > 0)
                        <div class="flex justify-between text-green-600 dark:text-green-400">
                            <flux:text>Discount</flux:text>
                            <flux:text>-RM {{ number_format($order->discount_amount, 2) }}</flux:text>
                        </div>
                    @endif

                    @if($order->shipping_cost > 0)
                        <div class="flex justify-between">
                            <flux:text>Shipping</flux:text>
                            <flux:text>RM {{ number_format($order->shipping_cost, 2) }}</flux:text>
                        </div>
                    @endif

                    @if($order->tax_amount > 0)
                        <div class="flex justify-between">
                            <flux:text>Tax</flux:text>
                            <flux:text>RM {{ number_format($order->tax_amount, 2) }}</flux:text>
                        </div>
                    @endif

                    <div class="border-t-2 border-gray-200 dark:border-zinc-700 pt-3 mt-2">
                        <div class="flex justify-between">
                            <flux:text class="font-semibold text-lg">Total</flux:text>
                            <flux:text class="font-semibold text-lg text-blue-600 dark:text-blue-400">RM {{ number_format($order->total_amount, 2) }}</flux:text>
                        </div>
                    </div>
                </div>

                <!-- Status Badges -->
                <div class="mt-6 space-y-3">
                    <div class="flex items-center justify-between">
                        <flux:text class="text-sm">Order Status</flux:text>
                        <flux:badge
                            :color="match($order->status) {
                                'pending' => 'orange',
                                'processing' => 'blue',
                                'shipped' => 'purple',
                                'delivered' => 'green',
                                'cancelled' => 'red',
                                default => 'gray'
                            }"
                        >
                            {{ ucfirst($order->status) }}
                        </flux:badge>
                    </div>

                    <div class="flex items-center justify-between">
                        <flux:text class="text-sm">Payment Status</flux:text>
                        <flux:badge
                            :color="match($paymentStatus) {
                                'pending' => 'orange',
                                'completed' => 'green',
                                'failed' => 'red',
                                'refunded' => 'purple',
                                default => 'gray'
                            }"
                        >
                            {{ ucfirst($paymentStatus) }}
                        </flux:badge>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="mt-6 space-y-2">
                    <!-- Receipt/Invoice Actions -->
                    <flux:button variant="outline" class="w-full" :href="route('agent-orders.receipt', $order)" wire:navigate>
                        <div class="flex items-center justify-center">
                            <flux:icon name="document-text" class="w-4 h-4 mr-2" />
                            View Receipt
                        </div>
                    </flux:button>

                    @if($order->status === 'pending')
                        <flux:button variant="primary" class="w-full" wire:click="updateStatus('processing')">
                            <flux:icon name="play" class="w-4 h-4 mr-2" />
                            Start Processing
                        </flux:button>
                    @endif

                    @if($order->status === 'processing')
                        <flux:button variant="primary" class="w-full" wire:click="updateStatus('shipped')">
                            <flux:icon name="truck" class="w-4 h-4 mr-2" />
                            Mark as Shipped
                        </flux:button>
                    @endif

                    @if($order->status === 'shipped')
                        <flux:button variant="primary" class="w-full" wire:click="updateStatus('delivered')">
                            <flux:icon name="check-circle" class="w-4 h-4 mr-2" />
                            Mark as Delivered
                        </flux:button>
                    @endif

                    <flux:button variant="outline" class="w-full" :href="route('agents.show', $order->agent)" wire:navigate>
                        <flux:icon name="building-storefront" class="w-4 h-4 mr-2" />
                        View Agent
                    </flux:button>
                </div>

                <!-- Timestamps -->
                <div class="mt-6 pt-6 border-t border-gray-200 dark:border-zinc-700 text-sm text-zinc-600 dark:text-zinc-400 space-y-2">
                    <div>
                        <flux:text class="font-medium">Created</flux:text>
                        <flux:text>{{ $order->created_at->format('M j, Y g:i A') }}</flux:text>
                    </div>
                    @if($order->updated_at != $order->created_at)
                        <div>
                            <flux:text class="font-medium">Updated</flux:text>
                            <flux:text>{{ $order->updated_at->format('M j, Y g:i A') }}</flux:text>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
