<?php

use App\Models\ProductOrder;
use App\Models\ProductOrderPayment;
use App\Models\Product;
use App\Models\Package;
use App\Models\Warehouse;
use App\Models\User;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\DB;

new class extends Component
{
    public function layout()
    {
        return 'components.layouts.app.sidebar';
    }

    public ProductOrder $order;
    public array $form = [];
    public array $orderItems = [];
    public float $subtotal = 0;
    public float $shippingCost = 0;
    public float $taxRate = 6.0; // GST percentage (editable)
    public float $taxAmount = 0;
    public float $total = 0;
    public string $paymentStatus = 'pending';
    public string $paymentMethod = 'cash';

    public function mount(ProductOrder $order): void
    {
        $this->order = $order->load(['items.product', 'items.package', 'items.warehouse', 'customer', 'addresses']);

        // Get payment information from payments table
        $latestPayment = $this->order->payments()->latest()->first();
        $this->paymentStatus = $latestPayment?->status ?? 'pending';
        $this->paymentMethod = $latestPayment?->payment_method ?? 'cash';

        // Initialize form with existing order data
        $billingAddress = $this->order->billingAddress();
        $shippingAddress = $this->order->shippingAddress();

        $this->form = [
            'customer_type' => $this->order->customer_id ? 'existing' : 'new',
            'customer_id' => $this->order->customer_id,
            'customer_email' => $this->order->getCustomerEmail(),
            'customer_name' => $this->order->getCustomerName(),
            'customer_phone' => $billingAddress?->phone ?? '',
            'billing_address' => [
                'first_name' => $billingAddress?->first_name ?? '',
                'last_name' => $billingAddress?->last_name ?? '',
                'company' => $billingAddress?->company ?? '',
                'address_line_1' => $billingAddress?->address_line_1 ?? '',
                'address_line_2' => $billingAddress?->address_line_2 ?? '',
                'city' => $billingAddress?->city ?? '',
                'state' => $billingAddress?->state ?? '',
                'postal_code' => $billingAddress?->postal_code ?? '',
                'country' => $billingAddress?->country ?? 'Malaysia',
            ],
            'shipping_address' => [
                'first_name' => $shippingAddress?->first_name ?? '',
                'last_name' => $shippingAddress?->last_name ?? '',
                'company' => $shippingAddress?->company ?? '',
                'address_line_1' => $shippingAddress?->address_line_1 ?? '',
                'address_line_2' => $shippingAddress?->address_line_2 ?? '',
                'city' => $shippingAddress?->city ?? '',
                'state' => $shippingAddress?->state ?? '',
                'postal_code' => $shippingAddress?->postal_code ?? '',
                'country' => $shippingAddress?->country ?? 'Malaysia',
            ],
            'same_as_billing' => $this->addressesAreSame($billingAddress, $shippingAddress),
            'order_status' => $this->order->status,
            'notes' => $this->order->customer_notes ?? '',
        ];

        // Initialize order items (support both products and packages)
        foreach ($this->order->items as $item) {
            $this->orderItems[] = [
                'item_type' => $item->isPackage() ? 'package' : 'product',
                'product_id' => $item->product_id ?? '',
                'package_id' => $item->package_id ?? '',
                'product_variant_id' => $item->product_variant_id,
                'warehouse_id' => $item->warehouse_id,
                'quantity' => $item->quantity_ordered,
                'unit_price' => number_format($item->unit_price, 2),
                'total_price' => $item->total_price,
            ];
        }

        // Initialize shipping cost from existing order
        $this->shippingCost = (float) ($this->order->shipping_cost ?? 0);

        // Initialize tax rate from existing order (calculate percentage from amount)
        if ($this->order->subtotal > 0 && $this->order->tax_amount > 0) {
            $this->taxRate = round(($this->order->tax_amount / $this->order->subtotal) * 100, 2);
        } else {
            $this->taxRate = 6.0; // Default GST rate
        }

        $this->calculateTotals();
    }

    private function addressesAreSame($billing, $shipping): bool
    {
        if (!$billing || !$shipping) return false;

        return $billing->first_name === $shipping->first_name &&
               $billing->last_name === $shipping->last_name &&
               $billing->address_line_1 === $shipping->address_line_1 &&
               $billing->city === $shipping->city &&
               $billing->state === $shipping->state &&
               $billing->postal_code === $shipping->postal_code;
    }

    public function updatedFormCustomerId(): void
    {
        if ($this->form['customer_id']) {
            $customer = User::find($this->form['customer_id']);
            if ($customer) {
                $this->form['customer_email'] = $customer->email;
                $this->form['customer_name'] = $customer->name;

                // Auto-fill billing address with customer name
                $this->form['billing_address']['first_name'] = explode(' ', $customer->name)[0] ?? '';
                $this->form['billing_address']['last_name'] = explode(' ', $customer->name, 2)[1] ?? '';
            }
        }
    }

    public function updatedOrderItems(): void
    {
        $this->calculateTotals();
    }

    public function updatedFormSameAsBilling(): void
    {
        if ($this->form['same_as_billing']) {
            $this->form['shipping_address'] = $this->form['billing_address'];
        }
    }

    public function addItem(): void
    {
        $this->orderItems[] = [
            'item_type' => 'product',
            'product_id' => '',
            'package_id' => '',
            'product_variant_id' => null,
            'warehouse_id' => '',
            'quantity' => 1,
            'unit_price' => '0',
            'total_price' => 0,
        ];
    }

    public function itemTypeChanged(int $index): void
    {
        // Reset item data when type changes
        $this->orderItems[$index]['product_id'] = '';
        $this->orderItems[$index]['package_id'] = '';
        $this->orderItems[$index]['unit_price'] = '0';
        $this->orderItems[$index]['total_price'] = 0;
        $this->calculateTotals();
    }

    public function updatePackagePrice(int $index): void
    {
        if (isset($this->orderItems[$index]['package_id']) && $this->orderItems[$index]['package_id']) {
            $package = Package::find($this->orderItems[$index]['package_id']);
            if ($package) {
                $this->orderItems[$index]['unit_price'] = number_format($package->price, 2);

                // Set default warehouse from package if not set
                if (empty($this->orderItems[$index]['warehouse_id']) && $package->default_warehouse_id) {
                    $this->orderItems[$index]['warehouse_id'] = $package->default_warehouse_id;
                }

                $this->updateItemTotal($index);
            }
        }
    }

    public function removeItem(int $index): void
    {
        unset($this->orderItems[$index]);
        $this->orderItems = array_values($this->orderItems);
        $this->calculateTotals();
    }

    public function updateItemPrice(int $index): void
    {
        if (isset($this->orderItems[$index]['product_id']) && $this->orderItems[$index]['product_id']) {
            $product = Product::find($this->orderItems[$index]['product_id']);
            if ($product) {
                $this->orderItems[$index]['unit_price'] = number_format($product->base_price, 2);
                $this->updateItemTotal($index);
            }
        }
    }

    public function updateItemTotal(int $index): void
    {
        $quantity = (int) $this->orderItems[$index]['quantity'];
        $unitPrice = (float) $this->orderItems[$index]['unit_price'];
        $this->orderItems[$index]['total_price'] = $quantity * $unitPrice;
        $this->calculateTotals();
    }

    public function calculateTotals(): void
    {
        $this->subtotal = collect($this->orderItems)->sum('total_price');
        $this->taxAmount = $this->subtotal * ($this->taxRate / 100);
        $this->total = $this->subtotal + $this->shippingCost + $this->taxAmount;
    }

    public function updatedTaxRate(): void
    {
        $this->calculateTotals();
    }

    public function updatedShippingCost(): void
    {
        $this->calculateTotals();
    }

    public function updateOrder(): void
    {
        // Validate required fields
        if (empty($this->orderItems)) {
            session()->flash('error', 'Please add at least one item to the order.');
            return;
        }

        if ($this->form['customer_type'] === 'existing' && empty($this->form['customer_id'])) {
            session()->flash('error', 'Please select a customer.');
            return;
        }

        if ($this->form['customer_type'] === 'new' && (empty($this->form['customer_email']) || empty($this->form['customer_name']))) {
            session()->flash('error', 'Please provide customer email and name.');
            return;
        }

        // Validate products/packages and warehouses
        foreach ($this->orderItems as $item) {
            if (empty($item['warehouse_id'])) {
                session()->flash('error', 'Please select warehouse for all items.');
                return;
            }

            // Validate based on item type
            if ($item['item_type'] === 'product') {
                if (empty($item['product_id'])) {
                    session()->flash('error', 'Please select product for all product items.');
                    return;
                }

                $productExists = Product::where('id', $item['product_id'])->exists();
                if (!$productExists) {
                    session()->flash('error', 'Invalid product selected.');
                    return;
                }
            } elseif ($item['item_type'] === 'package') {
                if (empty($item['package_id'])) {
                    session()->flash('error', 'Please select package for all package items.');
                    return;
                }

                $packageExists = Package::where('id', $item['package_id'])->exists();
                if (!$packageExists) {
                    session()->flash('error', 'Invalid package selected.');
                    return;
                }
            }

            $warehouseExists = Warehouse::where('id', $item['warehouse_id'])->exists();
            if (!$warehouseExists) {
                session()->flash('error', 'Invalid warehouse selected.');
                return;
            }
        }

        DB::transaction(function () {
            // Update order (without payment fields that don't exist)
            $this->order->update([
                'customer_id' => $this->form['customer_type'] === 'existing' ? $this->form['customer_id'] : null,
                'guest_email' => $this->form['customer_type'] === 'new' ? $this->form['customer_email'] : null,
                'status' => $this->form['order_status'],
                'subtotal' => $this->subtotal,
                'shipping_cost' => $this->shippingCost,
                'tax_amount' => $this->taxAmount,
                'total_amount' => $this->total,
                'customer_notes' => $this->form['notes'],
            ]);

            // Handle payment information
            $this->updatePaymentInformation();
        });

        // Update order items - delete old ones and create new
        $this->order->items()->delete();
        foreach ($this->orderItems as $item) {
            if ($item['item_type'] === 'package') {
                $package = Package::with(['products', 'courses'])->find($item['package_id']);

                $this->order->items()->create([
                    'itemable_type' => Package::class,
                    'itemable_id' => $package->id,
                    'package_id' => $package->id,
                    'warehouse_id' => $item['warehouse_id'],
                    'product_name' => $package->name,
                    'sku' => 'PKG-' . $package->id,
                    'quantity_ordered' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $item['total_price'],
                    'unit_cost' => 0,
                    'package_snapshot' => $package->toArray(),
                    'package_items_snapshot' => $package->items->toArray(),
                ]);
            } else {
                $product = Product::find($item['product_id']);

                $this->order->items()->create([
                    'itemable_type' => Product::class,
                    'itemable_id' => $product->id,
                    'product_id' => $item['product_id'],
                    'product_variant_id' => $item['product_variant_id'],
                    'warehouse_id' => $item['warehouse_id'],
                    'product_name' => $product->name,
                    'variant_name' => null,
                    'sku' => $product->sku,
                    'quantity_ordered' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $item['total_price'],
                    'unit_cost' => $product->cost_price,
                    'product_snapshot' => $product->toArray(),
                ]);
            }
        }

        // Update addresses
        $this->order->addresses()->delete();

        // Create billing address
        $billingData = array_merge($this->form['billing_address'], [
            'email' => $this->form['customer_email'],
            'phone' => $this->form['customer_phone'],
        ]);

        $this->order->addresses()->create([
            'type' => 'billing',
            'first_name' => $billingData['first_name'],
            'last_name' => $billingData['last_name'],
            'company' => $billingData['company'],
            'email' => $billingData['email'],
            'phone' => $billingData['phone'],
            'address_line_1' => $billingData['address_line_1'],
            'address_line_2' => $billingData['address_line_2'],
            'city' => $billingData['city'],
            'state' => $billingData['state'],
            'postal_code' => $billingData['postal_code'],
            'country' => $billingData['country'],
        ]);

        // Create shipping address
        $shippingData = $this->form['same_as_billing'] ? $billingData : array_merge($this->form['shipping_address'], [
            'email' => $this->form['customer_email'],
            'phone' => $this->form['customer_phone'],
        ]);

        $this->order->addresses()->create([
            'type' => 'shipping',
            'first_name' => $shippingData['first_name'],
            'last_name' => $shippingData['last_name'],
            'company' => $shippingData['company'],
            'email' => $shippingData['email'],
            'phone' => $shippingData['phone'],
            'address_line_1' => $shippingData['address_line_1'],
            'address_line_2' => $shippingData['address_line_2'],
            'city' => $shippingData['city'],
            'state' => $shippingData['state'],
            'postal_code' => $shippingData['postal_code'],
            'country' => $shippingData['country'],
        ]);

        session()->flash('success', 'Order updated successfully!');
        $this->redirectRoute('admin.orders.show', $this->order);
    }

    private function updatePaymentInformation(): void
    {
        // Get or create payment record
        $payment = $this->order->payments()->latest()->first();

        if (!$payment) {
            // Create new payment record
            $this->order->payments()->create([
                'payment_method' => $this->paymentMethod,
                'amount' => $this->total,
                'currency' => $this->order->currency ?? 'MYR',
                'status' => $this->paymentStatus,
                'paid_at' => $this->paymentStatus === 'completed' ? now() : null,
            ]);
        } else {
            // Update existing payment
            $payment->update([
                'payment_method' => $this->paymentMethod,
                'amount' => $this->total,
                'status' => $this->paymentStatus,
                'paid_at' => $this->paymentStatus === 'completed' ? now() : null,
            ]);
        }

        // Add system note for payment update
        $this->order->addSystemNote("Payment information updated: {$this->paymentMethod} - {$this->paymentStatus}");
    }

    public function with(): array
    {
        return [
            'products' => Product::active()->get(),
            'packages' => Package::active()->with(['products', 'courses'])->get(),
            'warehouses' => Warehouse::all(),
            'customers' => User::whereIn('role', ['student', 'user'])->get(),
        ];
    }
}; ?>

