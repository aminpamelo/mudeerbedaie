<?php

use App\Models\ProductOrder;
use App\Models\StockLevel;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Component;

new class extends Component
{
    public function layout()
    {
        return 'components.layouts.app.sidebar';
    }

    public ProductOrder $order;

    public $paymentStatus = 'pending';

    public $orderStatus = 'draft';

    public function mount(ProductOrder $order): void
    {
        $this->order = $order->load([
            'items.product',
            'items.warehouse',
            'user',
            'payments',
            'platform',
            'platformAccount',
            'notes.user',
        ]);

        // Get payment status from payments table
        $latestPayment = $this->order->payments()->latest()->first();
        $this->paymentStatus = $latestPayment?->status ?? 'pending';

        // Set order status to component property
        $this->orderStatus = $this->order->status;
    }

    public function updateStatus(string $status): void
    {
        DB::transaction(function () use ($status) {
            $previousStatus = $this->order->status;

            // Update order status
            $this->order->update(['status' => $status]);

            // Handle stock management based on status transitions
            $this->handleStockManagement($previousStatus, $status);

            // Add system note for status change
            $this->order->addSystemNote("Order status changed from {$previousStatus} to {$status}");

            // Update component property
            $this->orderStatus = $status;
        });

        session()->flash('success', 'Order status updated successfully!');
        $this->order->refresh();
    }

    public function updatePaymentStatus(string $paymentStatus): void
    {
        DB::transaction(function () use ($paymentStatus) {
            // Get or create payment record
            $payment = $this->order->payments()->latest()->first();

            if (! $payment) {
                // Create new payment record
                $payment = $this->order->payments()->create([
                    'payment_method' => 'cash',
                    'amount' => $this->order->total_amount,
                    'currency' => $this->order->currency,
                    'status' => $paymentStatus,
                    'paid_at' => $paymentStatus === 'completed' ? now() : null,
                ]);
            } else {
                // Update existing payment
                $payment->update([
                    'payment_method' => 'cash',
                    'amount' => $this->order->total_amount,
                    'status' => $paymentStatus,
                    'paid_at' => $paymentStatus === 'completed' ? now() : null,
                ]);
            }

            // Note: Stock management is now handled by order status changes, not payment status

            // Add system note
            $this->order->addSystemNote("Payment status changed to {$paymentStatus}");

            $this->paymentStatus = $paymentStatus;
        });

        session()->flash('success', 'Payment status updated successfully!');
        $this->order->refresh();
    }

    private function handleStockManagement(string $previousStatus, string $newStatus): void
    {
        // Define statuses that require stock deduction
        $stockDeductionStatuses = ['processing', 'shipped', 'delivered'];

        // Define statuses that should restore stock
        $stockRestorationStatuses = ['draft', 'pending', 'cancelled', 'refunded', 'returned'];

        $wasStockDeducted = in_array($previousStatus, $stockDeductionStatuses);
        $shouldDeductStock = in_array($newStatus, $stockDeductionStatuses);
        $shouldRestoreStock = in_array($newStatus, $stockRestorationStatuses);

        if (! $wasStockDeducted && $shouldDeductStock) {
            // Deduct stock when moving to a stock-deduction status
            $this->deductStock("Order status changed to {$newStatus}");
        } elseif ($wasStockDeducted && $shouldRestoreStock) {
            // Restore stock when moving from stock-deducted status to restoration status
            $this->restoreStock("Order status changed to {$newStatus}");
        }
    }

    private function deductStock(string $reason): void
    {
        foreach ($this->order->items as $item) {
            // Find or create stock level record
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
            $quantityAfter = max(0, $quantityBefore - $item->quantity_ordered);

            // Update stock level
            $stockLevel->update([
                'quantity' => $quantityAfter,
                'available_quantity' => max(0, $stockLevel->available_quantity - $item->quantity_ordered),
                'last_movement_at' => now(),
            ]);

            // Create stock movement record
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

                // Update stock level
                $stockLevel->update([
                    'quantity' => $quantityAfter,
                    'available_quantity' => $stockLevel->available_quantity + $item->quantity_ordered,
                    'last_movement_at' => now(),
                ]);

                // Create stock movement record
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
                <flux:heading size="xl">Order #{{ $order->platform_order_number ?: $order->order_number }}</flux:heading>
                @if($order->isPlatformOrder())
                    <flux:badge color="purple">
                        <flux:icon name="shopping-cart" class="w-3 h-3 mr-1" />
                        {{ $order->platform?->display_name ?? 'Platform' }}
                    </flux:badge>
                @endif
                @if($order->source === 'platform_import')
                    <flux:badge color="blue">Imported</flux:badge>
                @endif
            </div>
            <flux:text class="mt-2">
                Created {{ $order->order_date ? $order->order_date->format('M j, Y \a\t g:i A') : $order->created_at->format('M j, Y \a\t g:i A') }}
                @if($order->user)
                    by {{ $order->user->name }}
                @endif
                @if($order->platform_order_id && $order->platform_order_id !== $order->platform_order_number)
                    · Platform ID: {{ $order->platform_order_id }}
                @endif
            </flux:text>
        </div>
        <div class="flex space-x-3">
            <flux:button variant="outline" :href="route('admin.orders.edit', $order)" wire:navigate>
                <flux:icon name="pencil" class="w-4 h-4 mr-2" />
                Edit Order
            </flux:button>
            <flux:button variant="outline" :href="route('admin.orders.index')" wire:navigate>
                <flux:icon name="arrow-left" class="w-4 h-4 mr-2" />
                Back to Orders
            </flux:button>
        </div>
    </div>

    <div class="grid lg:grid-cols-3 gap-6">
        <!-- Left Column - Order Details -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Order Status -->
            <div class="bg-white rounded-lg shadow-sm border p-6">
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

                <div class="mt-4 grid md:grid-cols-2 gap-4">
                    <div>
                        <flux:text class="text-sm text-zinc-600">Payment Method</flux:text>
                        <flux:text class="font-medium capitalize">
                            @if($order->payments->count() > 0)
                                {{ str_replace('_', ' ', $order->payments->sortByDesc('created_at')->first()->payment_method) }}
                            @else
                                Not Set
                            @endif
                        </flux:text>
                    </div>
                    <div>
                        <flux:text class="text-sm text-zinc-600">Currency</flux:text>
                        <flux:text class="font-medium">{{ $order->currency }}</flux:text>
                    </div>
                </div>
            </div>

            <!-- Platform & Shipping Information (if platform order) -->
            @if($order->isPlatformOrder())
                <div class="bg-white rounded-lg shadow-sm border p-6">
                    <flux:heading size="lg" class="mb-4">Platform & Shipping Details</flux:heading>

                    <div class="grid md:grid-cols-2 gap-6">
                        @if($order->platform)
                            <div>
                                <flux:text class="text-sm text-zinc-600">Platform</flux:text>
                                <flux:text class="font-medium">{{ $order->platform->display_name }}</flux:text>
                                @if($order->platformAccount)
                                    <flux:text class="text-sm text-zinc-600 mt-1">Account: {{ $order->platformAccount->name }}</flux:text>
                                @endif
                            </div>
                        @endif

                        @if($order->tracking_id)
                            <div>
                                <flux:text class="text-sm text-zinc-600">Tracking ID</flux:text>
                                <flux:text class="font-medium">{{ $order->tracking_id }}</flux:text>
                            </div>
                        @endif

                        @if($order->package_id)
                            <div>
                                <flux:text class="text-sm text-zinc-600">Package ID</flux:text>
                                <flux:text class="font-medium">{{ $order->package_id }}</flux:text>
                            </div>
                        @endif

                        @if($order->shipping_provider)
                            <div>
                                <flux:text class="text-sm text-zinc-600">Shipping Provider</flux:text>
                                <flux:text class="font-medium">{{ $order->shipping_provider }}</flux:text>
                            </div>
                        @endif

                        @if($order->fulfillment_type)
                            <div>
                                <flux:text class="text-sm text-zinc-600">Fulfillment Type</flux:text>
                                <flux:text class="font-medium capitalize">{{ str_replace('_', ' ', $order->fulfillment_type) }}</flux:text>
                            </div>
                        @endif

                        @if($order->delivery_option)
                            <div>
                                <flux:text class="text-sm text-zinc-600">Delivery Option</flux:text>
                                <flux:text class="font-medium">{{ $order->delivery_option }}</flux:text>
                            </div>
                        @endif

                        @if($order->weight_kg)
                            <div>
                                <flux:text class="text-sm text-zinc-600">Weight</flux:text>
                                <flux:text class="font-medium">{{ $order->formatted_weight }}</flux:text>
                            </div>
                        @endif

                        @if($order->buyer_username)
                            <div>
                                <flux:text class="text-sm text-zinc-600">Buyer Username</flux:text>
                                <flux:text class="font-medium">{{ $order->buyer_username }}</flux:text>
                            </div>
                        @endif
                    </div>

                    <!-- Buyer Message -->
                    @if($order->buyer_message)
                        <div class="mt-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                            <div class="flex items-start gap-2">
                                <flux:icon name="chat-bubble-left" class="w-5 h-5 text-yellow-600 flex-shrink-0" />
                                <div>
                                    <flux:text class="text-sm font-medium text-yellow-900">Buyer Message</flux:text>
                                    <flux:text class="text-sm text-yellow-800 mt-1">{{ $order->buyer_message }}</flux:text>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Seller Note -->
                    @if($order->seller_note)
                        <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                            <div class="flex items-start gap-2">
                                <flux:icon name="document-text" class="w-5 h-5 text-blue-600 flex-shrink-0" />
                                <div>
                                    <flux:text class="text-sm font-medium text-blue-900">Seller Note</flux:text>
                                    <flux:text class="text-sm text-blue-800 mt-1">{{ $order->seller_note }}</flux:text>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Order Timeline for Platform Orders -->
                    @if($order->paid_time || $order->rts_time || $order->shipped_at || $order->delivered_at)
                        <div class="mt-6 pt-6 border-t">
                            <flux:heading size="sm" class="mb-3">Order Timeline</flux:heading>
                            <div class="space-y-2 text-sm">
                                @if($order->paid_time)
                                    <div class="flex justify-between">
                                        <flux:text class="text-zinc-600">Paid</flux:text>
                                        <flux:text class="font-medium">{{ $order->paid_time->format('M j, Y g:i A') }}</flux:text>
                                    </div>
                                @endif
                                @if($order->rts_time)
                                    <div class="flex justify-between">
                                        <flux:text class="text-zinc-600">Ready to Ship</flux:text>
                                        <flux:text class="font-medium">{{ $order->rts_time->format('M j, Y g:i A') }}</flux:text>
                                    </div>
                                @endif
                                @if($order->shipped_at)
                                    <div class="flex justify-between">
                                        <flux:text class="text-zinc-600">Shipped</flux:text>
                                        <flux:text class="font-medium">{{ $order->shipped_at->format('M j, Y g:i A') }}</flux:text>
                                    </div>
                                @endif
                                @if($order->delivered_at)
                                    <div class="flex justify-between">
                                        <flux:text class="text-zinc-600">Delivered</flux:text>
                                        <flux:text class="font-medium">{{ $order->delivered_at->format('M j, Y g:i A') }}</flux:text>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            <!-- Order Items -->
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <flux:heading size="lg" class="mb-4">Order Items</flux:heading>

                <div class="space-y-4">
                    @foreach($order->items as $item)
                        <div class="flex items-center justify-between border-b pb-4">
                            <div class="flex items-center space-x-4">
                                <div class="w-16 h-16 bg-zinc-100 rounded-lg flex items-center justify-center">
                                    <flux:icon name="cube" class="w-8 h-8 text-zinc-400" />
                                </div>
                                <div>
                                    <flux:heading class="font-medium">{{ $item->product?->name ?? $item->product_name ?? 'Unknown Product' }}</flux:heading>
                                    @if($item->product)
                                        <flux:text class="text-sm text-zinc-600">
                                            SKU: {{ $item->product->sku }}
                                        </flux:text>
                                    @elseif($item->platform_sku)
                                        <flux:text class="text-sm text-zinc-600">
                                            Platform SKU: {{ $item->platform_sku }}
                                        </flux:text>
                                    @endif
                                    <flux:text class="text-sm text-zinc-600">
                                        Warehouse: {{ $item->warehouse?->name ?? 'Not assigned' }}
                                    </flux:text>
                                </div>
                            </div>
                            <div class="text-right">
                                <flux:text class="font-medium">{{ $item->quantity }} × MYR {{ number_format($item->unit_price, 2) }}</flux:text>
                                <flux:text class="text-sm text-zinc-600">MYR {{ number_format($item->total_price, 2) }}</flux:text>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Customer Information -->
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <flux:heading size="lg" class="mb-4">Customer Information</flux:heading>

                <div class="grid md:grid-cols-2 gap-6">
                    <div>
                        <flux:text class="text-sm text-zinc-600">Customer</flux:text>
                        <flux:text class="font-medium">{{ $order->getCustomerName() }}</flux:text>
                        <flux:text class="text-sm text-zinc-600 mt-1">{{ $order->getCustomerEmail() }}</flux:text>

                        <!-- Show phone from multiple sources -->
                        @php
                            $phone = $order->customer_phone ?? $order->billingAddress()?->phone ?? null;
                        @endphp
                        @if($phone)
                            <flux:text class="text-sm text-zinc-600">{{ $phone }}</flux:text>
                        @endif
                    </div>

                    @if($order->user)
                        <div>
                            <flux:text class="text-sm text-zinc-600">Account</flux:text>
                            <flux:text class="font-medium">{{ $order->user->name }}</flux:text>
                            <flux:text class="text-sm text-zinc-600 mt-1">{{ $order->user->email }}</flux:text>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Addresses -->
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <flux:heading size="lg" class="mb-4">Addresses</flux:heading>

                @php
                    $shippingAddressModel = $order->shippingAddress();
                    $billingAddressModel = $order->billingAddress();
                    $platformShipping = $order->shipping_address; // JSON field for platform orders
                @endphp

                <div class="grid md:grid-cols-2 gap-6">
                    <!-- Billing Address -->
                    <div>
                        <flux:text class="font-medium mb-2">Billing Address</flux:text>
                        <div class="text-sm text-zinc-600 space-y-1">
                            @if($billingAddressModel)
                                <div>{{ $billingAddressModel->first_name }} {{ $billingAddressModel->last_name }}</div>
                                @if($billingAddressModel->company)
                                    <div>{{ $billingAddressModel->company }}</div>
                                @endif
                                <div>{{ $billingAddressModel->address_line_1 }}</div>
                                @if($billingAddressModel->address_line_2)
                                    <div>{{ $billingAddressModel->address_line_2 }}</div>
                                @endif
                                <div>{{ $billingAddressModel->city }}, {{ $billingAddressModel->state }} {{ $billingAddressModel->postal_code }}</div>
                                <div>{{ $billingAddressModel->country }}</div>
                            @else
                                <div class="text-zinc-500 italic">No billing address provided</div>
                            @endif
                        </div>
                    </div>

                    <!-- Shipping Address -->
                    <div>
                        <flux:text class="font-medium mb-2">Shipping Address</flux:text>
                        <div class="text-sm text-zinc-600 space-y-1">
                            @if($shippingAddressModel)
                                {{-- Traditional address model --}}
                                <div>{{ $shippingAddressModel->first_name }} {{ $shippingAddressModel->last_name }}</div>
                                @if($shippingAddressModel->company)
                                    <div>{{ $shippingAddressModel->company }}</div>
                                @endif
                                <div>{{ $shippingAddressModel->address_line_1 }}</div>
                                @if($shippingAddressModel->address_line_2)
                                    <div>{{ $shippingAddressModel->address_line_2 }}</div>
                                @endif
                                <div>{{ $shippingAddressModel->city }}, {{ $shippingAddressModel->state }} {{ $shippingAddressModel->postal_code }}</div>
                                <div>{{ $shippingAddressModel->country }}</div>
                            @elseif($platformShipping && is_array($platformShipping))
                                {{-- Platform order JSON address --}}
                                @if(!empty($platformShipping['detail_address']))
                                    <div>{{ $platformShipping['detail_address'] }}</div>
                                @endif
                                @if(!empty($platformShipping['additional_info']))
                                    <div>{{ $platformShipping['additional_info'] }}</div>
                                @endif
                                @php
                                    $cityState = implode(', ', array_filter([
                                        $platformShipping['city'] ?? null,
                                        $platformShipping['state'] ?? null,
                                        $platformShipping['postal_code'] ?? null
                                    ]));
                                @endphp
                                @if($cityState)
                                    <div>{{ $cityState }}</div>
                                @endif
                                @if(!empty($platformShipping['country']))
                                    <div>{{ $platformShipping['country'] }}</div>
                                @endif
                            @else
                                <div class="text-zinc-500 italic">No shipping address provided</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Order Notes -->
            @if($order->notes->count() > 0)
                <div class="bg-white rounded-lg shadow-sm border p-6">
                    <flux:heading size="lg" class="mb-4">Order Notes</flux:heading>

                    <div class="space-y-4">
                        @foreach($order->notes()->orderBy('created_at', 'desc')->get() as $note)
                            <div class="border-l-4 @if($note->type === 'system') border-blue-500 @elseif($note->type === 'customer') border-green-500 @else border-amber-500 @endif pl-4 py-2">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <flux:text class="text-zinc-900">{{ $note->message }}</flux:text>
                                        <div class="flex items-center gap-3 mt-2">
                                            <flux:badge
                                                variant="@if($note->type === 'system') subtle @elseif($note->type === 'customer') positive @else warning @endif"
                                                size="sm"
                                            >
                                                {{ ucfirst($note->type) }}
                                            </flux:badge>

                                            <flux:text class="text-xs text-zinc-500">
                                                {{ $note->created_at->format('M j, Y \a\t g:i A') }}
                                            </flux:text>

                                            @if($note->user)
                                                <flux:text class="text-xs text-zinc-500">
                                                    by {{ $note->user->name }}
                                                </flux:text>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <!-- Right Column - Order Summary -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow-sm border p-6 sticky top-6">
                <flux:heading size="lg" class="mb-4">Order Summary</flux:heading>

                <!-- Order Totals -->
                <div class="space-y-3">
                    @php
                        // Calculate all discounts for platform orders
                        $totalDiscount = 0;
                        $subtotalBeforeDiscount = $order->subtotal;

                        if($order->isPlatformOrder()) {
                            $totalDiscount = $order->sku_platform_discount + $order->sku_seller_discount +
                                           $order->shipping_fee_seller_discount + $order->shipping_fee_platform_discount +
                                           $order->payment_platform_discount;

                            // Calculate subtotal before discount
                            if($totalDiscount > 0) {
                                $subtotalBeforeDiscount = $order->subtotal + $order->sku_platform_discount + $order->sku_seller_discount;
                            }
                        } else {
                            $totalDiscount = $order->discount_amount;
                            if($totalDiscount > 0) {
                                $subtotalBeforeDiscount = $order->subtotal + $totalDiscount;
                            }
                        }

                        // Calculate original shipping cost for platform orders
                        $originalShipping = $order->shipping_cost;
                        $shippingDiscount = 0;
                        if($order->isPlatformOrder() && $order->original_shipping_fee) {
                            $originalShipping = $order->original_shipping_fee;
                            $shippingDiscount = $order->shipping_fee_seller_discount + $order->shipping_fee_platform_discount;
                        }
                    @endphp

                    <!-- Subtotal Before Discount (for clarity) -->
                    @if($totalDiscount > 0)
                        <div class="flex justify-between text-sm">
                            <flux:text class="text-zinc-600">Subtotal (Before Discount)</flux:text>
                            <flux:text class="text-zinc-600">{{ $order->currency }} {{ number_format($subtotalBeforeDiscount, 2) }}</flux:text>
                        </div>

                        <!-- Platform Discounts Breakdown -->
                        @if($order->isPlatformOrder())
                            @if($order->sku_platform_discount > 0)
                                <div class="flex justify-between text-sm pl-3">
                                    <flux:text class="text-green-600">- Platform Discount</flux:text>
                                    <flux:text class="text-green-600">-{{ $order->currency }} {{ number_format($order->sku_platform_discount, 2) }}</flux:text>
                                </div>
                            @endif
                            @if($order->sku_seller_discount > 0)
                                <div class="flex justify-between text-sm pl-3">
                                    <flux:text class="text-green-600">- Seller Discount</flux:text>
                                    <flux:text class="text-green-600">-{{ $order->currency }} {{ number_format($order->sku_seller_discount, 2) }}</flux:text>
                                </div>
                            @endif
                            @if($order->payment_platform_discount > 0)
                                <div class="flex justify-between text-sm pl-3">
                                    <flux:text class="text-green-600">- Payment Discount</flux:text>
                                    <flux:text class="text-green-600">-{{ $order->currency }} {{ number_format($order->payment_platform_discount, 2) }}</flux:text>
                                </div>
                            @endif
                        @else
                            <div class="flex justify-between text-sm pl-3">
                                <flux:text class="text-green-600">- Discount</flux:text>
                                <flux:text class="text-green-600">-{{ $order->currency }} {{ number_format($totalDiscount, 2) }}</flux:text>
                            </div>
                        @endif
                    @endif

                    <!-- Subtotal (After Discount) -->
                    <div class="flex justify-between {{ $totalDiscount > 0 ? 'font-medium' : '' }}">
                        <flux:text>{{ $totalDiscount > 0 ? 'Subtotal' : 'Subtotal' }}</flux:text>
                        <flux:text>{{ $order->currency }} {{ number_format($order->subtotal, 2) }}</flux:text>
                    </div>

                    <!-- Shipping Cost -->
                    @if($order->shipping_cost > 0 || $originalShipping > 0)
                        @if($order->isPlatformOrder() && $shippingDiscount > 0)
                            <!-- Show original shipping -->
                            <div class="flex justify-between text-sm">
                                <flux:text class="text-zinc-600">Shipping (Original)</flux:text>
                                <flux:text class="text-zinc-600">{{ $order->currency }} {{ number_format($originalShipping, 2) }}</flux:text>
                            </div>
                            <!-- Show shipping discounts -->
                            @if($order->shipping_fee_platform_discount > 0)
                                <div class="flex justify-between text-sm pl-3">
                                    <flux:text class="text-green-600">- Platform Shipping Discount</flux:text>
                                    <flux:text class="text-green-600">-{{ $order->currency }} {{ number_format($order->shipping_fee_platform_discount, 2) }}</flux:text>
                                </div>
                            @endif
                            @if($order->shipping_fee_seller_discount > 0)
                                <div class="flex justify-between text-sm pl-3">
                                    <flux:text class="text-green-600">- Seller Shipping Discount</flux:text>
                                    <flux:text class="text-green-600">-{{ $order->currency }} {{ number_format($order->shipping_fee_seller_discount, 2) }}</flux:text>
                                </div>
                            @endif
                            <!-- Final shipping cost -->
                            <div class="flex justify-between font-medium">
                                <flux:text>Shipping</flux:text>
                                <flux:text>{{ $order->currency }} {{ number_format($order->shipping_cost, 2) }}</flux:text>
                            </div>
                        @else
                            <div class="flex justify-between">
                                <flux:text>Shipping</flux:text>
                                <flux:text>{{ $order->currency }} {{ number_format($order->shipping_cost, 2) }}</flux:text>
                            </div>
                        @endif
                    @endif

                    <!-- Tax -->
                    @if($order->tax_amount > 0)
                        <div class="flex justify-between">
                            <flux:text>Tax</flux:text>
                            <flux:text>{{ $order->currency }} {{ number_format($order->tax_amount, 2) }}</flux:text>
                        </div>
                    @endif

                    <!-- Total -->
                    <div class="border-t-2 pt-3 mt-2">
                        <div class="flex justify-between">
                            <flux:text class="font-semibold text-lg">Total</flux:text>
                            <flux:text class="font-semibold text-lg text-blue-600">{{ $order->currency }} {{ number_format($order->total_amount, 2) }}</flux:text>
                        </div>
                    </div>

                    <!-- Calculation Summary Box (for complex orders) -->
                    @if($totalDiscount > 0 || $shippingDiscount > 0)
                        <div class="mt-4 p-3 bg-blue-50 rounded-lg border border-blue-200">
                            <div class="text-xs font-medium text-blue-900 mb-2">Order Calculation</div>
                            <div class="space-y-1 text-xs">
                                @if($totalDiscount > 0)
                                    <div class="flex justify-between text-blue-800">
                                        <span>Items Before Discount:</span>
                                        <span>{{ $order->currency }} {{ number_format($subtotalBeforeDiscount, 2) }}</span>
                                    </div>
                                    <div class="flex justify-between text-green-700">
                                        <span>- Total Item Discounts:</span>
                                        <span>-{{ $order->currency }} {{ number_format($totalDiscount - $shippingDiscount, 2) }}</span>
                                    </div>
                                    <div class="flex justify-between text-blue-800">
                                        <span>= Items Subtotal:</span>
                                        <span>{{ $order->currency }} {{ number_format($order->subtotal, 2) }}</span>
                                    </div>
                                @else
                                    <div class="flex justify-between text-blue-800">
                                        <span>Items Subtotal:</span>
                                        <span>{{ $order->currency }} {{ number_format($order->subtotal, 2) }}</span>
                                    </div>
                                @endif

                                @if($order->shipping_cost > 0)
                                    @if($shippingDiscount > 0)
                                        <div class="flex justify-between text-blue-800">
                                            <span>+ Original Shipping:</span>
                                            <span>{{ $order->currency }} {{ number_format($originalShipping, 2) }}</span>
                                        </div>
                                        <div class="flex justify-between text-green-700">
                                            <span>- Shipping Discounts:</span>
                                            <span>-{{ $order->currency }} {{ number_format($shippingDiscount, 2) }}</span>
                                        </div>
                                        <div class="flex justify-between text-blue-800">
                                            <span>= Final Shipping:</span>
                                            <span>{{ $order->currency }} {{ number_format($order->shipping_cost, 2) }}</span>
                                        </div>
                                    @else
                                        <div class="flex justify-between text-blue-800">
                                            <span>+ Shipping:</span>
                                            <span>{{ $order->currency }} {{ number_format($order->shipping_cost, 2) }}</span>
                                        </div>
                                    @endif
                                @endif

                                @if($order->tax_amount > 0)
                                    <div class="flex justify-between text-blue-800">
                                        <span>+ Tax:</span>
                                        <span>{{ $order->currency }} {{ number_format($order->tax_amount, 2) }}</span>
                                    </div>
                                @endif

                                <div class="flex justify-between font-bold text-blue-900 pt-2 mt-1 border-t border-blue-300">
                                    <span>= Final Total:</span>
                                    <span>{{ $order->currency }} {{ number_format($order->total_amount, 2) }}</span>
                                </div>

                                @if($totalDiscount > 0)
                                    <div class="flex justify-between text-green-700 font-medium pt-1 mt-1 border-t border-blue-200">
                                        <span>Total Savings:</span>
                                        <span>{{ $order->currency }} {{ number_format($totalDiscount, 2) }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
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
                    @if($order->status !== 'delivered' && $order->status !== 'cancelled')
                        <flux:button variant="outline" class="w-full">
                            <flux:icon name="printer" class="w-4 h-4 mr-2" />
                            Print Invoice
                        </flux:button>
                    @endif

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
                </div>

                <!-- Timestamps -->
                <div class="mt-6 pt-6 border-t text-sm text-zinc-600 space-y-2">
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
                    @if($order->created_by)
                        <div>
                            <flux:text class="font-medium">Created by</flux:text>
                            <flux:text>{{ $order->createdBy->name ?? 'Unknown' }}</flux:text>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>