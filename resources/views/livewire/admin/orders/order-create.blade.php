<?php

use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\ProductOrderItem;
use App\Models\ProductOrderPayment;
use App\Models\User;
use App\Models\Warehouse;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\DB;

new class extends Component
{
    public function layout()
    {
        return 'components.layouts.app.sidebar';
    }

    public array $form = [
        'customer_type' => 'existing', // 'existing' or 'new'
        'customer_id' => '',
        'customer_email' => '',
        'customer_name' => '',
        'customer_phone' => '',

        'billing_address' => [
            'first_name' => '',
            'last_name' => '',
            'company' => '',
            'address_line_1' => '',
            'address_line_2' => '',
            'city' => '',
            'state' => '',
            'postal_code' => '',
            'country' => 'Malaysia',
        ],

        'shipping_address' => [
            'first_name' => '',
            'last_name' => '',
            'company' => '',
            'address_line_1' => '',
            'address_line_2' => '',
            'city' => '',
            'state' => '',
            'postal_code' => '',
            'country' => 'Malaysia',
        ],

        'same_as_billing' => true,
        'payment_method' => 'cash',
        'payment_status' => 'pending',
        'order_status' => 'pending',
        'notes' => '',
    ];

    public array $orderItems = [];
    public float $subtotal = 0;
    public float $taxRate = 6.0; // GST percentage (editable)
    public float $taxAmount = 0;
    public float $total = 0;

    public function mount(): void
    {
        $this->addOrderItem(); // Start with one empty item
    }

    public function addOrderItem(): void
    {
        $this->orderItems[] = [
            'product_id' => '',
            'product_variant_id' => null,
            'warehouse_id' => '',
            'quantity' => 1,
            'unit_price' => 0,
            'total_price' => 0,
        ];
    }

    public function removeOrderItem(int $index): void
    {
        unset($this->orderItems[$index]);
        $this->orderItems = array_values($this->orderItems); // Re-index array
        $this->calculateTotals();
    }

    public function updatedOrderItems(): void
    {
        $this->calculateTotals();
    }

    public function productSelected(int $index, int $productId): void
    {
        $product = Product::find($productId);
        if ($product) {
            $this->orderItems[$index]['unit_price'] = $product->base_price;
            $this->orderItems[$index]['total_price'] = $product->base_price * $this->orderItems[$index]['quantity'];
        }
        $this->calculateTotals();
    }

    public function quantityUpdated(int $index): void
    {
        $quantity = $this->orderItems[$index]['quantity'];
        $unitPrice = $this->orderItems[$index]['unit_price'];
        $this->orderItems[$index]['total_price'] = $quantity * $unitPrice;
        $this->calculateTotals();
    }

    public function calculateTotals(): void
    {
        $this->subtotal = array_sum(array_column($this->orderItems, 'total_price'));
        $this->taxAmount = $this->subtotal * ($this->taxRate / 100);
        $this->total = $this->subtotal + $this->taxAmount;
    }

    public function updatedTaxRate(): void
    {
        $this->calculateTotals();
    }

    public function fillCustomerData(): void
    {
        if ($this->form['customer_type'] === 'existing' && $this->form['customer_id']) {
            $customer = User::find($this->form['customer_id']);
            if ($customer) {
                $this->form['customer_email'] = $customer->email;
                $this->form['customer_name'] = $customer->name;
                $this->form['billing_address']['first_name'] = explode(' ', $customer->name)[0] ?? '';
                $this->form['billing_address']['last_name'] = explode(' ', $customer->name, 2)[1] ?? '';
            }
        }
    }

    public function createOrder(): void
    {
        $this->validate([
            'form.customer_email' => 'required|email',
            'form.customer_name' => 'required|string|max:255',
            'form.billing_address.first_name' => 'required|string|max:255',
            'form.billing_address.last_name' => 'required|string|max:255',
            'form.billing_address.address_line_1' => 'required|string|max:255',
            'form.billing_address.city' => 'required|string|max:255',
            'form.billing_address.state' => 'required|string|max:255',
            'form.billing_address.postal_code' => 'required|string|max:20',
            'orderItems' => 'required|array|min:1',
            'orderItems.*.product_id' => 'required|exists:products,id',
            'orderItems.*.warehouse_id' => 'required|exists:warehouses,id',
            'orderItems.*.quantity' => 'required|integer|min:1',
        ]);

        DB::transaction(function () {
            // Check if customer exists or create new one
            $customer = null;
            if ($this->form['customer_type'] === 'existing' && $this->form['customer_id']) {
                $customer = User::find($this->form['customer_id']);
            } else {
                // For new customers, we'll just store the email and name in the order
            }

            // Create the order
            $this->createOrderWithData($customer);
        });
    }

    private function createOrderWithData($customer): void
    {
        // Create the order
        $order = ProductOrder::create([
            'customer_id' => $customer?->id,
            'guest_email' => $customer ? null : $this->form['customer_email'],
            'order_number' => 'ORD-' . strtoupper(uniqid()),
            'status' => $this->form['order_status'],
            'currency' => 'MYR',
            'subtotal' => $this->subtotal,
            'tax_amount' => $this->taxAmount,
            'total_amount' => $this->total,
            'customer_notes' => $this->form['notes'],
            'order_date' => now(),
        ]);

        // Create order items
        foreach ($this->orderItems as $item) {
            if (!empty($item['product_id'])) {
                $product = Product::find($item['product_id']);

                ProductOrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'product_variant_id' => $item['product_variant_id'],
                    'warehouse_id' => $item['warehouse_id'],
                    'product_name' => $product->name,
                    'variant_name' => null,
                    'sku' => $product->sku,
                    'quantity_ordered' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $item['total_price'],
                    'unit_cost' => $product->cost_price ?? 0,
                    'product_snapshot' => $product->toArray(),
                ]);
            }
        }

        // Create billing address
        $order->addresses()->create([
            'type' => 'billing',
            'first_name' => $this->form['billing_address']['first_name'],
            'last_name' => $this->form['billing_address']['last_name'],
            'company' => $this->form['billing_address']['company'],
            'email' => $this->form['customer_email'],
            'phone' => $this->form['customer_phone'],
            'address_line_1' => $this->form['billing_address']['address_line_1'],
            'address_line_2' => $this->form['billing_address']['address_line_2'],
            'city' => $this->form['billing_address']['city'],
            'state' => $this->form['billing_address']['state'],
            'postal_code' => $this->form['billing_address']['postal_code'],
            'country' => $this->form['billing_address']['country'],
        ]);

        // Create shipping address
        $shippingData = $this->form['same_as_billing']
            ? $this->form['billing_address']
            : $this->form['shipping_address'];

        $order->addresses()->create([
            'type' => 'shipping',
            'first_name' => $shippingData['first_name'],
            'last_name' => $shippingData['last_name'],
            'company' => $shippingData['company'],
            'email' => $this->form['customer_email'],
            'phone' => $this->form['customer_phone'],
            'address_line_1' => $shippingData['address_line_1'],
            'address_line_2' => $shippingData['address_line_2'],
            'city' => $shippingData['city'],
            'state' => $shippingData['state'],
            'postal_code' => $shippingData['postal_code'],
            'country' => $shippingData['country'],
        ]);

        // Create payment record
        $order->payments()->create([
            'payment_method' => $this->form['payment_method'],
            'amount' => $this->total,
            'currency' => 'MYR',
            'status' => $this->form['payment_status'],
            'paid_at' => $this->form['payment_status'] === 'completed' ? now() : null,
        ]);

        // Add system note
        $order->addSystemNote('Order created manually');

        session()->flash('success', 'Order created successfully!');
        $this->redirectRoute('admin.orders.show', $order);
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
            <flux:heading size="xl">Create Order</flux:heading>
            <flux:text class="mt-2">Create a new product order manually</flux:text>
        </div>
        <flux:button variant="outline" :href="route('admin.orders.index')" wire:navigate>
            <flux:icon name="arrow-left" class="w-4 h-4 mr-2" />
            Back to Orders
        </flux:button>
    </div>

    <form wire:submit="createOrder">
        <div class="grid lg:grid-cols-3 gap-6">
            <!-- Left Column - Order Details -->
            <div class="lg:col-span-2 space-y-6">

                <!-- Customer Information -->
                <div class="bg-white rounded-lg shadow-sm border p-6">
                    <flux:heading size="lg" class="mb-4">Customer Information</flux:heading>

                    <div class="space-y-4">
                        <!-- Customer Type -->
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
                            <!-- Existing Customer Selection -->
                            <flux:field>
                                <flux:label>Select Customer</flux:label>
                                <flux:select wire:model.live="form.customer_id" wire:change="fillCustomerData">
                                    <option value="">Choose a customer...</option>
                                    @foreach($customers as $customer)
                                        <option value="{{ $customer->id }}">{{ $customer->name }} ({{ $customer->email }})</option>
                                    @endforeach
                                </flux:select>
                            </flux:field>
                        @endif

                        <!-- Customer Details -->
                        <div class="grid md:grid-cols-2 gap-4">
                            <flux:field>
                                <flux:label>Email</flux:label>
                                <flux:input wire:model="form.customer_email" type="email" placeholder="customer@example.com" />
                                <flux:error name="form.customer_email" />
                            </flux:field>

                            <flux:field>
                                <flux:label>Full Name</flux:label>
                                <flux:input wire:model="form.customer_name" placeholder="Customer Name" />
                                <flux:error name="form.customer_name" />
                            </flux:field>
                        </div>

                        <flux:field>
                            <flux:label>Phone (Optional)</flux:label>
                            <flux:input wire:model="form.customer_phone" placeholder="+60123456789" />
                        </flux:field>
                    </div>
                </div>

                <!-- Order Items -->
                <div class="bg-white rounded-lg shadow-sm border p-6">
                    <div class="flex items-center justify-between mb-4">
                        <flux:heading size="lg">Order Items</flux:heading>
                        <flux:button type="button" wire:click="addOrderItem" size="sm">
                            <flux:icon name="plus" class="w-4 h-4 mr-1" />
                            Add Item
                        </flux:button>
                    </div>

                    <div class="space-y-4">
                        @foreach($orderItems as $index => $item)
                            <div class="border rounded-lg p-4">
                                <div class="grid md:grid-cols-5 gap-4 items-end">
                                    <!-- Product Selection -->
                                    <flux:field>
                                        <flux:label>Product</flux:label>
                                        <flux:select wire:model.live="orderItems.{{ $index }}.product_id" wire:change="productSelected({{ $index }}, $event.target.value)">
                                            <option value="">Select Product...</option>
                                            @foreach($products as $product)
                                                <option value="{{ $product->id }}">{{ $product->name }} (RM {{ $product->base_price }})</option>
                                            @endforeach
                                        </flux:select>
                                        <flux:error name="orderItems.{{ $index }}.product_id" />
                                    </flux:field>

                                    <!-- Warehouse -->
                                    <flux:field>
                                        <flux:label>Warehouse</flux:label>
                                        <flux:select wire:model="orderItems.{{ $index }}.warehouse_id">
                                            <option value="">Select Warehouse...</option>
                                            @foreach($warehouses as $warehouse)
                                                <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                                            @endforeach
                                        </flux:select>
                                        <flux:error name="orderItems.{{ $index }}.warehouse_id" />
                                    </flux:field>

                                    <!-- Quantity -->
                                    <flux:field>
                                        <flux:label>Quantity</flux:label>
                                        <flux:input wire:model.live="orderItems.{{ $index }}.quantity"
                                                   wire:change="quantityUpdated({{ $index }})"
                                                   type="number" min="1" />
                                        <flux:error name="orderItems.{{ $index }}.quantity" />
                                    </flux:field>

                                    <!-- Unit Price -->
                                    <flux:field>
                                        <flux:label>Unit Price</flux:label>
                                        <flux:input wire:model.live="orderItems.{{ $index }}.unit_price"
                                                   type="number" step="0.01" readonly />
                                    </flux:field>

                                    <!-- Remove Button -->
                                    <div>
                                        @if(count($orderItems) > 1)
                                            <flux:button type="button" wire:click="removeOrderItem({{ $index }})"
                                                       variant="danger" size="sm">
                                                <flux:icon name="trash" class="w-4 h-4" />
                                            </flux:button>
                                        @endif
                                    </div>
                                </div>

                                @if($item['total_price'] > 0)
                                    <div class="mt-2 text-right">
                                        <flux:text>Total: MYR {{ number_format($item['total_price'], 2) }}</flux:text>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Billing Address -->
                <div class="bg-white rounded-lg shadow-sm border p-6">
                    <flux:heading size="lg" class="mb-4">Billing Address</flux:heading>

                    <div class="grid md:grid-cols-2 gap-4">
                        <flux:field>
                            <flux:label>First Name</flux:label>
                            <flux:input wire:model="form.billing_address.first_name" />
                            <flux:error name="form.billing_address.first_name" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Last Name</flux:label>
                            <flux:input wire:model="form.billing_address.last_name" />
                            <flux:error name="form.billing_address.last_name" />
                        </flux:field>
                    </div>

                    <flux:field>
                        <flux:label>Company (Optional)</flux:label>
                        <flux:input wire:model="form.billing_address.company" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Address</flux:label>
                        <flux:input wire:model="form.billing_address.address_line_1" placeholder="Street address" />
                        <flux:error name="form.billing_address.address_line_1" />
                    </flux:field>

                    <flux:field>
                        <flux:input wire:model="form.billing_address.address_line_2" placeholder="Apartment, suite, etc. (optional)" />
                    </flux:field>

                    <div class="grid md:grid-cols-3 gap-4">
                        <flux:field>
                            <flux:label>City</flux:label>
                            <flux:input wire:model="form.billing_address.city" />
                            <flux:error name="form.billing_address.city" />
                        </flux:field>

                        <flux:field>
                            <flux:label>State</flux:label>
                            <flux:input wire:model="form.billing_address.state" />
                            <flux:error name="form.billing_address.state" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Postal Code</flux:label>
                            <flux:input wire:model="form.billing_address.postal_code" />
                            <flux:error name="form.billing_address.postal_code" />
                        </flux:field>
                    </div>
                </div>

                <!-- Order Notes -->
                <div class="bg-white rounded-lg shadow-sm border p-6">
                    <flux:heading size="lg" class="mb-4">Order Notes</flux:heading>
                    <flux:field>
                        <flux:textarea wire:model="form.notes" rows="4" placeholder="Any special instructions or notes for this order..." />
                    </flux:field>
                </div>
            </div>

            <!-- Right Column - Order Summary -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-sm border p-6 sticky top-6">
                    <flux:heading size="lg" class="mb-4">Order Summary</flux:heading>

                    <!-- Order Status Settings -->
                    <div class="space-y-4 mb-6">
                        <flux:field>
                            <flux:label>Order Status</flux:label>
                            <flux:select wire:model="form.order_status">
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
                            <flux:select wire:model="form.payment_status">
                                <option value="pending">Pending</option>
                                <option value="completed">Completed</option>
                                <option value="failed">Failed</option>
                                <option value="refunded">Refunded</option>
                            </flux:select>
                        </flux:field>

                        <flux:field>
                            <flux:label>Payment Method</flux:label>
                            <flux:select wire:model="form.payment_method">
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

                    <!-- GST Configuration -->
                    <div class="border-t pt-4">
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
                    <div class="space-y-3 border-t pt-4">
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

                    <!-- Create Order Button -->
                    <div class="mt-6">
                        <flux:button type="submit" variant="primary" class="w-full">
                            Create Order
                        </flux:button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>