<div>
    <!-- Header -->
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Edit Order #{{ $order->order_number }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">Update order details and information</flux:text>
        </div>
        <flux:button variant="outline" :href="route('admin.orders.show', $order)" wire:navigate>
            <div class="flex items-center justify-center">
                <flux:icon name="arrow-left" class="w-4 h-4 mr-1.5" />
                Back to Order
            </div>
        </flux:button>
    </div>

    @if(session('error'))
        <div class="mb-5 rounded-xl border border-red-200 dark:border-red-800/50 bg-red-50 dark:bg-red-900/10 px-4 py-3">
            <div class="flex items-center gap-2">
                <flux:icon name="exclamation-circle" class="w-5 h-5 text-red-500" />
                <flux:text size="sm" class="text-red-700 dark:text-red-400">{{ session('error') }}</flux:text>
            </div>
        </div>
    @endif

    <div class="grid lg:grid-cols-3 gap-6">
        <!-- Left Column - Order Details -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Customer Information -->
            <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700">
                <div class="px-6 py-4 border-b border-zinc-100 dark:border-zinc-700">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center">
                            <flux:icon name="user" class="w-4 h-4 text-blue-600 dark:text-blue-400" />
                        </div>
                        <flux:heading size="lg">Customer Information</flux:heading>
                    </div>
                </div>

                <div class="p-6 space-y-4">
                    <div>
                        <flux:field>
                            <flux:label>Customer Type</flux:label>
                            <flux:radio.group wire:model.live="form.customer_type" variant="segmented">
                                <flux:radio value="existing" label="Existing Customer" />
                                <flux:radio value="new" label="New Customer" />
                            </flux:radio.group>
                        </flux:field>
                    </div>

                    @if($form['customer_type'] === 'existing')
                        <div>
                            <flux:field>
                                <flux:label>Select Customer</flux:label>
                                <flux:select wire:model.live="form.customer_id" placeholder="Choose a customer...">
                                    @foreach($customers as $customer)
                                        <option value="{{ $customer->id }}">{{ $customer->name }} ({{ $customer->email }})</option>
                                    @endforeach
                                </flux:select>
                            </flux:field>
                        </div>
                    @endif

                    <div class="grid md:grid-cols-2 gap-4">
                        <flux:field>
                            <flux:label>Email</flux:label>
                            <flux:input
                                wire:model.live="form.customer_email"
                                type="email"
                                placeholder="customer@example.com"
                                :readonly="$form['customer_type'] === 'existing'"
                            />
                        </flux:field>
                        <flux:field>
                            <flux:label>Full Name</flux:label>
                            <flux:input
                                wire:model.live="form.customer_name"
                                placeholder="Customer Name"
                                :readonly="$form['customer_type'] === 'existing'"
                            />
                        </flux:field>
                    </div>

                    <flux:field>
                        <flux:label>Phone (Optional)</flux:label>
                        <flux:input wire:model.live="form.customer_phone" placeholder="+60123456789" />
                    </flux:field>
                </div>
            </div>

            <!-- Order Items -->
            <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700">
                <div class="px-6 py-4 border-b border-zinc-100 dark:border-zinc-700">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-lg bg-purple-50 dark:bg-purple-900/20 flex items-center justify-center">
                                <flux:icon name="shopping-bag" class="w-4 h-4 text-purple-600 dark:text-purple-400" />
                            </div>
                            <flux:heading size="lg">Order Items</flux:heading>
                            <flux:badge size="sm" color="zinc">{{ count($orderItems) }}</flux:badge>
                        </div>
                        <flux:button variant="outline" size="sm" wire:click="addItem">
                            <div class="flex items-center justify-center">
                                <flux:icon name="plus" class="w-4 h-4 mr-1" />
                                Add Item
                            </div>
                        </flux:button>
                    </div>
                </div>

                <div class="p-6 space-y-4">
                    @foreach($orderItems as $index => $item)
                        <div wire:key="item-{{ $index }}" class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-900/30 overflow-hidden">
                            <!-- Item Header -->
                            <div class="px-4 py-3 bg-zinc-100/50 dark:bg-zinc-800/50 border-b border-zinc-200 dark:border-zinc-700 flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <span class="w-6 h-6 rounded-md bg-zinc-200 dark:bg-zinc-700 flex items-center justify-center text-xs font-bold text-zinc-600 dark:text-zinc-400">{{ $index + 1 }}</span>
                                    <flux:radio.group wire:model.live="orderItems.{{ $index }}.item_type" wire:change="itemTypeChanged({{ $index }})" variant="segmented">
                                        <flux:radio value="product" label="Product" />
                                        <flux:radio value="package" label="Package" />
                                    </flux:radio.group>
                                </div>
                                @if(count($orderItems) > 1)
                                    <flux:button variant="ghost" size="sm" wire:click="removeItem({{ $index }})">
                                        <flux:icon name="trash" class="w-4 h-4 text-red-500" />
                                    </flux:button>
                                @endif
                            </div>

                            <div class="p-4">
                                <div class="grid md:grid-cols-4 gap-4 items-end">
                                    @if($item['item_type'] === 'product')
                                        <flux:field>
                                            <flux:label>Product</flux:label>
                                            <flux:select wire:model.live="orderItems.{{ $index }}.product_id" wire:change="updateItemPrice({{ $index }})">
                                                <option value="">Select Product...</option>
                                                @foreach($products as $product)
                                                    <option value="{{ $product->id }}">{{ $product->name }} (RM {{ number_format($product->base_price, 2) }})</option>
                                                @endforeach
                                            </flux:select>
                                        </flux:field>
                                    @else
                                        <flux:field>
                                            <flux:label>Package</flux:label>
                                            <flux:select wire:model.live="orderItems.{{ $index }}.package_id" wire:change="updatePackagePrice({{ $index }})">
                                                <option value="">Select Package...</option>
                                                @foreach($packages as $package)
                                                    <option value="{{ $package->id }}">
                                                        {{ $package->name }} (RM {{ $package->price }})
                                                        @if($package->track_stock) - Stock Tracked @endif
                                                    </option>
                                                @endforeach
                                            </flux:select>
                                        </flux:field>
                                    @endif

                                    <flux:field>
                                        <flux:label>Warehouse</flux:label>
                                        <flux:select wire:model.live="orderItems.{{ $index }}.warehouse_id">
                                            <option value="">Select Warehouse...</option>
                                            @foreach($warehouses as $warehouse)
                                                <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                                            @endforeach
                                        </flux:select>
                                    </flux:field>
                                    <flux:field>
                                        <flux:label>Quantity</flux:label>
                                        <flux:input
                                            type="number"
                                            wire:model.live="orderItems.{{ $index }}.quantity"
                                            wire:change="updateItemTotal({{ $index }})"
                                            min="1"
                                        />
                                    </flux:field>
                                    <flux:field>
                                        <flux:label>Unit Price (RM)</flux:label>
                                        <flux:input
                                            type="number"
                                            step="0.01"
                                            wire:model.live="orderItems.{{ $index }}.unit_price"
                                            wire:change="updateItemTotal({{ $index }})"
                                        />
                                    </flux:field>
                                </div>

                                <div class="mt-3 pt-3 border-t border-zinc-200 dark:border-zinc-700 flex items-center justify-end">
                                    <flux:text size="sm" class="font-semibold text-zinc-700 dark:text-zinc-300 tabular-nums">
                                        Item Total: <span class="text-zinc-900 dark:text-white">MYR {{ number_format($item['total_price'], 2) }}</span>
                                    </flux:text>
                                </div>
                            </div>

                            @if($item['item_type'] === 'package' && !empty($item['package_id']))
                                @php
                                    $selectedPackage = $packages->find($item['package_id']);
                                @endphp
                                @if($selectedPackage)
                                    <div class="px-4 pb-4">
                                        <div class="rounded-lg bg-purple-50 dark:bg-purple-900/10 border border-purple-100 dark:border-purple-800/30 p-3">
                                            <flux:text size="sm" class="font-semibold text-purple-700 dark:text-purple-400 mb-2">Package Contents</flux:text>
                                            <div class="space-y-1">
                                                @foreach($selectedPackage->products as $product)
                                                    <div class="flex justify-between text-sm">
                                                        <flux:text size="sm" class="text-purple-600 dark:text-purple-300">{{ $product->name }}</flux:text>
                                                        <flux:text size="sm" class="text-purple-500 dark:text-purple-400">Qty: {{ $product->pivot->quantity }}</flux:text>
                                                    </div>
                                                @endforeach
                                                @foreach($selectedPackage->courses as $course)
                                                    <div class="flex justify-between text-sm">
                                                        <flux:text size="sm" class="text-purple-600 dark:text-purple-300">{{ $course->name }}</flux:text>
                                                        <flux:badge size="sm" color="purple">Course</flux:badge>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Billing Address -->
            <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700">
                <div class="px-6 py-4 border-b border-zinc-100 dark:border-zinc-700">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 flex items-center justify-center">
                            <flux:icon name="map-pin" class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                        </div>
                        <flux:heading size="lg">Billing Address</flux:heading>
                    </div>
                </div>

                <div class="p-6 space-y-4">
                    <div class="grid md:grid-cols-2 gap-4">
                        <flux:field>
                            <flux:label>First Name</flux:label>
                            <flux:input wire:model.live="form.billing_address.first_name" />
                        </flux:field>
                        <flux:field>
                            <flux:label>Last Name</flux:label>
                            <flux:input wire:model.live="form.billing_address.last_name" />
                        </flux:field>
                    </div>

                    <flux:field>
                        <flux:label>Company (Optional)</flux:label>
                        <flux:input wire:model.live="form.billing_address.company" />
                    </flux:field>

                    <div class="space-y-2">
                        <flux:field>
                            <flux:label>Address</flux:label>
                            <flux:input wire:model.live="form.billing_address.address_line_1" placeholder="Street address" />
                        </flux:field>
                        <flux:input wire:model.live="form.billing_address.address_line_2" placeholder="Apartment, suite, etc. (optional)" />
                    </div>

                    <div class="grid md:grid-cols-3 gap-4">
                        <flux:field>
                            <flux:label>City</flux:label>
                            <flux:input wire:model.live="form.billing_address.city" />
                        </flux:field>
                        <flux:field>
                            <flux:label>State</flux:label>
                            <flux:input wire:model.live="form.billing_address.state" />
                        </flux:field>
                        <flux:field>
                            <flux:label>Postal Code</flux:label>
                            <flux:input wire:model.live="form.billing_address.postal_code" />
                        </flux:field>
                    </div>
                </div>
            </div>

            <!-- Order Notes -->
            <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700">
                <div class="px-6 py-4 border-b border-zinc-100 dark:border-zinc-700">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-amber-50 dark:bg-amber-900/20 flex items-center justify-center">
                            <flux:icon name="chat-bubble-left-ellipsis" class="w-4 h-4 text-amber-600 dark:text-amber-400" />
                        </div>
                        <flux:heading size="lg">Order Notes</flux:heading>
                    </div>
                </div>
                <div class="p-6">
                    <flux:textarea
                        wire:model.live="form.notes"
                        placeholder="Any special instructions or notes for this order..."
                        rows="3"
                    />
                </div>
            </div>
        </div>

        <!-- Right Column - Order Summary -->
        <div class="lg:col-span-1">
            <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 sticky top-6 overflow-hidden">
                <div class="px-6 py-4 border-b border-zinc-100 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-zinc-200 dark:bg-zinc-700 flex items-center justify-center">
                            <flux:icon name="clipboard-document-list" class="w-4 h-4 text-zinc-600 dark:text-zinc-400" />
                        </div>
                        <flux:heading size="lg">Order Summary</flux:heading>
                    </div>
                </div>

                <div class="p-6 space-y-5">
                    <!-- Order Status -->
                    <flux:field>
                        <flux:label>Order Status</flux:label>
                        <flux:select wire:model.live="form.order_status">
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

                    <flux:field>
                        <flux:label>Payment Status</flux:label>
                        <flux:select wire:model.live="paymentStatus">
                            <option value="pending">Pending</option>
                            <option value="completed">Completed</option>
                            <option value="failed">Failed</option>
                            <option value="refunded">Refunded</option>
                        </flux:select>
                    </flux:field>

                    <flux:field>
                        <flux:label>Payment Method</flux:label>
                        <flux:select wire:model.live="paymentMethod">
                            <option value="cash">Cash</option>
                            <option value="credit_card">Credit Card</option>
                            <option value="debit_card">Debit Card</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="fpx">FPX Online Banking</option>
                            <option value="grabpay">GrabPay</option>
                            <option value="boost">Boost</option>
                        </flux:select>
                    </flux:field>

                    <div class="border-t border-zinc-100 dark:border-zinc-700"></div>

                    <div class="grid grid-cols-2 gap-3">
                        <flux:field>
                            <flux:label>Shipping (MYR)</flux:label>
                            <flux:input
                                type="number"
                                step="0.01"
                                min="0"
                                wire:model.live="shippingCost"
                                placeholder="0.00"
                            />
                        </flux:field>
                        <flux:field>
                            <flux:label>GST Rate (%)</flux:label>
                            <flux:input
                                type="number"
                                step="0.01"
                                min="0"
                                max="100"
                                wire:model.live="taxRate"
                                placeholder="6.00"
                            />
                        </flux:field>
                    </div>

                    <div class="border-t border-zinc-100 dark:border-zinc-700"></div>

                    <!-- Order Totals -->
                    <div class="space-y-2.5">
                        <div class="flex justify-between items-center">
                            <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">Subtotal</flux:text>
                            <flux:text size="sm" class="tabular-nums text-zinc-700 dark:text-zinc-300">MYR {{ number_format($subtotal, 2) }}</flux:text>
                        </div>

                        @if($shippingCost > 0)
                            <div class="flex justify-between items-center">
                                <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">Shipping</flux:text>
                                <flux:text size="sm" class="tabular-nums text-zinc-700 dark:text-zinc-300">MYR {{ number_format($shippingCost, 2) }}</flux:text>
                            </div>
                        @endif

                        <div class="flex justify-between items-center">
                            <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">Tax (GST {{ number_format($taxRate, 1) }}%)</flux:text>
                            <flux:text size="sm" class="tabular-nums text-zinc-700 dark:text-zinc-300">MYR {{ number_format($taxAmount, 2) }}</flux:text>
                        </div>
                    </div>

                    <div class="rounded-xl bg-zinc-50 dark:bg-zinc-900/50 border border-zinc-200 dark:border-zinc-700 p-4">
                        <div class="flex justify-between items-center">
                            <flux:text class="font-semibold text-zinc-900 dark:text-white">Total</flux:text>
                            <flux:text class="font-bold text-lg text-emerald-600 dark:text-emerald-400 tabular-nums">MYR {{ number_format($total, 2) }}</flux:text>
                        </div>
                    </div>

                    <flux:button variant="primary" wire:click="updateOrder" class="w-full">
                        <div class="flex items-center justify-center">
                            <flux:icon name="check" class="w-4 h-4 mr-1.5" />
                            Update Order
                        </div>
                    </flux:button>
                </div>
            </div>
        </div>
    </div>
</div>