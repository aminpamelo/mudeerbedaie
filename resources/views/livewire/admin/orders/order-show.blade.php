<?php

use App\DTOs\Shipping\ShipmentRequest;
use App\DTOs\Shipping\ShippingRateRequest;
use App\Models\ClassAssignmentApproval;
use App\Models\ClassModel;
use App\Models\ProductOrder;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Student;
use App\Services\SettingsService;
use App\Services\Shipping\ShippingManager;
use App\Services\TikTok\OrderItemLinker;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
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

    // EasyParcel rate-shopping
    public array $easyParcelRates = [];

    public ?string $easyParcelServiceId = null;

    // Inline fix for a missing recipient phone (the #1 cause of booking rejection).
    public string $receiverPhoneInput = '';

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

    /**
     * Refresh the order and derived payment status after the accountant
     * approves or rejects a funnel COD payment (dispatched from the
     * payment-approval-card child component).
     */
    #[On('order-payment-updated')]
    public function refreshAfterPaymentUpdate(): void
    {
        $this->order = $this->order->fresh()->load([
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

        $latestPayment = $this->order->payments()->latest()->first();
        if ($latestPayment) {
            $this->paymentStatus = $latestPayment->status;
        } elseif ($this->order->paid_time) {
            $this->paymentStatus = 'completed';
        } else {
            $this->paymentStatus = 'pending';
        }

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
                Log::warning('Cannot deduct stock - no warehouse assigned', [
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
                Log::warning('Stock level is now NEGATIVE', [
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
                receiverName: trim($shippingAddress->first_name.' '.$shippingAddress->last_name) ?: $this->order->getCustomerName(),
                receiverPhone: $this->resolveReceiverPhone(),
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
        } catch (Exception $e) {
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
        } catch (Exception $e) {
            session()->flash('error', 'Failed to fetch tracking: '.$e->getMessage());
        }
    }

    public function cancelJntShipment(): void
    {
        // EasyParcel cancels by shipment number (the AWB may not exist yet);
        // other providers cancel by tracking number.
        $cancelId = $this->order->shipping_provider === 'easyparcel'
            ? data_get($this->order->metadata, 'easyparcel_shipment_number')
            : $this->order->tracking_id;

        if (! $cancelId) {
            return;
        }

        try {
            $shippingManager = app(ShippingManager::class);
            $provider = $shippingManager->getProvider($this->order->shipping_provider);
            $result = $provider->cancelShipment($cancelId);

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
        } catch (Exception $e) {
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

    private function isEasyParcelEnabled(): bool
    {
        return app(SettingsService::class)->isEasyParcelEnabled();
    }

    /**
     * When EasyParcel is partially set up (so the store clearly intends to use
     * it) but not fully enabled, return a short reason explaining why the rate
     * box is hidden. Returns null when fully enabled, or when nothing has been
     * configured at all (so we don't nag stores that don't use EasyParcel).
     */
    private function easyParcelHint(): ?string
    {
        $settings = app(SettingsService::class);

        if ($settings->isEasyParcelEnabled() || ! $settings->isEasyParcelConfigured()) {
            return null;
        }

        if (! $settings->isEasyParcelConnected()) {
            return 'Account not connected. Link your EasyParcel account in Settings → Shipping to fetch courier rates.';
        }

        return 'Switched off. Turn on "Enable EasyParcel Shipping" in Settings → Shipping to fetch courier rates.';
    }

    private function orderShippingAddress()
    {
        $row = $this->order->addresses()->where('type', 'shipping')->first()
            ?? $this->order->addresses()->where('type', 'billing')->first();

        if ($row) {
            return $row;
        }

        // Platform orders (TikTok Shop, POS, some funnel flows) keep the address
        // in the `shipping_address` JSON column with varied key names instead of
        // as address rows. Normalise it to the shape the rate/booking code reads.
        return $this->normalizeJsonShippingAddress($this->order->shipping_address ?? []);
    }

    /**
     * The best recipient phone for this order, resolved across every source:
     * the shipping/JSON address, then the order's own resolver (customer_phone
     * field, linked customer/user record, billing address). Returns '' when no
     * usable number (≥9 digits) exists anywhere.
     */
    private function resolveReceiverPhone(): string
    {
        $address = $this->orderShippingAddress();
        $candidates = [
            (string) ($address->phone ?? ''),
            $this->order->getCustomerPhone(),
        ];

        foreach ($candidates as $candidate) {
            $candidate = trim($candidate);

            if ($candidate !== '' && $candidate !== 'No phone provided'
                && strlen((string) preg_replace('/\D+/', '', $candidate)) >= 9) {
                return $candidate;
            }
        }

        return '';
    }

    /**
     * Recipient name + phone for the shipping card, flagging a missing/invalid
     * phone so the admin can fix it before EasyParcel silently rejects the booking.
     *
     * @return array{name: string, phone: string, phone_valid: bool}|null
     */
    public function getShippingContactProperty(): ?array
    {
        $address = $this->orderShippingAddress();

        if (! $address) {
            return null;
        }

        $phone = $this->resolveReceiverPhone();
        $name = trim(($address->first_name ?? '').' '.($address->last_name ?? ''));

        return [
            'name' => $name !== '' ? $name : $this->order->getCustomerName(),
            'phone' => $phone,
            'phone_valid' => $phone !== '',
        ];
    }

    /**
     * Save a recipient phone number onto the order's shipping address — writing to
     * the address row when present, else the `shipping_address` JSON column.
     */
    public function saveReceiverPhone(): void
    {
        $this->validate(
            ['receiverPhoneInput' => ['required', 'string']],
            ['receiverPhoneInput.required' => 'Please enter the recipient phone number.'],
        );

        if (strlen((string) preg_replace('/\D+/', '', $this->receiverPhoneInput)) < 9) {
            $this->addError('receiverPhoneInput', 'Enter a valid Malaysian phone number (at least 9 digits, e.g. 0123456789).');

            return;
        }

        $phone = trim($this->receiverPhoneInput);

        $row = $this->order->addresses()->where('type', 'shipping')->first()
            ?? $this->order->addresses()->where('type', 'billing')->first();

        if ($row) {
            $row->update(['phone' => $phone]);
        } else {
            $address = $this->order->shipping_address ?? [];
            $address['phone'] = $phone;
            $this->order->update(['shipping_address' => $address]);
        }

        $this->receiverPhoneInput = '';
        $this->order->refresh();
        session()->flash('success', 'Recipient phone number saved. You can book the shipment now.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function normalizeJsonShippingAddress(array $data): ?object
    {
        if (empty($data)) {
            return null;
        }

        $pick = fn (array $keys) => collect($keys)
            ->map(fn ($key) => $data[$key] ?? null)
            ->first(fn ($value) => filled($value));

        $postal = $pick(['postal_code', 'postcode', 'zipcode', 'zip']);
        $line1 = $pick(['address_line_1', 'address_line1', 'address_1', 'full_address', 'address']);

        // Nothing usable to ship with — treat as no address.
        if (blank($postal) && blank($line1)) {
            return null;
        }

        $name = (string) ($pick(['name', 'full_name', 'recipient_name']) ?? '');
        $first = (string) ($pick(['first_name']) ?? '');
        $last = (string) ($pick(['last_name']) ?? '');

        if ($name === '') {
            $name = trim($first.' '.$last);
        }

        return (object) [
            'first_name' => $name !== '' ? $name : $first,
            'last_name' => $name !== '' ? '' : $last,
            'phone' => (string) ($pick(['phone', 'phone_number', 'mobile']) ?? ''),
            'address_line_1' => (string) ($line1 ?? ''),
            'address_line_2' => (string) ($pick(['address_line_2', 'address_line2', 'address_2']) ?? ''),
            'city' => (string) ($pick(['city', 'town']) ?? ''),
            'state' => (string) ($pick(['state', 'region', 'province']) ?? ''),
            'postal_code' => (string) ($postal ?? ''),
        ];
    }

    public function getEasyParcelRates(): void
    {
        $this->easyParcelRates = [];
        $this->easyParcelServiceId = null;

        $shippingAddress = $this->orderShippingAddress();

        if (! $shippingAddress) {
            session()->flash('error', 'No shipping address found for this order.');

            return;
        }

        if (blank($shippingAddress->postal_code)) {
            session()->flash('error', 'This order has no shipping postcode. Edit the order address and add the delivery postcode/city/state before fetching rates.');

            return;
        }

        try {
            $sender = app(SettingsService::class)->getShippingSenderDefaults();

            if (blank($sender['postal_code'] ?? null)) {
                session()->flash('error', 'No sender postcode configured. Set your store/sender address in Settings → Shipping before fetching rates.');

                return;
            }

            $provider = app(ShippingManager::class)->getProvider('easyparcel');

            $rates = $provider->getRates(new ShippingRateRequest(
                originPostalCode: $sender['postal_code'] ?: '',
                originCity: $sender['city'] ?: '',
                originState: $sender['state'] ?: '',
                destinationPostalCode: $shippingAddress->postal_code ?: '',
                destinationCity: $shippingAddress->city ?: '',
                destinationState: $shippingAddress->state ?: '',
                weightKg: (float) ($this->order->weight_kg ?: 0.5),
                itemValue: (float) $this->order->total_amount,
            ));

            if (empty($rates)) {
                session()->flash('error', 'EasyParcel returned no rates for this route (sender '.$sender['postal_code'].' → receiver '.$shippingAddress->postal_code.'). No courier may serve this lane, or your EasyParcel account/connection needs checking in Settings.');

                return;
            }

            $this->easyParcelRates = array_map(fn ($rate) => [
                'service_id' => $rate->serviceCode,
                'name' => $rate->serviceName,
                'price' => $rate->cost,
                'days' => $rate->estimatedDays,
            ], $rates);

            // Pre-select the cheapest option.
            $cheapest = collect($this->easyParcelRates)->sortBy('price')->first();
            $this->easyParcelServiceId = $cheapest['service_id'] ?? null;
        } catch (Exception $e) {
            session()->flash('error', 'Failed to fetch EasyParcel rates: '.$e->getMessage());
        }
    }

    public function bookEasyParcelShipment(): void
    {
        if (empty($this->easyParcelServiceId)) {
            session()->flash('error', 'Please select a courier service first.');

            return;
        }

        $shippingAddress = $this->orderShippingAddress();

        if (! $shippingAddress) {
            session()->flash('error', 'No shipping address found for this order.');

            return;
        }

        try {
            $sender = app(SettingsService::class)->getShippingSenderDefaults();
            $provider = app(ShippingManager::class)->getProvider('easyparcel');

            $result = $provider->createShipment(new ShipmentRequest(
                orderNumber: $this->order->order_number,
                senderName: $sender['name'] ?: 'Sender',
                senderPhone: $sender['phone'] ?: '',
                senderAddress: $sender['address'] ?: '',
                senderCity: $sender['city'] ?: '',
                senderState: $sender['state'] ?: '',
                senderPostalCode: $sender['postal_code'] ?: '',
                receiverName: trim($shippingAddress->first_name.' '.$shippingAddress->last_name) ?: $this->order->getCustomerName(),
                receiverPhone: $this->resolveReceiverPhone(),
                receiverAddress: trim($shippingAddress->address_line_1.' '.$shippingAddress->address_line_2),
                receiverCity: $shippingAddress->city ?: '',
                receiverState: $shippingAddress->state ?: '',
                receiverPostalCode: $shippingAddress->postal_code ?: '',
                weightKg: (float) ($this->order->weight_kg ?: 0.5),
                itemDescription: 'Order '.$this->order->order_number,
                itemValue: (float) $this->order->total_amount,
                itemQuantity: (int) $this->order->items->sum('quantity_ordered'),
                serviceCode: $this->easyParcelServiceId,
            ));

            if ($result->success) {
                $metadata = $this->order->metadata ?? [];
                $metadata['shipping_label_url'] = $result->labelUrl;
                $metadata['shipping_tracking_url'] = $result->trackingUrl;
                $metadata['easyparcel_order_no'] = $result->providerOrderId;
                $metadata['easyparcel_shipment_number'] = $result->shipmentNumber;
                $metadata['easyparcel_awb_pending'] = $result->awbPending;

                $this->order->update([
                    'tracking_id' => $result->trackingNumber,
                    'shipping_provider' => 'easyparcel',
                    'status' => 'shipped',
                    'shipped_at' => now(),
                    'metadata' => $metadata,
                ]);
                $this->orderStatus = 'shipped';
                $this->easyParcelRates = [];
                $this->easyParcelServiceId = null;

                $ref = $result->shipmentNumber ?: $result->providerOrderId;
                $this->order->addSystemNote('EasyParcel shipment booked. '.($result->awbPending ? "Ref: {$ref} (AWB pending)" : "AWB: {$result->trackingNumber}"));
                session()->flash('success', $result->awbPending
                    ? "Shipment booked ({$ref}). The AWB & label are generating — click \"Refresh AWB\" in a moment."
                    : "Shipment booked! AWB: {$result->trackingNumber}");
            } else {
                session()->flash('error', "Failed to book shipment: {$result->message}");
            }
        } catch (Exception $e) {
            session()->flash('error', 'EasyParcel booking failed: '.$e->getMessage());
        }

        $this->order->refresh();
    }

    public function refreshEasyParcelAwb(): void
    {
        $shipmentNumber = data_get($this->order->metadata, 'easyparcel_shipment_number');

        if (! $shipmentNumber) {
            session()->flash('error', 'No EasyParcel shipment number on this order.');

            return;
        }

        try {
            $provider = app(ShippingManager::class)->getProvider('easyparcel');
            $details = $provider->getShipmentDetails($shipmentNumber);

            if (! $details) {
                session()->flash('error', 'Could not fetch shipment details from EasyParcel.');

                return;
            }

            $metadata = $this->order->metadata ?? [];
            $metadata['shipping_label_url'] = $details['awb_url'] ?: ($metadata['shipping_label_url'] ?? null);
            $metadata['shipping_tracking_url'] = $details['tracking_url'] ?: ($metadata['shipping_tracking_url'] ?? null);
            $metadata['easyparcel_awb_pending'] = empty($details['awb_number']);

            $this->order->update([
                'tracking_id' => $details['awb_number'] ?: $this->order->tracking_id,
                'metadata' => $metadata,
            ]);

            session()->flash('success', $details['awb_number']
                ? "AWB updated: {$details['awb_number']}"
                : 'AWB is still being generated. Try again shortly.');
        } catch (Exception $e) {
            session()->flash('error', 'Failed to refresh AWB: '.$e->getMessage());
        }

        $this->order->refresh();
    }

    // Class Assignment
    public bool $showAssignClassModal = false;

    public string $classSearch = '';

    public array $selectedClassIds = [];

    // Inline WhatsApp group link editing (keyed per assignment row, not per
    // class — the same class can legitimately appear in more than one row).
    public ?int $editingWhatsappApprovalId = null;

    public string $whatsappLinkInput = '';

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

        $query = ClassModel::query()
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

        return ClassModel::whereIn('shipment_product_id', $productIds)
            ->where('status', 'active')
            ->pluck('id')
            ->toArray();
    }

    public function resolveStudent(): ?Student
    {
        // 1. Direct student link
        if ($this->order->student_id) {
            return $this->order->student;
        }

        // 2. Find student via customer user
        if ($this->order->customer_id) {
            $student = Student::where('user_id', $this->order->customer_id)->first();
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
            ClassAssignmentApproval::firstOrCreate(
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
        session()->flash('success', 'Order assigned to '.$count.' class(es) for approval.');
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

        $approval = ClassAssignmentApproval::where('id', $this->removingAssignmentId)
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

    public function startEditClassWhatsapp(int $approvalId): void
    {
        $approval = $this->order->classAssignmentApprovals()->with('class')->find($approvalId);

        if (! $approval?->class) {
            return;
        }

        $this->editingWhatsappApprovalId = $approvalId;
        $this->whatsappLinkInput = $approval->class->whatsapp_group_link ?? '';
        $this->resetErrorBag('whatsappLinkInput');
    }

    public function cancelEditClassWhatsapp(): void
    {
        $this->editingWhatsappApprovalId = null;
        $this->whatsappLinkInput = '';
        $this->resetErrorBag('whatsappLinkInput');
    }

    public function saveClassWhatsapp(): void
    {
        if (! $this->editingWhatsappApprovalId) {
            return;
        }

        $this->validate([
            'whatsappLinkInput' => ['nullable', 'url:http,https', 'max:2048'],
        ], [
            'whatsappLinkInput.url' => 'Please enter a valid link starting with https:// (e.g. https://chat.whatsapp.com/...).',
        ]);

        $approval = $this->order->classAssignmentApprovals()->with('class')->find($this->editingWhatsappApprovalId);

        if (! $approval?->class) {
            $this->cancelEditClassWhatsapp();

            return;
        }

        $class = $approval->class;
        $link = trim($this->whatsappLinkInput);

        $class->update([
            'whatsapp_group_link' => $link !== '' ? $link : null,
        ]);

        $this->editingWhatsappApprovalId = null;
        $this->whatsappLinkInput = '';

        session()->flash('success', $link !== ''
            ? "WhatsApp group link saved for {$class->title}."
            : "WhatsApp group link removed for {$class->title}.");
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
                <flux:button variant="outline" size="sm" :href="route('admin.orders.receipt-pdf', $order)" target="_blank">
                    <div class="flex items-center justify-center">
                        <flux:icon name="arrow-down-tray" class="w-4 h-4 mr-1" />
                        Download PDF
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

            <!-- Payment Approval (accountant-only, funnel COD orders) -->
            <livewire:admin.orders.payment-approval-card :order="$order" wire:key="payment-approval-{{ $order->id }}" />

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

                    @php
                        $awbPending = (bool) data_get($order->metadata, 'easyparcel_awb_pending');
                        $shipmentNo = data_get($order->metadata, 'easyparcel_shipment_number');
                    @endphp
                    @if($order->tracking_id || $order->shipping_provider)
                        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3 mb-4">
                            <div class="p-3 rounded-lg bg-zinc-50 dark:bg-zinc-700/50">
                                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ $order->tracking_id ? 'Tracking Number (AWB)' : 'Shipment Reference' }}</flux:text>
                                @if($order->tracking_id)
                                    <flux:text class="font-medium text-sm font-mono">{{ $order->tracking_id }}</flux:text>
                                @else
                                    <flux:text class="font-medium text-sm font-mono">{{ $shipmentNo ?: '—' }}</flux:text>
                                    @if($awbPending)
                                        <span class="mt-1 inline-block rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-700 dark:bg-amber-500/15 dark:text-amber-400">AWB pending</span>
                                    @endif
                                @endif
                            </div>
                            @if($order->shipping_provider)
                                <div class="p-3 rounded-lg bg-zinc-50 dark:bg-zinc-700/50">
                                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">Provider</flux:text>
                                    <flux:text class="font-medium text-sm capitalize">{{ ['jnt' => 'J&T Express', 'easyparcel' => 'EasyParcel'][$order->shipping_provider] ?? $order->shipping_provider }}</flux:text>
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

                        <div class="flex flex-wrap gap-2">
                            @if($order->shipping_provider)
                                @if($order->tracking_id)
                                    <flux:button variant="outline" size="sm" wire:click="viewTracking">
                                        <div class="flex items-center justify-center">
                                            <flux:icon name="magnifying-glass" class="w-4 h-4 mr-1" />
                                            View Tracking
                                        </div>
                                    </flux:button>
                                @endif

                                @if($order->shipping_provider === 'easyparcel' && $awbPending)
                                    <flux:button variant="primary" size="sm" wire:click="refreshEasyParcelAwb" wire:loading.attr="disabled" wire:target="refreshEasyParcelAwb">
                                        <div class="flex items-center justify-center">
                                            <flux:icon name="arrow-path" class="w-4 h-4 mr-1" />
                                            <span wire:loading.remove wire:target="refreshEasyParcelAwb">Refresh AWB</span>
                                            <span wire:loading wire:target="refreshEasyParcelAwb">Checking...</span>
                                        </div>
                                    </flux:button>
                                @endif

                                @php $labelUrl = data_get($order->metadata, 'shipping_label_url'); @endphp
                                @if($labelUrl)
                                    <flux:button variant="outline" size="sm" :href="$labelUrl" target="_blank">
                                        <div class="flex items-center justify-center">
                                            <flux:icon name="printer" class="w-4 h-4 mr-1" />
                                            Print Label
                                        </div>
                                    </flux:button>
                                @endif

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

                            @if($this->isEasyParcelEnabled() && in_array($order->status, ['confirmed', 'processing']))
                                <div class="p-4 rounded-lg border border-indigo-200 dark:border-indigo-800 bg-indigo-50 dark:bg-indigo-900/20">
                                    <div class="flex items-center justify-between gap-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-9 h-9 rounded-lg bg-indigo-100 dark:bg-indigo-900/40 flex items-center justify-center shrink-0">
                                                <flux:icon name="globe-alt" class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                                            </div>
                                            <div>
                                                <flux:text class="text-sm font-medium text-indigo-900 dark:text-indigo-200">EasyParcel</flux:text>
                                                <flux:text class="text-xs text-indigo-700 dark:text-indigo-300">Compare couriers, then book &amp; pay in one click.</flux:text>
                                            </div>
                                        </div>
                                        <flux:button variant="outline" size="sm" wire:click="getEasyParcelRates" wire:loading.attr="disabled" wire:target="getEasyParcelRates">
                                            <div class="flex items-center justify-center">
                                                <flux:icon name="magnifying-glass" class="w-4 h-4 mr-1" />
                                                <span wire:loading.remove wire:target="getEasyParcelRates">Get Rates</span>
                                                <span wire:loading wire:target="getEasyParcelRates">Fetching...</span>
                                            </div>
                                        </flux:button>
                                    </div>

                                    {{-- Recipient contact — a missing phone is the #1 cause of EasyParcel
                                         rejecting the booking ("0 requests success, 1 request error"). --}}
                                    @php $contact = $this->shippingContact; @endphp
                                    @if($contact)
                                        <div class="mt-3 rounded-lg border p-3 {{ $contact['phone_valid'] ? 'border-zinc-200 dark:border-zinc-700 bg-white/60 dark:bg-zinc-800/60' : 'border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-900/20' }}">
                                            <div class="flex items-center gap-2">
                                                <flux:icon name="user" class="w-4 h-4 text-zinc-400" />
                                                <flux:text class="text-sm font-medium">{{ $contact['name'] ?: 'Recipient' }}</flux:text>
                                            </div>
                                            @if($contact['phone_valid'])
                                                <div class="mt-1 flex items-center gap-2">
                                                    <flux:icon name="phone" class="w-4 h-4 text-zinc-400" />
                                                    <flux:text class="text-sm font-mono">{{ $contact['phone'] }}</flux:text>
                                                </div>
                                            @else
                                                <div class="mt-2 flex items-start gap-2">
                                                    <flux:icon name="exclamation-triangle" class="w-4 h-4 shrink-0 mt-0.5 text-amber-600 dark:text-amber-400" />
                                                    <flux:text class="text-xs text-amber-800 dark:text-amber-300">No recipient phone number. EasyParcel will reject the booking without it — add one below.</flux:text>
                                                </div>
                                                <div class="mt-2 flex gap-2">
                                                    <flux:input wire:model="receiverPhoneInput" placeholder="e.g. 0123456789" size="sm" class="max-w-[220px]" />
                                                    <flux:button variant="primary" size="sm" wire:click="saveReceiverPhone" wire:loading.attr="disabled" wire:target="saveReceiverPhone">
                                                        <span wire:loading.remove wire:target="saveReceiverPhone">Save Phone</span>
                                                        <span wire:loading wire:target="saveReceiverPhone">Saving...</span>
                                                    </flux:button>
                                                </div>
                                                @error('receiverPhoneInput')
                                                    <flux:text class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</flux:text>
                                                @enderror
                                            @endif
                                        </div>
                                    @endif

                                    @if(!empty($easyParcelRates))
                                        <div class="mt-4 space-y-2">
                                            @foreach($easyParcelRates as $rate)
                                                <label wire:key="ep-rate-{{ $rate['service_id'] }}"
                                                    class="flex items-center justify-between gap-3 rounded-lg border p-3 cursor-pointer transition-colors
                                                        {{ $easyParcelServiceId === $rate['service_id'] ? 'border-indigo-400 bg-white dark:bg-zinc-800 ring-1 ring-indigo-300' : 'border-zinc-200 dark:border-zinc-700 bg-white/60 dark:bg-zinc-800/60 hover:border-indigo-300' }}">
                                                    <div class="flex items-center gap-3 min-w-0">
                                                        <input type="radio" wire:model.live="easyParcelServiceId" value="{{ $rate['service_id'] }}" class="h-4 w-4 shrink-0 accent-indigo-600" />
                                                        <div class="min-w-0">
                                                            <flux:text class="text-sm font-medium truncate">{{ $rate['name'] }}</flux:text>
                                                            @if($rate['days'])
                                                                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">~{{ $rate['days'] }} day(s)</flux:text>
                                                            @endif
                                                        </div>
                                                    </div>
                                                    <flux:text class="text-sm font-semibold whitespace-nowrap">MYR {{ number_format($rate['price'], 2) }}</flux:text>
                                                </label>
                                            @endforeach

                                            <div class="flex justify-end pt-1">
                                                <flux:button variant="primary" size="sm" wire:click="bookEasyParcelShipment" wire:loading.attr="disabled" wire:target="bookEasyParcelShipment"
                                                    wire:confirm="Book this shipment and pay from your EasyParcel credit?">
                                                    <div class="flex items-center justify-center">
                                                        <flux:icon name="truck" class="w-4 h-4 mr-1" />
                                                        <span wire:loading.remove wire:target="bookEasyParcelShipment">Book &amp; Pay</span>
                                                        <span wire:loading wire:target="bookEasyParcelShipment">Booking...</span>
                                                    </div>
                                                </flux:button>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endif

                            @if(! $this->isEasyParcelEnabled() && in_array($order->status, ['confirmed', 'processing']) && ($easyParcelHint = $this->easyParcelHint()))
                                <div class="p-4 rounded-lg border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20">
                                    <div class="flex items-start gap-3">
                                        <div class="w-9 h-9 rounded-lg bg-amber-100 dark:bg-amber-900/40 flex items-center justify-center shrink-0">
                                            <flux:icon name="globe-alt" class="w-5 h-5 text-amber-600 dark:text-amber-400" />
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <flux:text class="text-sm font-medium text-amber-900 dark:text-amber-200">EasyParcel not available</flux:text>
                                            <flux:text class="text-xs text-amber-700 dark:text-amber-300">{{ $easyParcelHint }}</flux:text>
                                            <a href="{{ route('admin.settings.shipping') }}" wire:navigate class="mt-1.5 inline-flex items-center gap-1 text-xs font-semibold text-amber-800 hover:text-amber-900 dark:text-amber-300 dark:hover:text-amber-200 transition-colors">
                                                Open Settings → Shipping
                                                <flux:icon name="arrow-right" class="w-3 h-3" />
                                            </a>
                                        </div>
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
                            <div class="flex items-start justify-between p-3 bg-zinc-50 dark:bg-zinc-700/50 rounded-lg" wire:key="assignment-{{ $assignment->id }}">
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-zinc-900 dark:text-white">
                                        {{ $assignment->class->title }}
                                    </p>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ $assignment->class->course?->name ?? 'No Course' }}
                                        &middot; Assigned by {{ $assignment->assignedByUser?->name ?? 'Unknown User' }}
                                        &middot; {{ $assignment->created_at->diffForHumans() }}
                                    </p>

                                    {{-- WhatsApp group link indicator + inline editor --}}
                                    <div class="mt-2">
                                        @if($editingWhatsappApprovalId === $assignment->id)
                                            <div wire:key="wa-edit-{{ $assignment->id }}" class="flex flex-col gap-1.5">
                                                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                                                    <flux:input
                                                        wire:model="whatsappLinkInput"
                                                        wire:keydown.enter="saveClassWhatsapp"
                                                        wire:keydown.escape="cancelEditClassWhatsapp"
                                                        type="url"
                                                        size="sm"
                                                        autofocus
                                                        aria-label="WhatsApp group link"
                                                        placeholder="https://chat.whatsapp.com/..."
                                                        class="w-full sm:max-w-xs"
                                                    />
                                                    <div class="flex items-center gap-2">
                                                        <flux:button size="sm" variant="primary" icon="check" wire:click="saveClassWhatsapp" wire:loading.attr="disabled" wire:target="saveClassWhatsapp">Save</flux:button>
                                                        <flux:button size="sm" variant="ghost" wire:click="cancelEditClassWhatsapp">Cancel</flux:button>
                                                    </div>
                                                </div>
                                                <flux:error name="whatsappLinkInput" />
                                            </div>
                                        @elseif($assignment->class->whatsapp_group_link)
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 dark:bg-emerald-900/20 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:text-emerald-400 ring-1 ring-inset ring-emerald-600/10 dark:ring-emerald-400/20">
                                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893A11.821 11.821 0 0020.885 3.488"/></svg>
                                                    WhatsApp group linked
                                                </span>
                                                <a href="{{ $assignment->class->whatsapp_group_link }}" target="_blank" class="inline-flex items-center gap-1 text-xs font-medium text-emerald-600 hover:text-emerald-700 dark:text-emerald-400 dark:hover:text-emerald-300">
                                                    <flux:icon name="arrow-top-right-on-square" class="w-3.5 h-3.5" />
                                                    Open
                                                </a>
                                                <button type="button" wire:click="startEditClassWhatsapp({{ $assignment->id }})" class="inline-flex items-center gap-1 text-xs text-zinc-400 hover:text-zinc-600 dark:text-zinc-500 dark:hover:text-zinc-300 transition-colors">
                                                    <flux:icon name="pencil-square" class="w-3.5 h-3.5" />
                                                    Edit
                                                </button>
                                            </div>
                                        @else
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="inline-flex items-center gap-1 rounded-full bg-zinc-100 dark:bg-zinc-700 px-2 py-0.5 text-xs font-medium text-zinc-500 dark:text-zinc-400 ring-1 ring-inset ring-zinc-500/10 dark:ring-zinc-400/20">
                                                    <flux:icon name="minus-circle" class="w-3.5 h-3.5" />
                                                    No WhatsApp group
                                                </span>
                                                <flux:button size="xs" variant="ghost" icon="plus" wire:click="startEditClassWhatsapp({{ $assignment->id }})">Add Link</flux:button>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 shrink-0 ml-2">
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