<?php

use App\Models\ProductOrder;
use App\Models\ProductOrderPayment;
use App\Models\Product;
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
    public float $taxRate = 6.0; // GST percentage (editable)
    public float $taxAmount = 0;
    public float $total = 0;
    public string $paymentStatus = 'pending';
    public string $paymentMethod = 'cash';

    public function mount(ProductOrder $order): void
    {
        $this->order = $order->load(['items.product', 'items.warehouse', 'customer', 'addresses']);

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

        // Initialize order items
        foreach ($this->order->items as $item) {
            $this->orderItems[] = [
                'product_id' => $item->product_id,
                'product_variant_id' => $item->product_variant_id,
                'warehouse_id' => $item->warehouse_id,
                'quantity' => $item->quantity_ordered,
                'unit_price' => number_format($item->unit_price, 2),
                'total_price' => $item->total_price,
            ];
        }

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
            'product_id' => '',
            'product_variant_id' => null,
            'warehouse_id' => '',
            'quantity' => 1,
            'unit_price' => '0',
            'total_price' => 0,
        ];
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
        $this->total = $this->subtotal + $this->taxAmount;
    }

    public function updatedTaxRate(): void
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

        // Validate products and warehouses
        foreach ($this->orderItems as $item) {
            if (empty($item['product_id']) || empty($item['warehouse_id'])) {
                session()->flash('error', 'Please select product and warehouse for all items.');
                return;
            }

            $productExists = Product::where('id', $item['product_id'])->exists();
            $warehouseExists = Warehouse::where('id', $item['warehouse_id'])->exists();

            if (!$productExists || !$warehouseExists) {
                session()->flash('error', 'Invalid product or warehouse selected.');
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
                'tax_amount' => $this->taxAmount,
                'total_amount' => $this->total,
                'customer_notes' => $this->form['notes'],
            ]);

            // Handle payment information
            $this->updatePaymentInformation();
        });

        // Update order items
        $this->order->items()->delete();
        foreach ($this->orderItems as $item) {
            $product = Product::find($item['product_id']);
            $this->order->items()->create([
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
            <flux:text class="mt-2">Update order details and information</flux:text>
        </div>
        <flux:button variant="outline" :href="route('admin.orders.show', $order)" wire:navigate>
            <flux:icon name="arrow-left" class="w-4 h-4 mr-2" />
            Back to Order
        </flux:button>
    </div>

    <div class="grid lg:grid-cols-3 gap-6">
        <!-- Left Column - Order Details -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Customer Information -->
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <flux:heading size="lg" class="mb-4">Customer Information</flux:heading>

                <div class="space-y-4">
                    <div>
                        <flux:field>
                            <flux:label>Customer Type</flux:label>
                            <div class="flex space-x-4">
                                <label class="flex items-center">
                                    <input type="radio" wire:model.live="form.customer_type" value="existing" class="mr-2">
                                    Existing Customer
                                </label>
                                <label class="flex items-center">
                                    <input type="radio" wire:model.live="form.customer_type" value="new" class="mr-2">
                                    New Customer
                                </label>
                            </div>
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
                        <div>
                            <flux:field>
                                <flux:label>Email</flux:label>
                                <flux:input
                                    wire:model.live="form.customer_email"
                                    type="email"
                                    placeholder="customer@example.com"
                                    :readonly="$form['customer_type'] === 'existing'"
                                />
                            </flux:field>
                        </div>
                        <div>
                            <flux:field>
                                <flux:label>Full Name</flux:label>
                                <flux:input
                                    wire:model.live="form.customer_name"
                                    placeholder="Customer Name"
                                    :readonly="$form['customer_type'] === 'existing'"
                                />
                            </flux:field>
                        </div>
                    </div>

                    <div>
                        <flux:field>
                            <flux:label>Phone (Optional)</flux:label>
                            <flux:input wire:model.live="form.customer_phone" placeholder="+60123456789" />
                        </flux:field>
                    </div>
                </div>
            </div>

            <!-- Order Items -->
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <div class="flex items-center justify-between mb-4">
                    <flux:heading size="lg">Order Items</flux:heading>
                    <flux:button variant="outline" wire:click="addItem">
                        <flux:icon name="plus" class="w-4 h-4 mr-2" />
                        <div class="flex items-center justify-center">
                            <flux:icon name="plus" class="w-4 h-4 mr-1" />
                            Add Item
                        </div>
                    </flux:button>
                </div>

                <div class="space-y-4">
                    @foreach($orderItems as $index => $item)
                        <div class="border rounded-lg p-4 bg-gray-50">
                            <div class="grid md:grid-cols-4 gap-4 items-end">
                                <div>
                                    <flux:field>
                                        <flux:label>Product</flux:label>
                                        <flux:select wire:model.live="orderItems.{{ $index }}.product_id" wire:change="updateItemPrice({{ $index }})">
                                            <option value="">Select Product...</option>
                                            @foreach($products as $product)
                                                <option value="{{ $product->id }}">{{ $product->name }} (RM {{ number_format($product->base_price, 2) }})</option>
                                            @endforeach
                                        </flux:select>
                                    </flux:field>
                                </div>
                                <div>
                                    <flux:field>
                                        <flux:label>Warehouse</flux:label>
                                        <flux:select wire:model.live="orderItems.{{ $index }}.warehouse_id">
                                            <option value="">Select Warehouse...</option>
                                            @foreach($warehouses as $warehouse)
                                                <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                                            @endforeach
                                        </flux:select>
                                    </flux:field>
                                </div>
                                <div>
                                    <flux:field>
                                        <flux:label>Quantity</flux:label>
                                        <flux:input
                                            type="number"
                                            wire:model.live="orderItems.{{ $index }}.quantity"
                                            wire:change="updateItemTotal({{ $index }})"
                                            min="1"
                                        />
                                    </flux:field>
                                </div>
                                <div>
                                    <flux:field>
                                        <flux:label>Unit Price</flux:label>
                                        <flux:input
                                            type="number"
                                            step="0.01"
                                            wire:model.live="orderItems.{{ $index }}.unit_price"
                                            wire:change="updateItemTotal({{ $index }})"
                                        />
                                    </flux:field>
                                </div>
                            </div>
                            <div class="flex justify-between items-center mt-4">
                                <p class="font-medium">Total: MYR {{ number_format($item['total_price'], 2) }}</p>
                                @if(count($orderItems) > 1)
                                    <flux:button variant="outline" size="sm" wire:click="removeItem({{ $index }})">
                                        <flux:icon name="trash" class="w-4 h-4" />
                                    </flux:button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Billing Address -->
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <flux:heading size="lg" class="mb-4">Billing Address</flux:heading>

                <div class="space-y-4">
                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <flux:field>
                                <flux:label>First Name</flux:label>
                                <flux:input wire:model.live="form.billing_address.first_name" />
                            </flux:field>
                        </div>
                        <div>
                            <flux:field>
                                <flux:label>Last Name</flux:label>
                                <flux:input wire:model.live="form.billing_address.last_name" />
                            </flux:field>
                        </div>
                    </div>

                    <div>
                        <flux:field>
                            <flux:label>Company (Optional)</flux:label>
                            <flux:input wire:model.live="form.billing_address.company" />
                        </flux:field>
                    </div>

                    <div class="space-y-2">
                        <flux:field>
                            <flux:label>Address</flux:label>
                            <flux:input wire:model.live="form.billing_address.address_line_1" placeholder="Street address" />
                        </flux:field>
                        <flux:input wire:model.live="form.billing_address.address_line_2" placeholder="Apartment, suite, etc. (optional)" />
                    </div>

                    <div class="grid md:grid-cols-3 gap-4">
                        <div>
                            <flux:field>
                                <flux:label>City</flux:label>
                                <flux:input wire:model.live="form.billing_address.city" />
                            </flux:field>
                        </div>
                        <div>
                            <flux:field>
                                <flux:label>State</flux:label>
                                <flux:input wire:model.live="form.billing_address.state" />
                            </flux:field>
                        </div>
                        <div>
                            <flux:field>
                                <flux:label>Postal Code</flux:label>
                                <flux:input wire:model.live="form.billing_address.postal_code" />
                            </flux:field>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Order Notes -->
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <flux:heading size="lg" class="mb-4">Order Notes</flux:heading>
                <flux:textarea
                    wire:model.live="form.notes"
                    placeholder="Any special instructions or notes for this order..."
                    rows="3"
                />
            </div>
        </div>

        <!-- Right Column - Order Summary -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow-sm border p-6 sticky top-6">
                <flux:heading size="lg" class="mb-4">Order Summary</flux:heading>

                <!-- Order Status -->
                <div class="space-y-4 mb-6">
                    <div>
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
                    </div>
                    <div>
                        <flux:field>
                            <flux:label>Payment Status</flux:label>
                            <flux:select wire:model.live="paymentStatus">
                                <option value="pending">Pending</option>
                                <option value="completed">Completed</option>
                                <option value="failed">Failed</option>
                                <option value="refunded">Refunded</option>
                            </flux:select>
                        </flux:field>
                    </div>
                    <div>
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
                    </div>
                </div>

                <!-- GST Configuration -->
                <div class="mb-6">
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

                <!-- Order Totals -->
                <div class="space-y-3 mb-6">
                    <div class="flex justify-between">
                        <flux:text>Subtotal</flux:text>
                        <flux:text>MYR {{ number_format($subtotal, 2) }}</flux:text>
                    </div>

                    <div class="flex justify-between">
                        <flux:text>Tax (GST {{ number_format($taxRate, 1) }}%)</flux:text>
                        <flux:text>MYR {{ number_format($taxAmount, 2) }}</flux:text>
                    </div>

                    <div class="border-t pt-3">
                        <div class="flex justify-between">
                            <flux:text class="font-semibold text-lg">Total</flux:text>
                            <flux:text class="font-semibold text-lg">MYR {{ number_format($total, 2) }}</flux:text>
                        </div>
                    </div>
                </div>

                <flux:button variant="primary" wire:click="updateOrder" class="w-full">
                    <flux:icon name="check" class="w-4 h-4 mr-2" />
                    Update Order
                </flux:button>
            </div>
        </div>
    </div>
</div>