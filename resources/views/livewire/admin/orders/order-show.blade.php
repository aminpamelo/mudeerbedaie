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
            'salesSource',
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

    // Class Assignment
    public bool $showAssignClassModal = false;
    public string $classSearch = '';
    public array $selectedClassIds = [];

    public function openAssignClassModal(): void
    {
        $this->showAssignClassModal = true;
        $this->classSearch = '';
        $this->selectedClassIds = [];
    }

    public function getAvailableClassesProperty()
    {
        $alreadyAssignedClassIds = $this->order->classAssignmentApprovals()
            ->whereIn('status', ['pending', 'approved'])
            ->pluck('class_id')
            ->toArray();

        $query = \App\Models\ClassModel::query()
            ->where('status', 'active')
            ->whereNotIn('id', $alreadyAssignedClassIds)
            ->with('course');

        if ($this->classSearch) {
            $query->where(function ($q) {
                $q->where('title', 'like', "%{$this->classSearch}%")
                  ->orWhereHas('course', fn ($cq) => $cq->where('name', 'like', "%{$this->classSearch}%"));
            });
        }

        return $query->get()->groupBy(fn ($class) => $class->course?->name ?? 'No Course');
    }

    public function getSuggestedClassIdsProperty(): array
    {
        $productIds = $this->order->items->pluck('product_id')->filter()->toArray();

        if (empty($productIds)) {
            return [];
        }

        return \App\Models\ClassModel::whereIn('shipment_product_id', $productIds)
            ->where('status', 'active')
            ->pluck('id')
            ->toArray();
    }

    public function resolveStudent(): ?\App\Models\Student
    {
        // 1. Direct student link
        if ($this->order->student_id) {
            return $this->order->student;
        }

        // 2. Find student via customer user
        if ($this->order->customer_id) {
            $student = \App\Models\Student::where('user_id', $this->order->customer_id)->first();
            if ($student) {
                return $student;
            }
        }

        return null;
    }

    public function assignToClasses(): void
    {
        if (empty($this->selectedClassIds)) {
            return;
        }

        $student = $this->resolveStudent();

        if (! $student) {
            session()->flash('error', 'No student could be found for this order. Please link a student or customer first.');
            return;
        }

        $count = count($this->selectedClassIds);

        foreach ($this->selectedClassIds as $classId) {
            \App\Models\ClassAssignmentApproval::firstOrCreate(
                [
                    'class_id' => $classId,
                    'student_id' => $student->id,
                    'product_order_id' => $this->order->id,
                ],
                [
                    'status' => 'pending',
                    'assigned_by' => auth()->id(),
                ]
            );
        }

        $this->showAssignClassModal = false;
        $this->selectedClassIds = [];
        session()->flash('success', 'Order assigned to ' . $count . ' class(es) for approval.');
    }

    public function toggleClassSelection(int $classId): void
    {
        if (in_array($classId, $this->selectedClassIds)) {
            $this->selectedClassIds = array_values(array_diff($this->selectedClassIds, [$classId]));
        } else {
            $this->selectedClassIds[] = $classId;
        }
    }

    public bool $showRemoveAssignmentModal = false;
    public ?int $removingAssignmentId = null;

    public function confirmRemoveAssignment(int $approvalId): void
    {
        $this->removingAssignmentId = $approvalId;
        $this->showRemoveAssignmentModal = true;
    }

    public function removeAssignment(): void
    {
        if (! $this->removingAssignmentId) {
            return;
        }

        $approval = \App\Models\ClassAssignmentApproval::where('id', $this->removingAssignmentId)
            ->where('product_order_id', $this->order->id)
            ->first();

        if ($approval) {
            $approval->delete();
            session()->flash('success', 'Assignment removed.');
        }

        $this->showRemoveAssignmentModal = false;
        $this->removingAssignmentId = null;
    }

    public function cancelRemoveAssignment(): void
    {
        $this->showRemoveAssignmentModal = false;
        $this->removingAssignmentId = null;
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
    <div class="mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="min-w-0">
                <div class="flex items-center gap-2 mb-1">
                    <flux:button variant="ghost" size="sm" :href="route('admin.orders.index')" wire:navigate>
                        <flux:icon name="arrow-left" class="w-4 h-4" />
                    </flux:button>
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">Orders</flux:text>
                    <flux:icon name="chevron-right" class="w-3 h-3 text-zinc-400" />
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">#{{ $order->platform_order_number ?: $order->order_number }}</flux:text>
                </div>
                <div class="flex items-center gap-3 flex-wrap">
                    <flux:heading size="xl">Order #{{ $order->platform_order_number ?: $order->order_number }}</flux:heading>
                    <flux:badge
                        size="sm"
                        :color="match($order->status) {
                            'pending' => 'orange',
                            'processing' => 'blue',
                            'shipped' => 'purple',
                            'delivered' => 'green',
                            'cancelled' => 'red',
                            'refunded' => 'red',
                            'returned' => 'red',
                            default => 'zinc'
                        }"
                    >
                        {{ ucfirst($order->status) }}
                    </flux:badge>
                    <flux:badge
                        size="sm"
                        :color="match($paymentStatus) {
                            'pending' => 'orange',
                            'completed' => 'green',
                            'failed' => 'red',
                            'refunded' => 'purple',
                            default => 'zinc'
                        }"
                    >
                        Payment: {{ ucfirst($paymentStatus) }}
                    </flux:badge>
                    @if($order->isPlatformOrder())
                        <flux:badge size="sm" color="purple">
                            {{ $order->platform?->display_name ?? 'Platform' }}
                        </flux:badge>
                    @endif
                    @if($order->source === 'platform_import')
                        <flux:badge size="sm" color="blue">Imported</flux:badge>
                    @endif
                </div>
                <flux:text class="mt-1 text-sm">
                    {{ $order->order_date ? $order->order_date->format('M j, Y \a\t g:i A') : $order->created_at->format('M j, Y \a\t g:i A') }}
                    @if($order->user)
                        · {{ $order->user->name }}
                    @endif
                    @if($order->platform_order_id && $order->platform_order_id !== $order->platform_order_number)
                        · Platform ID: {{ $order->platform_order_id }}
                    @endif
                </flux:text>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <flux:button variant="outline" size="sm" :href="route('admin.orders.receipt', $order)" wire:navigate>
                    <div class="flex items-center justify-center">
                        <flux:icon name="document-text" class="w-4 h-4 mr-1" />
                        Receipt
                    </div>
                </flux:button>
                <flux:button variant="outline" size="sm" :href="route('admin.orders.edit', $order)" wire:navigate>
                    <div class="flex items-center justify-center">
                        <flux:icon name="pencil" class="w-4 h-4 mr-1" />
                        Edit
                    </div>
                </flux:button>
            </div>
        </div>
    </div>

    <!-- Order Progress Stepper -->
    @php
        $steps = ['pending', 'processing', 'shipped', 'delivered'];
        $cancelledStatuses = ['cancelled', 'refunded', 'returned'];
        $isCancelled = in_array($order->status, $cancelledStatuses);
        $currentStepIndex = array_search($order->status, $steps);
        if ($currentStepIndex === false) {
            $currentStepIndex = -1;
        }
        $stepLabels = [
            'pending' => 'Pending',
            'processing' => 'Processing',
            'shipped' => 'Shipped',
            'delivered' => 'Delivered',
        ];
        $stepIcons = [
            'pending' => 'clock',
            'processing' => 'cog-6-tooth',
            'shipped' => 'truck',
            'delivered' => 'check-circle',
        ];
    @endphp
    @if(!$isCancelled)
        <div class="mb-6 bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-5">
            <div class="flex items-center justify-between">
                @foreach($steps as $index => $step)
                    <div class="flex items-center {{ $index < count($steps) - 1 ? 'flex-1' : '' }}">
                        <div class="flex flex-col items-center">
                            <div class="flex items-center justify-center w-10 h-10 rounded-full transition-all
                                @if($index < $currentStepIndex)
                                    bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400
                                @elseif($index === $currentStepIndex)
                                    bg-blue-600 dark:bg-blue-500 text-white ring-4 ring-blue-100 dark:ring-blue-900/50
                                @else
                                    bg-zinc-100 dark:bg-zinc-700 text-zinc-400 dark:text-zinc-500
                                @endif
                            ">
                                @if($index < $currentStepIndex)
                                    <flux:icon name="check" class="w-5 h-5" />
                                @else
                                    <flux:icon :name="$stepIcons[$step]" class="w-5 h-5" />
                                @endif
                            </div>
                            <span class="mt-2 text-xs font-medium
                                @if($index < $currentStepIndex)
                                    text-green-600 dark:text-green-400
                                @elseif($index === $currentStepIndex)
                                    text-blue-600 dark:text-blue-400
                                @else
                                    text-zinc-400 dark:text-zinc-500
                                @endif
                            ">{{ $stepLabels[$step] }}</span>
                        </div>
                        @if($index < count($steps) - 1)
                            <div class="flex-1 mx-3 mt-[-1.25rem]">
                                <div class="h-0.5 rounded-full transition-all
                                    @if($index < $currentStepIndex)
                                        bg-green-400 dark:bg-green-600
                                    @else
                                        bg-zinc-200 dark:bg-zinc-700
                                    @endif
                                "></div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @else
        <div class="mb-6 bg-red-50 dark:bg-red-900/20 rounded-xl border border-red-200 dark:border-red-800 p-4">
            <div class="flex items-center gap-3">
                <div class="flex items-center justify-center w-10 h-10 rounded-full bg-red-100 dark:bg-red-900/40 text-red-600 dark:text-red-400">
                    <flux:icon name="x-circle" class="w-5 h-5" />
                </div>
                <div>
                    <flux:text class="font-semibold text-red-900 dark:text-red-200">Order {{ ucfirst($order->status) }}</flux:text>
                    <flux:text class="text-sm text-red-700 dark:text-red-400">This order has been {{ $order->status }}.</flux:text>
                </div>
            </div>
        </div>
    @endif

    <div class="grid lg:grid-cols-3 gap-6">
        <!-- Left Column - Order Details -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Order & Payment Status Controls -->
            <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                <div class="grid md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-zinc-200 dark:divide-zinc-700">
                    <!-- Order Status -->
                    <div class="p-5">
                        <div class="flex items-center gap-2 mb-3">
                            <div class="w-2 h-2 rounded-full
                                @if(in_array($order->status, ['delivered']))
                                    bg-green-500
                                @elseif(in_array($order->status, ['processing', 'shipped']))
                                    bg-blue-500
                                @elseif(in_array($order->status, ['cancelled', 'refunded', 'returned']))
                                    bg-red-500
                                @else
                                    bg-orange-500
                                @endif
                            "></div>
                            <flux:text class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Order Status</flux:text>
                        </div>
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
                    </div>

                    <!-- Payment Status -->
                    <div class="p-5">
                        <div class="flex items-center gap-2 mb-3">
                            <div class="w-2 h-2 rounded-full
                                @if($paymentStatus === 'completed')
                                    bg-green-500
                                @elseif($paymentStatus === 'failed')
                                    bg-red-500
                                @elseif($paymentStatus === 'refunded')
                                    bg-purple-500
                                @else
                                    bg-orange-500
                                @endif
                            "></div>
                            <flux:text class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Payment Status</flux:text>
                        </div>
                        <flux:select wire:model="paymentStatus" wire:change="updatePaymentStatus($event.target.value)">
                            <option value="pending">Pending</option>
                            <option value="completed">Completed</option>
                            <option value="failed">Failed</option>
                            <option value="refunded">Refunded</option>
                        </flux:select>
                        <div class="mt-3 flex items-center gap-4 text-sm">
                            <div>
                                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">Method</flux:text>
                                <flux:text class="font-medium capitalize text-sm">
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
                                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">Currency</flux:text>
                                <flux:text class="font-medium text-sm">{{ $order->currency }}</flux:text>
                            </div>
                            @if($order->salesSource)
                                <div>
                                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">Source</flux:text>
                                    <div class="flex items-center gap-1.5 mt-0.5">
                                        <span class="w-2.5 h-2.5 rounded-full shrink-0" style="background-color: {{ $order->salesSource->color }}"></span>
                                        <flux:text class="font-medium text-sm">{{ $order->salesSource->name }}</flux:text>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Receipt Attachment -->
            @if($order->receipt_attachment)
                <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
                    <div class="flex items-center gap-2 mb-4">
                        <flux:icon name="paper-clip" class="w-5 h-5 text-amber-500" />
                        <flux:heading size="lg">Receipt Attachment</flux:heading>
                    </div>
                    <div>
                        @if(str_ends_with($order->receipt_attachment, '.pdf'))
                            <div class="flex items-center gap-3">
                                <flux:icon name="document" class="w-5 h-5 text-red-600" />
                                <flux:text class="text-sm text-zinc-700 dark:text-zinc-300">PDF Receipt</flux:text>
                                <a href="{{ $order->receipt_attachment_url }}" target="_blank">
                                    <flux:button variant="outline" size="sm">
                                        <div class="flex items-center justify-center">
                                            <flux:icon name="arrow-top-right-on-square" class="w-4 h-4 mr-1" />
                                            View
                                        </div>
                                    </flux:button>
                                </a>
                            </div>
                        @else
                            <a href="{{ $order->receipt_attachment_url }}" target="_blank" class="block">
                                <img
                                    src="{{ $order->receipt_attachment_url }}"
                                    alt="Receipt"
                                    class="max-w-xs rounded-lg border border-zinc-200 dark:border-zinc-700 hover:opacity-90 transition-opacity"
                                />
                            </a>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Platform & Shipping Information (if platform order) -->
            @if($order->isPlatformOrder())
                <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
                    <div class="flex items-center gap-2 mb-4">
                        <flux:icon name="globe-alt" class="w-5 h-5 text-purple-500" />
                        <flux:heading size="lg">Platform & Shipping Details</flux:heading>
                    </div>

                    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        @if($order->platform)
                            <div class="p-3 rounded-lg bg-zinc-50 dark:bg-zinc-700/50">
                                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">Platform</flux:text>
                                <flux:text class="font-medium text-sm">{{ $order->platform->display_name }}</flux:text>
                                @if($order->platformAccount)
                                    <flux:text class="text-xs text-zinc-400 mt-0.5">{{ $order->platformAccount->name }}</flux:text>
                                @endif
                            </div>
                        @endif

                        @if($order->tracking_id)
                            <div class="p-3 rounded-lg bg-zinc-50 dark:bg-zinc-700/50">
                                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">Tracking ID</flux:text>
                                <flux:text class="font-medium text-sm font-mono">{{ $order->tracking_id }}</flux:text>
                            </div>
                        @endif

                        @if($order->package_id)
                            <div class="p-3 rounded-lg bg-zinc-50 dark:bg-zinc-700/50">
                                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">Package ID</flux:text>
                                <flux:text class="font-medium text-sm font-mono">{{ $order->package_id }}</flux:text>
                            </div>
                        @endif

                        @if($order->shipping_provider)
                            <div class="p-3 rounded-lg bg-zinc-50 dark:bg-zinc-700/50">
                                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">Shipping Provider</flux:text>
                                <flux:text class="font-medium text-sm">{{ $order->shipping_provider }}</flux:text>
                            </div>
                        @endif

                        @if($order->fulfillment_type)
                            <div class="p-3 rounded-lg bg-zinc-50 dark:bg-zinc-700/50">
                                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">Fulfillment</flux:text>
                                <flux:text class="font-medium text-sm capitalize">{{ str_replace('_', ' ', $order->fulfillment_type) }}</flux:text>
                            </div>
                        @endif

                        @if($order->delivery_option)
                            <div class="p-3 rounded-lg bg-zinc-50 dark:bg-zinc-700/50">
                                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">Delivery Option</flux:text>
                                <flux:text class="font-medium text-sm">{{ $order->delivery_option }}</flux:text>
                            </div>
                        @endif

                        @if($order->weight_kg)
                            <div class="p-3 rounded-lg bg-zinc-50 dark:bg-zinc-700/50">
                                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">Weight</flux:text>
                                <flux:text class="font-medium text-sm">{{ $order->formatted_weight }}</flux:text>
                            </div>
                        @endif

                        @if($order->buyer_username)
                            <div class="p-3 rounded-lg bg-zinc-50 dark:bg-zinc-700/50">
                                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">Buyer Username</flux:text>
                                <flux:text class="font-medium text-sm">{{ $order->buyer_username }}</flux:text>
                            </div>
                        @endif
                    </div>

                    <!-- Buyer Message -->
                    @if($order->buyer_message)
                        <div class="mt-4 p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
                            <div class="flex items-start gap-3">
                                <flux:icon name="chat-bubble-left" class="w-5 h-5 text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5" />
                                <div>
                                    <flux:text class="text-sm font-medium text-amber-900 dark:text-amber-200">Buyer Message</flux:text>
                                    <flux:text class="text-sm text-amber-800 dark:text-amber-300 mt-1">{{ $order->buyer_message }}</flux:text>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Seller Note -->
                    @if($order->seller_note)
                        <div class="mt-3 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                            <div class="flex items-start gap-3">
                                <flux:icon name="document-text" class="w-5 h-5 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" />
                                <div>
                                    <flux:text class="text-sm font-medium text-blue-900 dark:text-blue-200">Seller Note</flux:text>
                                    <flux:text class="text-sm text-blue-800 dark:text-blue-300 mt-1">{{ $order->seller_note }}</flux:text>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Order Timeline for Platform Orders -->
                    @if($order->paid_time || $order->rts_time || $order->shipped_at || $order->delivered_at)
                        <div class="mt-5 pt-5 border-t border-zinc-200 dark:border-zinc-700">
                            <flux:text class="text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-3">Timeline</flux:text>
                            <div class="space-y-3">
                                @if($order->paid_time)
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center shrink-0">
                                            <flux:icon name="banknotes" class="w-4 h-4 text-green-600 dark:text-green-400" />
                                        </div>
                                        <div class="flex-1 flex items-center justify-between">
                                            <flux:text class="text-sm font-medium">Paid</flux:text>
                                            <flux:text class="text-xs text-zinc-500">{{ $order->paid_time->format('M j, Y g:i A') }}</flux:text>
                                        </div>
                                    </div>
                                @endif
                                @if($order->rts_time)
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center shrink-0">
                                            <flux:icon name="archive-box-arrow-down" class="w-4 h-4 text-blue-600 dark:text-blue-400" />
                                        </div>
                                        <div class="flex-1 flex items-center justify-between">
                                            <flux:text class="text-sm font-medium">Ready to Ship</flux:text>
                                            <flux:text class="text-xs text-zinc-500">{{ $order->rts_time->format('M j, Y g:i A') }}</flux:text>
                                        </div>
                                    </div>
                                @endif
                                @if($order->shipped_at)
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center shrink-0">
                                            <flux:icon name="truck" class="w-4 h-4 text-purple-600 dark:text-purple-400" />
                                        </div>
                                        <div class="flex-1 flex items-center justify-between">
                                            <flux:text class="text-sm font-medium">Shipped</flux:text>
                                            <flux:text class="text-xs text-zinc-500">{{ $order->shipped_at->format('M j, Y g:i A') }}</flux:text>
                                        </div>
                                    </div>
                                @endif
                                @if($order->delivered_at)
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center shrink-0">
                                            <flux:icon name="check-circle" class="w-4 h-4 text-green-600 dark:text-green-400" />
                                        </div>
                                        <div class="flex-1 flex items-center justify-between">
                                            <flux:text class="text-sm font-medium">Delivered</flux:text>
                                            <flux:text class="text-xs text-zinc-500">{{ $order->delivered_at->format('M j, Y g:i A') }}</flux:text>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            <!-- Shipping Management (non-platform orders) -->
            @if(!$order->isPlatformOrder())
                <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
                    <div class="flex items-center gap-2 mb-4">
                        <flux:icon name="truck" class="w-5 h-5 text-blue-500" />
                        <flux:heading size="lg">Shipping</flux:heading>
                    </div>

                    @if($order->tracking_id)
                        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3 mb-4">
                            <div class="p-3 rounded-lg bg-zinc-50 dark:bg-zinc-700/50">
                                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">Tracking Number</flux:text>
                                <flux:text class="font-medium text-sm font-mono">{{ $order->tracking_id }}</flux:text>
                            </div>
                            @if($order->shipping_provider)
                                <div class="p-3 rounded-lg bg-zinc-50 dark:bg-zinc-700/50">
                                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">Provider</flux:text>
                                    <flux:text class="font-medium text-sm capitalize">{{ $order->shipping_provider === 'jnt' ? 'J&T Express' : $order->shipping_provider }}</flux:text>
                                </div>
                            @endif
                            @if($order->delivery_option)
                                <div class="p-3 rounded-lg bg-zinc-50 dark:bg-zinc-700/50">
                                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">Service</flux:text>
                                    <flux:text class="font-medium text-sm">{{ $order->delivery_option }}</flux:text>
                                </div>
                            @endif
                            @if($order->weight_kg)
                                <div class="p-3 rounded-lg bg-zinc-50 dark:bg-zinc-700/50">
                                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">Weight</flux:text>
                                    <flux:text class="font-medium text-sm">{{ number_format($order->weight_kg, 2) }} kg</flux:text>
                                </div>
                            @endif
                            @if($order->shipping_cost)
                                <div class="p-3 rounded-lg bg-zinc-50 dark:bg-zinc-700/50">
                                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">Shipping Cost</flux:text>
                                    <flux:text class="font-medium text-sm">MYR {{ number_format($order->shipping_cost, 2) }}</flux:text>
                                </div>
                            @endif
                            @if($order->shipped_at)
                                <div class="p-3 rounded-lg bg-zinc-50 dark:bg-zinc-700/50">
                                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">Shipped At</flux:text>
                                    <flux:text class="font-medium text-sm">{{ $order->shipped_at->format('M j, Y g:i A') }}</flux:text>
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
                        <div class="space-y-3">
                            @if($this->isJntEnabled() && in_array($order->status, ['confirmed', 'processing']))
                                <div class="p-4 rounded-lg border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-900/20">
                                    <div class="flex items-center justify-between gap-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-9 h-9 rounded-lg bg-blue-100 dark:bg-blue-900/40 flex items-center justify-center shrink-0">
                                                <flux:icon name="truck" class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                                            </div>
                                            <flux:text class="text-sm text-blue-800 dark:text-blue-300">Create a J&T Express shipment</flux:text>
                                        </div>
                                        <flux:button variant="primary" size="sm" wire:click="createJntShipment" wire:loading.attr="disabled">
                                            <div class="flex items-center justify-center">
                                                <span wire:loading.remove wire:target="createJntShipment">Create Shipment</span>
                                                <span wire:loading wire:target="createJntShipment">Creating...</span>
                                            </div>
                                        </flux:button>
                                    </div>
                                </div>
                            @endif

                            <div class="p-4 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-700/30">
                                <flux:text class="text-xs font-medium text-zinc-500 dark:text-zinc-400 mb-2">Manual Tracking Number</flux:text>
                                <div class="flex gap-2">
                                    <flux:input wire:model="manualTrackingId" placeholder="Enter tracking number" size="sm" />
                                    <flux:button variant="primary" size="sm" wire:click="setManualTracking">
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
                        <flux:heading size="lg" class="mb-1">Tracking Timeline</flux:heading>
                        <flux:text class="text-sm text-zinc-500 dark:text-zinc-400 mb-5">{{ $order->tracking_id }}</flux:text>

                        @if(empty($trackingInfo))
                            <div class="py-8 text-center">
                                <flux:icon name="truck" class="w-10 h-10 text-zinc-300 mx-auto mb-2" />
                                <flux:text class="text-zinc-500">No tracking events available yet.</flux:text>
                            </div>
                        @else
                            <div class="space-y-0">
                                @foreach($trackingInfo as $event)
                                    <div class="flex gap-3">
                                        <div class="flex flex-col items-center">
                                            <div class="w-3 h-3 rounded-full {{ $loop->first ? 'bg-blue-600' : 'bg-zinc-300 dark:bg-zinc-600' }}"></div>
                                            @if(!$loop->last)
                                                <div class="w-px flex-1 bg-zinc-200 dark:bg-zinc-600 my-1"></div>
                                            @endif
                                        </div>
                                        <div class="pb-4">
                                            <flux:text class="font-medium text-sm">{{ $event['description'] ?: $event['status'] }}</flux:text>
                                            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ $event['datetime'] }}</flux:text>
                                            @if(!empty($event['location']))
                                                <flux:text class="text-xs text-zinc-400">{{ $event['location'] }}</flux:text>
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
            <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                <div class="flex items-center justify-between p-5 pb-0">
                    <div class="flex items-center gap-2">
                        <flux:icon name="shopping-bag" class="w-5 h-5 text-zinc-500" />
                        <flux:heading size="lg">Items</flux:heading>
                        <flux:badge size="sm" color="zinc">{{ $order->items->count() }}</flux:badge>
                    </div>
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

                <div class="divide-y divide-zinc-100 dark:divide-zinc-700/50 mt-4">
                    @foreach($order->items as $item)
                        <div class="flex items-center gap-4 p-5 hover:bg-zinc-50 dark:hover:bg-zinc-700/30 transition-colors" wire:key="item-{{ $item->id }}">
                            <div class="w-12 h-12 rounded-lg flex items-center justify-center shrink-0
                                @if($item->package_id)
                                    bg-purple-50 dark:bg-purple-900/20
                                @elseif($item->product_id)
                                    bg-blue-50 dark:bg-blue-900/20
                                @else
                                    bg-zinc-100 dark:bg-zinc-700
                                @endif
                            ">
                                @if($item->package_id)
                                    <flux:icon name="archive-box" class="w-6 h-6 text-purple-500 dark:text-purple-400" />
                                @elseif($item->product_id)
                                    <flux:icon name="cube" class="w-6 h-6 text-blue-500 dark:text-blue-400" />
                                @else
                                    <flux:icon name="cube" class="w-6 h-6 text-zinc-400" />
                                @endif
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    @if($item->package_id && $item->package)
                                        <a href="{{ route('packages.show', $item->package_id) }}" wire:navigate class="font-medium text-sm text-zinc-900 dark:text-zinc-100 hover:text-purple-600 dark:hover:text-purple-400 transition-colors">
                                            {{ $item->package->name }}
                                        </a>
                                        <flux:badge size="sm" color="purple">Package</flux:badge>
                                    @elseif($item->product_id && $item->product)
                                        <a href="{{ route('products.show', $item->product_id) }}" wire:navigate class="font-medium text-sm text-zinc-900 dark:text-zinc-100 hover:text-blue-600 dark:hover:text-blue-400 transition-colors">
                                            {{ $item->product->name }}
                                        </a>
                                        <flux:badge size="sm" color="blue">Product</flux:badge>
                                    @else
                                        <span class="font-medium text-sm text-zinc-900 dark:text-zinc-100">{{ $item->product_name ?? 'Unknown Product' }}</span>
                                        <flux:badge size="sm" color="zinc">Unmapped</flux:badge>
                                    @endif
                                </div>
                                <div class="flex items-center gap-3 mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                    @if($item->platform_product_name && ($item->product_id || $item->package_id))
                                        <span>Platform: {{ $item->platform_product_name }}</span>
                                        <span class="text-zinc-300 dark:text-zinc-600">&middot;</span>
                                    @endif
                                    @if($item->product && $item->product->sku)
                                        <span>SKU: {{ $item->product->sku }}</span>
                                    @elseif($item->platform_sku)
                                        <span>SKU: {{ $item->platform_sku }}</span>
                                    @endif
                                    @if($item->warehouse)
                                        <span class="text-zinc-300 dark:text-zinc-600">&middot;</span>
                                        <span>{{ $item->warehouse->name }}</span>
                                    @endif
                                </div>
                            </div>
                            <div class="text-right shrink-0">
                                <flux:text class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $order->currency }} {{ number_format($item->total_price, 2) }}</flux:text>
                                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ $item->quantity_ordered ?? $item->quantity }} &times; {{ $order->currency }} {{ number_format($item->unit_price, 2) }}</flux:text>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Class Assignment -->
            <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-5">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2">
                        <flux:icon name="academic-cap" class="w-5 h-5 text-zinc-500" />
                        <flux:heading size="lg">Class Assignment</flux:heading>
                    </div>
                    <flux:button variant="primary" size="sm" wire:click="openAssignClassModal">
                        <div class="flex items-center justify-center">
                            <flux:icon name="academic-cap" class="w-4 h-4 mr-1" />
                            Assign to Class
                        </div>
                    </flux:button>
                </div>

                @php
                    $assignments = $order->classAssignmentApprovals()->with(['class.course', 'assignedByUser'])->latest()->get();
                @endphp

                @if($assignments->isNotEmpty())
                    <div class="space-y-3">
                        @foreach($assignments as $assignment)
                            <div class="flex items-center justify-between p-3 bg-zinc-50 dark:bg-zinc-700/50 rounded-lg" wire:key="assignment-{{ $assignment->id }}">
                                <div>
                                    <p class="text-sm font-medium text-zinc-900 dark:text-white">
                                        {{ $assignment->class->title }}
                                    </p>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ $assignment->class->course?->name ?? 'No Course' }}
                                        &middot; Assigned by {{ $assignment->assignedByUser->name }}
                                        &middot; {{ $assignment->created_at->diffForHumans() }}
                                    </p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <flux:badge
                                        :variant="match($assignment->status) {
                                            'pending' => 'warning',
                                            'approved' => 'success',
                                            'rejected' => 'danger',
                                            default => 'default',
                                        }"
                                        size="sm"
                                    >
                                        {{ ucfirst($assignment->status) }}
                                    </flux:badge>
                                    <button
                                        wire:click="confirmRemoveAssignment({{ $assignment->id }})"
                                        class="p-1 text-zinc-400 hover:text-red-500 dark:hover:text-red-400 transition-colors"
                                        title="Remove assignment"
                                    >
                                        <flux:icon name="x-mark" class="w-4 h-4" />
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">No class assignments yet.</p>
                @endif
            </div>

            <!-- Assign to Class Modal -->
            <flux:modal wire:model="showAssignClassModal" class="max-w-lg">
                <div class="space-y-4">
                    <flux:heading size="lg">Assign Order to Class</flux:heading>
                    <flux:text>Select classes to assign this order for approval.</flux:text>

                    @if(!$this->resolveStudent())
                        <flux:callout variant="danger">
                            No student could be found for this order. Please link a student or customer first.
                        </flux:callout>
                    @else
                        <flux:input
                            wire:model.live.debounce.300ms="classSearch"
                            placeholder="Search classes or courses..."
                            icon="magnifying-glass"
                        />

                        <div class="max-h-80 overflow-y-auto space-y-4">
                            @forelse($this->availableClasses as $courseName => $classes)
                                <div>
                                    <h4 class="text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider mb-2">
                                        {{ $courseName }}
                                    </h4>
                                    <div class="space-y-1">
                                        @foreach($classes as $class)
                                            <div
                                                class="flex items-center gap-3 p-2 rounded-lg cursor-pointer transition-colors {{ in_array($class->id, $selectedClassIds) ? 'bg-blue-50 dark:bg-blue-900/20' : 'hover:bg-zinc-50 dark:hover:bg-zinc-700/50' }}"
                                                wire:key="class-{{ $class->id }}"
                                                wire:click="toggleClassSelection({{ $class->id }})"
                                            >
                                                <div class="shrink-0 w-5 h-5 rounded border-2 flex items-center justify-center transition-colors {{ in_array($class->id, $selectedClassIds) ? 'bg-blue-600 border-blue-600' : 'border-zinc-300 dark:border-zinc-600' }}">
                                                    @if(in_array($class->id, $selectedClassIds))
                                                        <flux:icon name="check" class="w-3.5 h-3.5 text-white" />
                                                    @endif
                                                </div>
                                                <div class="flex-1">
                                                    <span class="text-sm font-medium text-zinc-900 dark:text-white">{{ $class->title }}</span>
                                                    @if($class->max_capacity)
                                                        <span class="text-xs text-zinc-500 dark:text-zinc-400 ml-2">
                                                            ({{ $class->activeStudents->count() }}/{{ $class->max_capacity }})
                                                        </span>
                                                    @endif
                                                </div>
                                                @if(in_array($class->id, $this->suggestedClassIds))
                                                    <flux:badge variant="success" size="sm">Suggested</flux:badge>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @empty
                                <p class="text-sm text-zinc-500 dark:text-zinc-400 text-center py-4">No classes available.</p>
                            @endforelse
                        </div>

                        <div class="flex justify-end gap-2 pt-4 border-t dark:border-zinc-700">
                            <flux:button variant="ghost" wire:click="$set('showAssignClassModal', false)">Cancel</flux:button>
                            <flux:button
                                variant="primary"
                                wire:click="assignToClasses"
                                :disabled="empty($selectedClassIds)"
                            >
                                Assign to {{ count($selectedClassIds) }} Class(es)
                            </flux:button>
                        </div>
                    @endif
                </div>
            </flux:modal>

            <!-- Remove Assignment Confirmation Modal -->
            <flux:modal wire:model="showRemoveAssignmentModal" class="max-w-sm">
                <div class="space-y-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center shrink-0">
                            <flux:icon name="exclamation-triangle" class="w-5 h-5 text-red-600 dark:text-red-400" />
                        </div>
                        <div>
                            <flux:heading size="lg">Remove Assignment</flux:heading>
                            <flux:text class="mt-1">Are you sure you want to remove this class assignment?</flux:text>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <flux:button variant="ghost" wire:click="cancelRemoveAssignment">Cancel</flux:button>
                        <flux:button variant="danger" wire:click="removeAssignment">Remove</flux:button>
                    </div>
                </div>
            </flux:modal>

            <!-- Customer & Address Grid -->
            <div class="grid md:grid-cols-2 gap-6">
                <!-- Customer Information -->
                <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-5">
                    <div class="flex items-center gap-2 mb-4">
                        <flux:icon name="user" class="w-5 h-5 text-zinc-500" />
                        <flux:heading size="lg">Customer</flux:heading>
                    </div>

                    <div class="flex items-start gap-3">
                        <div class="w-10 h-10 rounded-full bg-zinc-100 dark:bg-zinc-700 flex items-center justify-center shrink-0">
                            <span class="text-sm font-semibold text-zinc-600 dark:text-zinc-300">
                                {{ strtoupper(substr($order->getCustomerName(), 0, 1)) }}{{ strtoupper(substr(str_contains($order->getCustomerName(), ' ') ? substr($order->getCustomerName(), strpos($order->getCustomerName(), ' ') + 1) : '', 0, 1)) }}
                            </span>
                        </div>
                        <div class="min-w-0">
                            <flux:text class="font-medium text-zinc-900 dark:text-zinc-100">{{ $order->getCustomerName() }}</flux:text>
                            @if($order->getCustomerEmail())
                                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ $order->getCustomerEmail() }}</flux:text>
                            @endif
                            @php
                                $billingAddr = $order->billingAddress();
                                $phone = $order->customer_phone ?? ($billingAddr ? $billingAddr->phone : null);
                            @endphp
                            @if($phone)
                                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ $phone }}</flux:text>
                            @endif
                        </div>
                    </div>

                    @if($order->user && $order->user->email !== $order->getCustomerEmail())
                        <div class="mt-3 pt-3 border-t border-zinc-100 dark:border-zinc-700">
                            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400 mb-1">Linked Account</flux:text>
                            <flux:text class="text-sm font-medium">{{ $order->user->name }}</flux:text>
                            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ $order->user->email }}</flux:text>
                        </div>
                    @endif
                </div>

                <!-- Addresses -->
                <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-5">
                    <div class="flex items-center gap-2 mb-4">
                        <flux:icon name="map-pin" class="w-5 h-5 text-zinc-500" />
                        <flux:heading size="lg">Addresses</flux:heading>
                    </div>

                    @php
                        $shippingAddressModel = $order->shippingAddress();
                        $billingAddressModel = $order->billingAddress();
                        $platformShipping = $order->shipping_address;
                    @endphp

                    <div class="space-y-4">
                        <!-- Shipping Address -->
                        <div>
                            <flux:text class="text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider mb-1.5">Shipping</flux:text>
                            <div class="text-sm text-zinc-700 dark:text-zinc-300 space-y-0.5">
                                @if($shippingAddressModel)
                                    <div class="font-medium">{{ $shippingAddressModel->first_name }} {{ $shippingAddressModel->last_name }}</div>
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
                                        <div>{{ $platformShipping['full_address'] }}</div>
                                    @elseif(!empty($platformShipping['address_line_1']))
                                        @if(!empty($platformShipping['first_name']) || !empty($platformShipping['last_name']))
                                            <div class="font-medium">{{ trim(($platformShipping['first_name'] ?? '') . ' ' . ($platformShipping['last_name'] ?? '')) }}</div>
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
                                    <div class="text-zinc-400 dark:text-zinc-500 italic">No shipping address</div>
                                @endif
                            </div>
                        </div>

                        <!-- Billing Address -->
                        @if($billingAddressModel)
                            <div class="pt-3 border-t border-zinc-100 dark:border-zinc-700">
                                <flux:text class="text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider mb-1.5">Billing</flux:text>
                                <div class="text-sm text-zinc-700 dark:text-zinc-300 space-y-0.5">
                                    <div class="font-medium">{{ $billingAddressModel->first_name }} {{ $billingAddressModel->last_name }}</div>
                                    @if($billingAddressModel->company)
                                        <div>{{ $billingAddressModel->company }}</div>
                                    @endif
                                    <div>{{ $billingAddressModel->address_line_1 }}</div>
                                    @if($billingAddressModel->address_line_2)
                                        <div>{{ $billingAddressModel->address_line_2 }}</div>
                                    @endif
                                    <div>{{ $billingAddressModel->city }}, {{ $billingAddressModel->state }} {{ $billingAddressModel->postal_code }}</div>
                                    <div>{{ $billingAddressModel->country }}</div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- POS Sale Info -->
            @if($order->source === 'pos' && ($order->metadata['salesperson_name'] ?? null))
                <div class="bg-orange-50 dark:bg-orange-900/20 rounded-xl border border-orange-200 dark:border-orange-800 p-5">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-lg bg-orange-100 dark:bg-orange-900/40 flex items-center justify-center shrink-0">
                            <flux:icon name="building-storefront" class="w-5 h-5 text-orange-600 dark:text-orange-400" />
                        </div>
                        <div>
                            <flux:text class="text-sm font-medium text-orange-900 dark:text-orange-200">POS Sale</flux:text>
                            <flux:text class="text-sm text-orange-700 dark:text-orange-400">by {{ $order->metadata['salesperson_name'] }}</flux:text>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Order Notes -->
            @if($order->notes->count() > 0)
                <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
                    <div class="flex items-center gap-2 mb-4">
                        <flux:icon name="chat-bubble-bottom-center-text" class="w-5 h-5 text-zinc-500" />
                        <flux:heading size="lg">Notes</flux:heading>
                        <flux:badge size="sm" color="zinc">{{ $order->notes->count() }}</flux:badge>
                    </div>

                    <div class="space-y-3">
                        @foreach($order->notes()->orderBy('created_at', 'desc')->get() as $note)
                            <div class="flex gap-3 p-3 rounded-lg bg-zinc-50 dark:bg-zinc-700/30" wire:key="note-{{ $note->id }}">
                                <div class="w-1.5 rounded-full shrink-0
                                    @if($note->type === 'system') bg-blue-400
                                    @elseif($note->type === 'customer') bg-green-400
                                    @elseif($note->type === 'payment') bg-purple-400
                                    @elseif($note->type === 'shipping') bg-orange-400
                                    @else bg-amber-400
                                    @endif
                                "></div>
                                <div class="flex-1 min-w-0">
                                    <flux:text class="text-sm text-zinc-800 dark:text-zinc-200">{{ $note->message }}</flux:text>
                                    <div class="flex items-center gap-2 mt-1.5 flex-wrap">
                                        <flux:badge size="sm" color="zinc">{{ ucfirst($note->type) }}</flux:badge>
                                        <flux:text class="text-xs text-zinc-400 dark:text-zinc-500">
                                            {{ $note->created_at->format('M j, Y g:i A') }}
                                        </flux:text>
                                        @if($note->user)
                                            <flux:text class="text-xs text-zinc-400 dark:text-zinc-500">
                                                by {{ $note->user->name }}
                                            </flux:text>
                                        @endif
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
            <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 sticky top-6 overflow-hidden">
                <!-- Summary Header -->
                <div class="p-5 pb-4 border-b border-zinc-100 dark:border-zinc-700">
                    <flux:heading size="lg">Summary</flux:heading>
                </div>

                <!-- Order Totals -->
                <div class="p-5 space-y-2.5">
                    @php
                        $totalDiscount = 0;
                        $subtotalBeforeDiscount = $order->subtotal;

                        if($order->isPlatformOrder()) {
                            $totalDiscount = $order->sku_platform_discount + $order->sku_seller_discount +
                                           $order->shipping_fee_seller_discount + $order->shipping_fee_platform_discount +
                                           $order->payment_platform_discount;
                            if($totalDiscount > 0) {
                                $subtotalBeforeDiscount = $order->subtotal + $order->sku_platform_discount + $order->sku_seller_discount;
                            }
                        } else {
                            $totalDiscount = $order->discount_amount;
                            if($totalDiscount > 0) {
                                $subtotalBeforeDiscount = $order->subtotal + $totalDiscount;
                            }
                        }

                        $originalShipping = $order->shipping_cost;
                        $shippingDiscount = 0;
                        if($order->isPlatformOrder() && $order->original_shipping_fee) {
                            $originalShipping = $order->original_shipping_fee;
                            $shippingDiscount = $order->shipping_fee_seller_discount + $order->shipping_fee_platform_discount;
                        }
                    @endphp

                    @if($totalDiscount > 0)
                        <div class="flex justify-between text-sm">
                            <flux:text class="text-zinc-500 dark:text-zinc-400">Subtotal (before discount)</flux:text>
                            <flux:text class="text-zinc-500 dark:text-zinc-400">{{ $order->currency }} {{ number_format($subtotalBeforeDiscount, 2) }}</flux:text>
                        </div>

                        @if($order->isPlatformOrder())
                            @if($order->sku_platform_discount > 0)
                                <div class="flex justify-between text-sm pl-3">
                                    <flux:text class="text-green-600 dark:text-green-400">Platform discount</flux:text>
                                    <flux:text class="text-green-600 dark:text-green-400">-{{ $order->currency }} {{ number_format($order->sku_platform_discount, 2) }}</flux:text>
                                </div>
                            @endif
                            @if($order->sku_seller_discount > 0)
                                <div class="flex justify-between text-sm pl-3">
                                    <flux:text class="text-green-600 dark:text-green-400">Seller discount</flux:text>
                                    <flux:text class="text-green-600 dark:text-green-400">-{{ $order->currency }} {{ number_format($order->sku_seller_discount, 2) }}</flux:text>
                                </div>
                            @endif
                            @if($order->payment_platform_discount > 0)
                                <div class="flex justify-between text-sm pl-3">
                                    <flux:text class="text-green-600 dark:text-green-400">Payment discount</flux:text>
                                    <flux:text class="text-green-600 dark:text-green-400">-{{ $order->currency }} {{ number_format($order->payment_platform_discount, 2) }}</flux:text>
                                </div>
                            @endif
                        @else
                            <div class="flex justify-between text-sm pl-3">
                                <flux:text class="text-green-600 dark:text-green-400">Discount</flux:text>
                                <flux:text class="text-green-600 dark:text-green-400">-{{ $order->currency }} {{ number_format($totalDiscount, 2) }}</flux:text>
                            </div>
                        @endif
                    @endif

                    <div class="flex justify-between text-sm">
                        <flux:text class="text-zinc-600 dark:text-zinc-300">Subtotal</flux:text>
                        <flux:text class="font-medium text-zinc-900 dark:text-zinc-100">{{ $order->currency }} {{ number_format($order->subtotal, 2) }}</flux:text>
                    </div>

                    @if($order->shipping_cost > 0 || $originalShipping > 0)
                        @if($order->isPlatformOrder() && $shippingDiscount > 0)
                            <div class="flex justify-between text-sm">
                                <flux:text class="text-zinc-500 dark:text-zinc-400">Shipping (original)</flux:text>
                                <flux:text class="text-zinc-500 dark:text-zinc-400">{{ $order->currency }} {{ number_format($originalShipping, 2) }}</flux:text>
                            </div>
                            @if($order->shipping_fee_platform_discount > 0)
                                <div class="flex justify-between text-sm pl-3">
                                    <flux:text class="text-green-600 dark:text-green-400">Platform shipping discount</flux:text>
                                    <flux:text class="text-green-600 dark:text-green-400">-{{ $order->currency }} {{ number_format($order->shipping_fee_platform_discount, 2) }}</flux:text>
                                </div>
                            @endif
                            @if($order->shipping_fee_seller_discount > 0)
                                <div class="flex justify-between text-sm pl-3">
                                    <flux:text class="text-green-600 dark:text-green-400">Seller shipping discount</flux:text>
                                    <flux:text class="text-green-600 dark:text-green-400">-{{ $order->currency }} {{ number_format($order->shipping_fee_seller_discount, 2) }}</flux:text>
                                </div>
                            @endif
                        @endif
                        <div class="flex justify-between text-sm">
                            <flux:text class="text-zinc-600 dark:text-zinc-300">Shipping</flux:text>
                            <flux:text class="font-medium text-zinc-900 dark:text-zinc-100">{{ $order->currency }} {{ number_format($order->shipping_cost, 2) }}</flux:text>
                        </div>
                    @endif

                    @if($order->tax_amount > 0)
                        <div class="flex justify-between text-sm">
                            <flux:text class="text-zinc-600 dark:text-zinc-300">Tax</flux:text>
                            <flux:text class="font-medium text-zinc-900 dark:text-zinc-100">{{ $order->currency }} {{ number_format($order->tax_amount, 2) }}</flux:text>
                        </div>
                    @endif
                </div>

                <!-- Total -->
                <div class="mx-5 mb-5 p-4 rounded-lg bg-zinc-50 dark:bg-zinc-700/50">
                    <div class="flex justify-between items-baseline">
                        <flux:text class="font-semibold text-zinc-900 dark:text-zinc-100">Total</flux:text>
                        <flux:text class="text-xl font-bold text-zinc-900 dark:text-zinc-100">{{ $order->currency }} {{ number_format($order->total_amount, 2) }}</flux:text>
                    </div>
                    @if($totalDiscount > 0)
                        <div class="flex justify-end mt-1">
                            <flux:text class="text-xs text-green-600 dark:text-green-400">Saved {{ $order->currency }} {{ number_format($totalDiscount, 2) }}</flux:text>
                        </div>
                    @endif
                </div>

                <!-- Calculation Summary Box (for complex orders) -->
                @if($totalDiscount > 0 || $shippingDiscount > 0)
                    <div class="mx-5 mb-5">
                        <details class="group">
                            <summary class="flex items-center gap-2 cursor-pointer text-xs font-medium text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-300 transition-colors">
                                <flux:icon name="calculator" class="w-3.5 h-3.5" />
                                View calculation breakdown
                                <flux:icon name="chevron-down" class="w-3 h-3 ml-auto transition-transform group-open:rotate-180" />
                            </summary>
                            <div class="mt-2 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
                                <div class="space-y-1 text-xs">
                                    @if($totalDiscount > 0)
                                        <div class="flex justify-between text-blue-800 dark:text-blue-300">
                                            <span>Items before discount:</span>
                                            <span>{{ $order->currency }} {{ number_format($subtotalBeforeDiscount, 2) }}</span>
                                        </div>
                                        <div class="flex justify-between text-green-700 dark:text-green-400">
                                            <span>- Total item discounts:</span>
                                            <span>-{{ $order->currency }} {{ number_format($totalDiscount - $shippingDiscount, 2) }}</span>
                                        </div>
                                        <div class="flex justify-between text-blue-800 dark:text-blue-300">
                                            <span>= Items subtotal:</span>
                                            <span>{{ $order->currency }} {{ number_format($order->subtotal, 2) }}</span>
                                        </div>
                                    @else
                                        <div class="flex justify-between text-blue-800 dark:text-blue-300">
                                            <span>Items subtotal:</span>
                                            <span>{{ $order->currency }} {{ number_format($order->subtotal, 2) }}</span>
                                        </div>
                                    @endif

                                    @if($order->shipping_cost > 0)
                                        @if($shippingDiscount > 0)
                                            <div class="flex justify-between text-blue-800 dark:text-blue-300">
                                                <span>+ Original shipping:</span>
                                                <span>{{ $order->currency }} {{ number_format($originalShipping, 2) }}</span>
                                            </div>
                                            <div class="flex justify-between text-green-700 dark:text-green-400">
                                                <span>- Shipping discounts:</span>
                                                <span>-{{ $order->currency }} {{ number_format($shippingDiscount, 2) }}</span>
                                            </div>
                                            <div class="flex justify-between text-blue-800 dark:text-blue-300">
                                                <span>= Final shipping:</span>
                                                <span>{{ $order->currency }} {{ number_format($order->shipping_cost, 2) }}</span>
                                            </div>
                                        @else
                                            <div class="flex justify-between text-blue-800 dark:text-blue-300">
                                                <span>+ Shipping:</span>
                                                <span>{{ $order->currency }} {{ number_format($order->shipping_cost, 2) }}</span>
                                            </div>
                                        @endif
                                    @endif

                                    @if($order->tax_amount > 0)
                                        <div class="flex justify-between text-blue-800 dark:text-blue-300">
                                            <span>+ Tax:</span>
                                            <span>{{ $order->currency }} {{ number_format($order->tax_amount, 2) }}</span>
                                        </div>
                                    @endif

                                    <div class="flex justify-between font-bold text-blue-900 dark:text-blue-200 pt-2 mt-1 border-t border-blue-300 dark:border-blue-700">
                                        <span>= Final total:</span>
                                        <span>{{ $order->currency }} {{ number_format($order->total_amount, 2) }}</span>
                                    </div>
                                </div>
                            </div>
                        </details>
                    </div>
                @endif

                <!-- Quick Actions -->
                <div class="p-5 pt-0 space-y-2">
                    @if($order->status === 'pending')
                        <flux:button variant="primary" class="w-full" wire:click="updateStatus('processing')">
                            <div class="flex items-center justify-center">
                                <flux:icon name="play" class="w-4 h-4 mr-2" />
                                Start Processing
                            </div>
                        </flux:button>
                    @endif

                    @if($order->status === 'processing')
                        <flux:button variant="primary" class="w-full" wire:click="updateStatus('shipped')">
                            <div class="flex items-center justify-center">
                                <flux:icon name="truck" class="w-4 h-4 mr-2" />
                                Mark as Shipped
                            </div>
                        </flux:button>
                    @endif

                    @if($order->status === 'shipped')
                        <flux:button variant="primary" class="w-full" wire:click="updateStatus('delivered')">
                            <div class="flex items-center justify-center">
                                <flux:icon name="check-circle" class="w-4 h-4 mr-2" />
                                Mark as Delivered
                            </div>
                        </flux:button>
                    @endif
                </div>

                <!-- Timestamps -->
                <div class="p-5 pt-0 border-t border-zinc-100 dark:border-zinc-700 mt-2">
                    <div class="pt-4 space-y-2 text-xs text-zinc-500 dark:text-zinc-400">
                        <div class="flex justify-between">
                            <span>Created</span>
                            <span>{{ $order->created_at->format('M j, Y g:i A') }}</span>
                        </div>
                        @if($order->updated_at != $order->created_at)
                            <div class="flex justify-between">
                                <span>Updated</span>
                                <span>{{ $order->updated_at->format('M j, Y g:i A') }}</span>
                            </div>
                        @endif
                        @if($order->created_by)
                            <div class="flex justify-between">
                                <span>Created by</span>
                                <span>{{ $order->createdBy->name ?? 'Unknown' }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>