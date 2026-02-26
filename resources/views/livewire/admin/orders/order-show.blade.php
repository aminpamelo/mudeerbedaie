<?php

use App\DTOs\Shipping\ShipmentRequest;
use App\Models\ProductOrder;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Services\SettingsService;
use App\Services\Shipping\ShippingManager;
use App\Services\TikTok\OrderItemLinker;
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

    // Shipping
    public bool $showTrackingModal = false;
    public array $trackingInfo = [];
    public string $manualTrackingId = '';

    public function mount(ProductOrder $order): void
    {
        $this->order = $order->load([
            'items.product',
            'items.package',
            'items.warehouse',
            'user',
            'payments',
            'platform',
            'platformAccount',
            'notes.user',
        ]);

        // Get payment status from payments table, fallback to paid_time for POS orders
        $latestPayment = $this->order->payments()->latest()->first();
        if ($latestPayment) {
            $this->paymentStatus = $latestPayment->status;
        } elseif ($this->order->paid_time) {
            $this->paymentStatus = 'completed';
        } else {
            $this->paymentStatus = 'pending';
        }

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
            // Skip items without warehouse assignment
            if (! $item->warehouse_id) {
                \Log::warning('Cannot deduct stock - no warehouse assigned', [
                    'order_id' => $this->order->id,
                    'item_id' => $item->id,
                    'product_id' => $item->product_id,
                ]);

                continue;
            }

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
            // Allow negative stock - don't use max(0, ...)
            $quantityAfter = $quantityBefore - $item->quantity_ordered;

            // Update stock level (allow negative quantities)
            $stockLevel->update([
                'quantity' => $quantityAfter,
                'available_quantity' => $stockLevel->available_quantity - $item->quantity_ordered,
                'last_movement_at' => now(),
            ]);

            // Log warning if stock goes negative
            if ($quantityAfter < 0) {
                \Log::warning('Stock level is now NEGATIVE', [
                    'order_id' => $this->order->id,
                    'product_id' => $item->product_id,
                    'warehouse_id' => $item->warehouse_id,
                    'quantity_before' => $quantityBefore,
                    'quantity_after' => $quantityAfter,
                    'shortage' => abs($quantityAfter),
                ]);
            }

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
                'notes' => "Stock deducted: {$reason} (Order #{$this->order->order_number})".
                    ($quantityAfter < 0 ? ' [WARNING: Stock is now NEGATIVE by '.abs($quantityAfter).' units]' : ''),
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

    public function linkOrderItems(): void
    {
        if (! $this->order->platform_id || ! $this->order->platform_account_id) {
            session()->flash('error', 'This order has no platform account linked.');

            return;
        }

        $linker = app(OrderItemLinker::class);
        $linked = 0;

        foreach ($this->order->items as $item) {
            if (! $item->product_id && ! $item->package_id) {
                if ($linker->linkItemToMapping($item, $this->order->platform_id, $this->order->platform_account_id)) {
                    $linked++;
                }
            }
        }

        if ($linked > 0) {
            $this->order->addSystemNote("Manually linked {$linked} item(s) to internal products/packages");
            session()->flash('success', "Linked {$linked} item(s) to internal products/packages.");
        } else {
            session()->flash('info', 'No items could be linked. Check that SKU mappings exist for this account.');
        }

        $this->order = $this->order->fresh([
            'items.product',
            'items.package',
            'items.warehouse',
            'user',
            'payments',
            'platform',
            'platformAccount',
            'notes.user',
        ]);
    }

    public function deductOrderStock(): void
    {
        $linker = app(OrderItemLinker::class);
        $result = $linker->deductStockForOrder($this->order);

        if ($result['deducted'] > 0) {
            $this->order->addSystemNote("Manually deducted stock for {$result['deducted']} item(s)");
            session()->flash('success', "Stock deducted for {$result['deducted']} item(s).");
        } else {
            session()->flash('info', 'No stock was deducted. Items may already have been deducted or are missing warehouse assignments.');
        }

        $this->order->refresh();
    }

    public function createJntShipment(): void
    {
        try {
            $shippingManager = app(ShippingManager::class);
            $jntService = $shippingManager->getProvider('jnt');
            $senderDefaults = app(SettingsService::class)->getShippingSenderDefaults();
            $shippingAddress = $this->order->addresses()->where('type', 'shipping')->first()
                ?? $this->order->addresses()->where('type', 'billing')->first();

            if (! $shippingAddress) {
                session()->flash('error', 'No shipping address found for this order.');
                return;
            }

            $request = new ShipmentRequest(
                orderNumber: $this->order->order_number,
                senderName: $senderDefaults['name'] ?: 'Sender',
                senderPhone: $senderDefaults['phone'] ?: '',
                senderAddress: $senderDefaults['address'] ?: '',
                senderCity: $senderDefaults['city'] ?: '',
                senderState: $senderDefaults['state'] ?: '',
                senderPostalCode: $senderDefaults['postal_code'] ?: '',
                receiverName: trim($shippingAddress->first_name.' '.$shippingAddress->last_name),
                receiverPhone: $shippingAddress->phone ?: '',
                receiverAddress: trim($shippingAddress->address_line_1.' '.$shippingAddress->address_line_2),
                receiverCity: $shippingAddress->city ?: '',
                receiverState: $shippingAddress->state ?: '',
                receiverPostalCode: $shippingAddress->postal_code ?: '',
                weightKg: $this->order->weight_kg ?: 0.5,
                itemDescription: 'Order '.$this->order->order_number,
                itemValue: $this->order->total_amount,
                itemQuantity: $this->order->items->sum('quantity_ordered'),
                serviceCode: $this->order->delivery_option ?: app(SettingsService::class)->get('jnt_default_service_type', 'EZ'),
            );

            $result = $jntService->createShipment($request);

            if ($result->success) {
                $this->order->update([
                    'tracking_id' => $result->trackingNumber,
                    'shipping_provider' => 'jnt',
                    'status' => 'shipped',
                    'shipped_at' => now(),
                ]);
                $this->orderStatus = 'shipped';
                $this->order->addSystemNote("JNT shipment created. Tracking: {$result->trackingNumber}");
                session()->flash('success', "Shipment created successfully! Tracking: {$result->trackingNumber}");
            } else {
                session()->flash('error', "Failed to create shipment: {$result->message}");
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Shipment creation failed: '.$e->getMessage());
        }

        $this->order->refresh();
    }

    public function viewTracking(): void
    {
        if (! $this->order->tracking_id || ! $this->order->shipping_provider) {
            return;
        }

        try {
            $shippingManager = app(ShippingManager::class);
            $provider = $shippingManager->getProvider($this->order->shipping_provider);
            $result = $provider->getTracking($this->order->tracking_id);

            $this->trackingInfo = $result->events;
            $this->showTrackingModal = true;
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to fetch tracking: '.$e->getMessage());
        }
    }

    public function cancelJntShipment(): void
    {
        if (! $this->order->tracking_id) {
            return;
        }

        try {
            $shippingManager = app(ShippingManager::class);
            $provider = $shippingManager->getProvider($this->order->shipping_provider);
            $result = $provider->cancelShipment($this->order->tracking_id);

            if ($result->success) {
                $oldTracking = $this->order->tracking_id;
                $this->order->update([
                    'tracking_id' => null,
                    'shipping_provider' => null,
                    'status' => 'processing',
                    'shipped_at' => null,
                ]);
                $this->orderStatus = 'processing';
                $this->order->addSystemNote("JNT shipment cancelled. Previous tracking: {$oldTracking}");
                session()->flash('success', 'Shipment cancelled successfully.');
            } else {
                session()->flash('error', "Failed to cancel shipment: {$result->message}");
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Shipment cancellation failed: '.$e->getMessage());
        }

        $this->order->refresh();
    }

    public function setManualTracking(): void
    {
        if (empty($this->manualTrackingId)) {
            return;
        }

        $this->order->update([
            'tracking_id' => $this->manualTrackingId,
            'status' => 'shipped',
            'shipped_at' => now(),
        ]);
        $this->orderStatus = 'shipped';
        $this->order->addSystemNote("Manual tracking set: {$this->manualTrackingId}");
        session()->flash('success', 'Tracking number saved.');
        $this->manualTrackingId = '';
        $this->order->refresh();
    }

    public function closeTrackingModal(): void
    {
        $this->showTrackingModal = false;
        $this->trackingInfo = [];
    }

    private function isJntEnabled(): bool
    {
        return app(SettingsService::class)->isJntEnabled();
    }
}; ?>

<div>
    @if (session()->has('success'))
        <flux:callout variant="success" class="mb-6">
            {{ session('success') }}
        </flux:callout>
    @endif

    @if (session()->has('error'))
        <flux:callout variant="danger" class="mb-6">
            {{ session('error') }}
        </flux:callout>
    @endif

    @if (session()->has('info'))
        <flux:callout variant="warning" class="mb-6">
            {{ session('info') }}
        </flux:callout>
    @endif

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

                <div class="mt-4 grid md:grid-cols-2 gap-4">
                    <div>
                        <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">Payment Method</flux:text>
                        <flux:text class="font-medium capitalize">
                            @if($order->payments->count() > 0)
                                {{ str_replace('_', ' ', $order->payments->sortByDesc('created_at')->first()->payment_method) }}
                            @elseif($order->payment_method)
                                {{ str_replace('_', ' ', $order->payment_method) }}
                            @else
                                Not Set
                            @endif
                        </flux:text>
                    </div>
                    <div>
                        <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">Currency</flux:text>
                        <flux:text class="font-medium">{{ $order->currency }}</flux:text>
                    </div>
                </div>
            </div>

            <!-- Platform & Shipping Information (if platform order) -->
            @if($order->isPlatformOrder())
                <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6">
                    <flux:heading size="lg" class="mb-4">Platform & Shipping Details</flux:heading>

                    <div class="grid md:grid-cols-2 gap-6">
                        @if($order->platform)
                            <div>
                                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">Platform</flux:text>
                                <flux:text class="font-medium">{{ $order->platform->display_name }}</flux:text>
                                @if($order->platformAccount)
                                    <flux:text class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">Account: {{ $order->platformAccount->name }}</flux:text>
                                @endif
                            </div>
                        @endif

                        @if($order->tracking_id)
                            <div>
                                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">Tracking ID</flux:text>
                                <flux:text class="font-medium">{{ $order->tracking_id }}</flux:text>
                            </div>
                        @endif

                        @if($order->package_id)
                            <div>
                                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">Package ID</flux:text>
                                <flux:text class="font-medium">{{ $order->package_id }}</flux:text>
                            </div>
                        @endif

                        @if($order->shipping_provider)
                            <div>
                                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">Shipping Provider</flux:text>
                                <flux:text class="font-medium">{{ $order->shipping_provider }}</flux:text>
                            </div>
                        @endif

                        @if($order->fulfillment_type)
                            <div>
                                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">Fulfillment Type</flux:text>
                                <flux:text class="font-medium capitalize">{{ str_replace('_', ' ', $order->fulfillment_type) }}</flux:text>
                            </div>
                        @endif

                        @if($order->delivery_option)
                            <div>
                                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">Delivery Option</flux:text>
                                <flux:text class="font-medium">{{ $order->delivery_option }}</flux:text>
                            </div>
                        @endif

                        @if($order->weight_kg)
                            <div>
                                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">Weight</flux:text>
                                <flux:text class="font-medium">{{ $order->formatted_weight }}</flux:text>
                            </div>
                        @endif

                        @if($order->buyer_username)
                            <div>
                                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">Buyer Username</flux:text>
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

            <!-- Shipping Management (non-platform orders) -->
            @if(!$order->isPlatformOrder())
                <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6">
                    <flux:heading size="lg" class="mb-4">Shipping Information</flux:heading>

                    @if($order->tracking_id)
                        <!-- Tracking Info -->
                        <div class="grid md:grid-cols-2 gap-6 mb-4">
                            <div>
                                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">Tracking Number</flux:text>
                                <flux:text class="font-medium">{{ $order->tracking_id }}</flux:text>
                            </div>
                            @if($order->shipping_provider)
                                <div>
                                    <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">Provider</flux:text>
                                    <flux:text class="font-medium capitalize">{{ $order->shipping_provider === 'jnt' ? 'J&T Express' : $order->shipping_provider }}</flux:text>
                                </div>
                            @endif
                            @if($order->delivery_option)
                                <div>
                                    <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">Service</flux:text>
                                    <flux:text class="font-medium">{{ $order->delivery_option }}</flux:text>
                                </div>
                            @endif
                            @if($order->weight_kg)
                                <div>
                                    <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">Weight</flux:text>
                                    <flux:text class="font-medium">{{ number_format($order->weight_kg, 2) }} kg</flux:text>
                                </div>
                            @endif
                            @if($order->shipping_cost)
                                <div>
                                    <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">Shipping Cost</flux:text>
                                    <flux:text class="font-medium">MYR {{ number_format($order->shipping_cost, 2) }}</flux:text>
                                </div>
                            @endif
                            @if($order->shipped_at)
                                <div>
                                    <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">Shipped At</flux:text>
                                    <flux:text class="font-medium">{{ $order->shipped_at->format('M j, Y g:i A') }}</flux:text>
                                </div>
                            @endif
                        </div>

                        <div class="flex gap-2">
                            @if($order->shipping_provider)
                                <flux:button variant="outline" size="sm" wire:click="viewTracking">
                                    <div class="flex items-center justify-center">
                                        <flux:icon name="magnifying-glass" class="w-4 h-4 mr-1" />
                                        View Tracking
                                    </div>
                                </flux:button>

                                @if($order->status === 'shipped')
                                    <flux:button variant="outline" size="sm" wire:click="cancelJntShipment"
                                        wire:confirm="Are you sure you want to cancel this shipment?"
                                    >
                                        <div class="flex items-center justify-center">
                                            <flux:icon name="x-mark" class="w-4 h-4 mr-1" />
                                            Cancel Shipment
                                        </div>
                                    </flux:button>
                                @endif
                            @endif
                        </div>
                    @else
                        <!-- No tracking - show actions -->
                        <div class="space-y-4">
                            @if($this->isJntEnabled() && in_array($order->status, ['confirmed', 'processing']))
                                <div class="p-4 rounded-lg border border-blue-200 bg-blue-50">
                                    <flux:text class="text-sm text-blue-800 mb-3">Create a J&T Express shipment for this order.</flux:text>
                                    <flux:button variant="primary" size="sm" wire:click="createJntShipment" wire:loading.attr="disabled">
                                        <div class="flex items-center justify-center">
                                            <flux:icon name="truck" class="w-4 h-4 mr-1" />
                                            <span wire:loading.remove wire:target="createJntShipment">Create JNT Shipment</span>
                                            <span wire:loading wire:target="createJntShipment">Creating...</span>
                                        </div>
                                    </flux:button>
                                </div>
                            @endif

                            <!-- Manual Tracking Entry -->
                            <div class="p-4 rounded-lg border border-gray-200">
                                <flux:text class="text-sm text-gray-600 mb-3">Or manually enter a tracking number:</flux:text>
                                <div class="flex gap-2">
                                    <flux:input wire:model="manualTrackingId" placeholder="Enter tracking number" size="sm" />
                                    <flux:button variant="outline" size="sm" wire:click="setManualTracking">
                                        Save
                                    </flux:button>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            <!-- Tracking Modal -->
            @if($showTrackingModal)
                <flux:modal wire:model="showTrackingModal">
                    <div class="p-6">
                        <flux:heading size="lg" class="mb-4">Tracking Timeline</flux:heading>
                        <flux:text class="text-sm text-gray-500 mb-4">Tracking: {{ $order->tracking_id }}</flux:text>

                        @if(empty($trackingInfo))
                            <flux:text class="text-gray-500">No tracking events available yet.</flux:text>
                        @else
                            <div class="space-y-4">
                                @foreach($trackingInfo as $event)
                                    <div class="flex gap-3">
                                        <div class="flex flex-col items-center">
                                            <div class="w-3 h-3 bg-blue-600 rounded-full"></div>
                                            @if(!$loop->last)
                                                <div class="w-px h-full bg-gray-200 mt-1"></div>
                                            @endif
                                        </div>
                                        <div class="pb-4">
                                            <flux:text class="font-medium text-sm">{{ $event['description'] ?: $event['status'] }}</flux:text>
                                            <flux:text class="text-xs text-gray-500">{{ $event['datetime'] }}</flux:text>
                                            @if(!empty($event['location']))
                                                <flux:text class="text-xs text-gray-400">{{ $event['location'] }}</flux:text>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <div class="mt-6 flex justify-end">
                            <flux:button variant="outline" wire:click="closeTrackingModal">Close</flux:button>
                        </div>
                    </div>
                </flux:modal>
            @endif

            <!-- Order Items -->
            <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6">
                <div class="flex items-center justify-between mb-4">
                    <flux:heading size="lg">Order Items</flux:heading>
                    @if($order->platform_id && $order->platform_account_id)
                        <div class="flex items-center gap-2">
                            @if($order->items->contains(fn ($item) => !$item->product_id && !$item->package_id))
                                <flux:button size="sm" variant="outline" wire:click="linkOrderItems" wire:loading.attr="disabled">
                                    <div class="flex items-center justify-center">
                                        <flux:icon name="link" class="w-4 h-4 mr-1" />
                                        <span wire:loading.remove wire:target="linkOrderItems">Link to Products</span>
                                        <span wire:loading wire:target="linkOrderItems">Linking...</span>
                                    </div>
                                </flux:button>
                            @endif
                            @if($order->items->contains(fn ($item) => ($item->product_id || $item->package_id) && $item->warehouse_id) && in_array($order->status, ['shipped', 'delivered']))
                                <flux:button size="sm" variant="outline" wire:click="deductOrderStock" wire:loading.attr="disabled" wire:confirm="Deduct stock for all linked items in this order?">
                                    <div class="flex items-center justify-center">
                                        <flux:icon name="minus-circle" class="w-4 h-4 mr-1" />
                                        <span wire:loading.remove wire:target="deductOrderStock">Deduct Stock</span>
                                        <span wire:loading wire:target="deductOrderStock">Deducting...</span>
                                    </div>
                                </flux:button>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="space-y-4">
                    @foreach($order->items as $item)
                        <div class="flex items-center justify-between border-b pb-4">
                            <div class="flex items-center space-x-4">
                                <div class="w-16 h-16 bg-zinc-100 dark:bg-zinc-700 rounded-lg flex items-center justify-center">
                                    @if($item->package_id)
                                        <flux:icon name="archive-box" class="w-8 h-8 text-purple-400" />
                                    @elseif($item->product_id)
                                        <flux:icon name="cube" class="w-8 h-8 text-blue-400" />
                                    @else
                                        <flux:icon name="cube" class="w-8 h-8 text-zinc-400" />
                                    @endif
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        @if($item->package_id && $item->package)
                                            <a href="{{ route('packages.show', $item->package_id) }}" wire:navigate class="font-medium text-gray-900 dark:text-zinc-100 hover:text-purple-600 dark:hover:text-purple-400 transition-colors">
                                                {{ $item->package->name }}
                                            </a>
                                            <flux:badge size="sm" color="purple">Package</flux:badge>
                                        @elseif($item->product_id && $item->product)
                                            <a href="{{ route('products.show', $item->product_id) }}" wire:navigate class="font-medium text-gray-900 dark:text-zinc-100 hover:text-blue-600 dark:hover:text-blue-400 transition-colors">
                                                {{ $item->product->name }}
                                            </a>
                                            <flux:badge size="sm" color="blue">Product</flux:badge>
                                        @else
                                            <flux:heading class="font-medium">{{ $item->product_name ?? 'Unknown Product' }}</flux:heading>
                                            <flux:badge size="sm" color="zinc">Unmapped</flux:badge>
                                        @endif
                                    </div>
                                    @if($item->platform_product_name && ($item->product_id || $item->package_id))
                                        <flux:text class="text-xs text-zinc-400">
                                            Platform: {{ $item->platform_product_name }}
                                        </flux:text>
                                    @endif
                                    @if($item->product && $item->product->sku)
                                        <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                                            SKU: {{ $item->product->sku }}
                                        </flux:text>
                                    @elseif($item->platform_sku)
                                        <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                                            Platform SKU: {{ $item->platform_sku }}
                                        </flux:text>
                                    @endif
                                    <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                                        Warehouse: {{ $item->warehouse?->name ?? 'Not assigned' }}
                                    </flux:text>
                                </div>
                            </div>
                            <div class="text-right">
                                <flux:text class="font-medium">{{ $item->quantity_ordered ?? $item->quantity }} × MYR {{ number_format($item->unit_price, 2) }}</flux:text>
                                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">MYR {{ number_format($item->total_price, 2) }}</flux:text>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Customer Information -->
            <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6">
                <flux:heading size="lg" class="mb-4">Customer Information</flux:heading>

                <div class="grid md:grid-cols-2 gap-6">
                    <div>
                        <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">Customer</flux:text>
                        <flux:text class="font-medium">{{ $order->getCustomerName() }}</flux:text>
                        <flux:text class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">{{ $order->getCustomerEmail() }}</flux:text>

                        <!-- Show phone from multiple sources -->
                        @php
                            $billingAddr = $order->billingAddress();
                            $phone = $order->customer_phone ?? ($billingAddr ? $billingAddr->phone : null);
                        @endphp
                        @if($phone)
                            <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">{{ $phone }}</flux:text>
                        @endif
                    </div>

                    @if($order->user)
                        <div>
                            <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">Account</flux:text>
                            <flux:text class="font-medium">{{ $order->user->name }}</flux:text>
                            <flux:text class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">{{ $order->user->email }}</flux:text>
                        </div>
                    @endif
                </div>
            </div>

            <!-- POS Sale Info -->
            @if($order->source === 'pos' && ($order->metadata['salesperson_name'] ?? null))
                <div class="bg-orange-50 dark:bg-orange-900/20 rounded-lg border border-orange-200 dark:border-orange-800 p-6">
                    <flux:heading size="lg" class="mb-4">POS Sale Information</flux:heading>
                    <div class="grid md:grid-cols-2 gap-6">
                        <div>
                            <flux:text class="text-sm text-orange-700 dark:text-orange-400">Salesperson</flux:text>
                            <flux:text class="font-medium">{{ $order->metadata['salesperson_name'] }}</flux:text>
                        </div>
                        <div>
                            <flux:text class="text-sm text-orange-700 dark:text-orange-400">Source</flux:text>
                            <flux:badge size="sm" color="orange">POS - Point of Sale</flux:badge>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Addresses -->
            <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6">
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
                        <div class="text-sm text-zinc-600 dark:text-zinc-400 space-y-1">
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
                                <div class="text-zinc-500 dark:text-zinc-400 italic">No billing address provided</div>
                            @endif
                        </div>
                    </div>

                    <!-- Shipping Address -->
                    <div>
                        <flux:text class="font-medium mb-2">Shipping Address</flux:text>
                        <div class="text-sm text-zinc-600 dark:text-zinc-400 space-y-1">
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
                                @if(!empty($platformShipping['full_address']))
                                    {{-- POS or other flat address --}}
                                    <div>{{ $platformShipping['full_address'] }}</div>
                                @elseif(!empty($platformShipping['address_line_1']))
                                    {{-- Funnel/website structured address --}}
                                    @if(!empty($platformShipping['first_name']) || !empty($platformShipping['last_name']))
                                        <div>{{ trim(($platformShipping['first_name'] ?? '') . ' ' . ($platformShipping['last_name'] ?? '')) }}</div>
                                    @endif
                                    @if(!empty($platformShipping['company']))
                                        <div>{{ $platformShipping['company'] }}</div>
                                    @endif
                                    <div>{{ $platformShipping['address_line_1'] }}</div>
                                    @if(!empty($platformShipping['address_line_2']))
                                        <div>{{ $platformShipping['address_line_2'] }}</div>
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
                                    @if(!empty($platformShipping['phone']))
                                        <div>{{ $platformShipping['phone'] }}</div>
                                    @endif
                                @else
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
                                @endif
                            @else
                                <div class="text-zinc-500 dark:text-zinc-400 italic">No shipping address provided</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Order Notes -->
            @if($order->notes->count() > 0)
                <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6">
                    <flux:heading size="lg" class="mb-4">Order Notes</flux:heading>

                    <div class="space-y-4">
                        @foreach($order->notes()->orderBy('created_at', 'desc')->get() as $note)
                            <div class="border-l-4 @if($note->type === 'system') border-blue-500 @elseif($note->type === 'customer') border-green-500 @else border-amber-500 @endif pl-4 py-2">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <flux:text class="text-zinc-900 dark:text-zinc-100">{{ $note->message }}</flux:text>
                                        <div class="flex items-center gap-3 mt-2">
                                            <flux:badge
                                                :variant="match($note->type) { 'system' => 'subtle', 'customer' => 'positive', default => 'warning' }"
                                                size="sm"
                                            >
                                                {{ ucfirst($note->type) }}
                                            </flux:badge>

                                            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                                                {{ $note->created_at->format('M j, Y \a\t g:i A') }}
                                            </flux:text>

                                            @if($note->user)
                                                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
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
            <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-6 sticky top-6">
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
                    <div class="border-t-2 border-gray-200 dark:border-zinc-700 pt-3 mt-2">
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
                    <!-- Receipt/Invoice Actions -->
                    <flux:button variant="outline" class="w-full" :href="route('admin.orders.receipt', $order)" wire:navigate>
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
                </div>

                <!-- Timestamps -->
                <div class="mt-6 pt-6 border-t border-gray-200 dark:border-zinc-700 text-sm text-zinc-600 dark:text-zinc-400 dark:text-zinc-400 space-y-2">
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