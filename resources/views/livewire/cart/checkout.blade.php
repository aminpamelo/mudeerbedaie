<?php

use App\Models\ProductCart;
use App\Models\ProductOrder;
use App\Models\ProductOrderPayment;
use Livewire\Volt\Component;

new class extends Component
{
    public ?ProductCart $cart = null;
    public array $customerData = [
        'email' => '',
        'phone' => '',
        'notes' => '',
    ];
    public array $billingAddress = [
        'first_name' => '',
        'last_name' => '',
        'company' => '',
        'address_line_1' => '',
        'address_line_2' => '',
        'city' => '',
        'state' => '',
        'postal_code' => '',
        'country' => 'Malaysia',
        'phone' => '',
        'email' => '',
    ];
    public array $shippingAddress = [
        'first_name' => '',
        'last_name' => '',
        'company' => '',
        'address_line_1' => '',
        'address_line_2' => '',
        'city' => '',
        'state' => '',
        'postal_code' => '',
        'country' => 'Malaysia',
        'phone' => '',
        'email' => '',
        'delivery_instructions' => '',
    ];
    public bool $sameAsBilling = true;
    public string $paymentMethod = 'credit_card';
    public bool $isProcessing = false;
    public string $currentStep = 'information'; // information, payment, confirmation

    public function mount(): void
    {
        $this->loadCart();
        $this->prefillUserData();

        // Redirect if cart is empty
        if (!$this->cart || $this->cart->isEmpty()) {
            $this->redirectRoute('cart');
        }
    }

    public function loadCart(): void
    {
        if (auth()->check()) {
            $this->cart = ProductCart::where('user_id', auth()->id())
                ->with(['items.product', 'items.variant', 'items.warehouse'])
                ->first();
        } else {
            $this->cart = ProductCart::where('session_id', session()->getId())
                ->with(['items.product', 'items.variant', 'items.warehouse'])
                ->first();
        }
    }

    public function prefillUserData(): void
    {
        if (auth()->check()) {
            $user = auth()->user();
            $this->customerData['email'] = $user->email;
            $this->billingAddress['first_name'] = $user->name ?? '';
            $this->billingAddress['email'] = $user->email;
        }
    }

    public function updatedSameAsBilling(): void
    {
        if ($this->sameAsBilling) {
            $this->shippingAddress = array_merge($this->shippingAddress, $this->billingAddress);
        }
    }

    public function proceedToPayment(): void
    {
        // Validate customer information
        $this->validate([
            'customerData.email' => 'required|email',
            'billingAddress.first_name' => 'required|min:2',
            'billingAddress.last_name' => 'required|min:2',
            'billingAddress.address_line_1' => 'required|min:5',
            'billingAddress.city' => 'required|min:2',
            'billingAddress.state' => 'required|min:2',
            'billingAddress.postal_code' => 'required|min:5',
            'billingAddress.country' => 'required',
        ]);

        if (!$this->sameAsBilling) {
            $this->validate([
                'shippingAddress.first_name' => 'required|min:2',
                'shippingAddress.last_name' => 'required|min:2',
                'shippingAddress.address_line_1' => 'required|min:5',
                'shippingAddress.city' => 'required|min:2',
                'shippingAddress.state' => 'required|min:2',
                'shippingAddress.postal_code' => 'required|min:5',
                'shippingAddress.country' => 'required',
            ]);
        }

        $this->currentStep = 'payment';
    }

    public function backToInformation(): void
    {
        $this->currentStep = 'information';
    }

    public function processOrder(): void
    {
        $this->isProcessing = true;

        try {
            // Validate payment method
            $this->validate([
                'paymentMethod' => 'required|in:credit_card,debit_card,bank_transfer,cash,fpx,grabpay,boost',
            ]);

            // Final stock validation
            foreach ($this->cart->items as $item) {
                if ($item->variant) {
                    if (!$item->variant->checkStockAvailability($item->quantity, $item->warehouse_id)) {
                        throw new \Exception("Insufficient stock for {$item->getDisplayName()}");
                    }
                } else {
                    if (!$item->product->checkStockAvailability($item->quantity, $item->warehouse_id)) {
                        throw new \Exception("Insufficient stock for {$item->getDisplayName()}");
                    }
                }
            }

            // Prepare addresses
            $addresses = [
                'billing' => $this->billingAddress,
                'shipping' => $this->sameAsBilling ? $this->billingAddress : $this->shippingAddress,
            ];

            // Create order
            $order = ProductOrder::createFromCart(
                cart: $this->cart,
                customerData: $this->customerData,
                addresses: $addresses
            );

            // Create payment record
            $payment = $order->payments()->create([
                'payment_method' => $this->paymentMethod,
                'payment_provider' => $this->getPaymentProvider(),
                'amount' => $this->cart->total_amount,
                'currency' => $this->cart->currency,
                'status' => 'pending',
                'transaction_id' => $this->generateTransactionId(),
            ]);

            // For demo purposes, we'll mark cash payments as completed
            if ($this->paymentMethod === 'cash') {
                $payment->markAsPaid();
                $order->markAsConfirmed();
            }

            // Clear the cart
            $this->cart->clear();

            // Redirect to confirmation
            $this->currentStep = 'confirmation';
            session()->flash('order_id', $order->id);
            session()->flash('order_number', $order->order_number);

        } catch (\Exception $e) {
            $this->dispatch('checkout-error', message: $e->getMessage());
        } finally {
            $this->isProcessing = false;
        }
    }

    private function getPaymentProvider(): ?string
    {
        return match($this->paymentMethod) {
            'credit_card', 'debit_card' => 'stripe',
            'fpx' => 'fpx',
            'grabpay' => 'grabpay',
            'boost' => 'boost',
            default => null,
        };
    }

    private function generateTransactionId(): string
    {
        return 'TXN-' . date('Ymd') . '-' . strtoupper(\Illuminate\Support\Str::random(8));
    }

    public function getCartSubtotal(): string
    {
        return $this->cart ? number_format($this->cart->subtotal, 2) : '0.00';
    }

    public function getCartTax(): string
    {
        return $this->cart ? number_format($this->cart->tax_amount, 2) : '0.00';
    }

    public function getCartTotal(): string
    {
        return $this->cart ? number_format($this->cart->total_amount, 2) : '0.00';
    }
}; ?>

<div class="max-w-6xl mx-auto py-8">
    @if($currentStep === 'confirmation')
        <!-- Order Confirmation -->
        <div class="text-center py-12">
            <div class="mx-auto w-24 h-24 bg-green-100 rounded-full flex items-center justify-center mb-6">
                <flux:icon name="check" class="w-12 h-12 text-green-600" />
            </div>

            <flux:heading size="xl" class="mb-4">Order Confirmed!</flux:heading>
            <flux:text class="text-gray-600 mb-2">Thank you for your order</flux:text>
            <flux:text class="font-semibold text-lg">Order #{{ session('order_number') }}</flux:text>

            <div class="mt-8 space-y-4">
                <flux:button variant="primary" href="{{ route('products.index') }}">
                    Continue Shopping
                </flux:button>

                @auth
                    <flux:button variant="outline" href="{{ route('student.orders') }}">
                        View Order History
                    </flux:button>
                @endauth
            </div>
        </div>
    @else
        <div class="mb-8">
            <flux:heading size="xl">Checkout</flux:heading>
            <flux:text class="mt-2">Complete your order</flux:text>
        </div>

        <!-- Progress Steps -->
        <div class="mb-8">
            <div class="flex items-center justify-center space-x-8">
                <div class="flex items-center">
                    <div class="w-8 h-8 rounded-full {{ $currentStep === 'information' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-600' }} flex items-center justify-center font-semibold">
                        1
                    </div>
                    <flux:text class="ml-2 {{ $currentStep === 'information' ? 'font-semibold' : '' }}">Information</flux:text>
                </div>

                <div class="flex-1 h-px bg-gray-200"></div>

                <div class="flex items-center">
                    <div class="w-8 h-8 rounded-full {{ $currentStep === 'payment' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-600' }} flex items-center justify-center font-semibold">
                        2
                    </div>
                    <flux:text class="ml-2 {{ $currentStep === 'payment' ? 'font-semibold' : '' }}">Payment</flux:text>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Main Content -->
            <div class="lg:col-span-2">
                @if($currentStep === 'information')
                    <!-- Customer Information Step -->
                    <div class="bg-white rounded-lg shadow-sm border p-6">
                        <flux:heading size="lg" class="mb-6">Customer Information</flux:heading>

                        <div class="space-y-6">
                            <!-- Contact Information -->
                            <div>
                                <flux:heading size="sm" class="mb-4">Contact Information</flux:heading>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <flux:field>
                                        <flux:label>Email</flux:label>
                                        <flux:input wire:model="customerData.email" type="email" placeholder="john@example.com" />
                                        <flux:error name="customerData.email" />
                                    </flux:field>

                                    <flux:field>
                                        <flux:label>Phone (Optional)</flux:label>
                                        <flux:input wire:model="customerData.phone" placeholder="+60123456789" />
                                    </flux:field>
                                </div>
                            </div>

                            <!-- Billing Address -->
                            <div>
                                <flux:heading size="sm" class="mb-4">Billing Address</flux:heading>
                                <div class="space-y-4">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <flux:field>
                                            <flux:label>First Name</flux:label>
                                            <flux:input wire:model="billingAddress.first_name" />
                                            <flux:error name="billingAddress.first_name" />
                                        </flux:field>

                                        <flux:field>
                                            <flux:label>Last Name</flux:label>
                                            <flux:input wire:model="billingAddress.last_name" />
                                            <flux:error name="billingAddress.last_name" />
                                        </flux:field>
                                    </div>

                                    <flux:field>
                                        <flux:label>Company (Optional)</flux:label>
                                        <flux:input wire:model="billingAddress.company" />
                                    </flux:field>

                                    <flux:field>
                                        <flux:label>Address</flux:label>
                                        <flux:input wire:model="billingAddress.address_line_1" placeholder="Street address" />
                                        <flux:error name="billingAddress.address_line_1" />
                                    </flux:field>

                                    <flux:field>
                                        <flux:input wire:model="billingAddress.address_line_2" placeholder="Apartment, suite, etc. (optional)" />
                                    </flux:field>

                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <flux:field>
                                            <flux:label>City</flux:label>
                                            <flux:input wire:model="billingAddress.city" />
                                            <flux:error name="billingAddress.city" />
                                        </flux:field>

                                        <flux:field>
                                            <flux:label>State</flux:label>
                                            <flux:input wire:model="billingAddress.state" />
                                            <flux:error name="billingAddress.state" />
                                        </flux:field>

                                        <flux:field>
                                            <flux:label>Postal Code</flux:label>
                                            <flux:input wire:model="billingAddress.postal_code" />
                                            <flux:error name="billingAddress.postal_code" />
                                        </flux:field>
                                    </div>
                                </div>
                            </div>

                            <!-- Shipping Address -->
                            <div>
                                <div class="flex items-center justify-between mb-4">
                                    <flux:heading size="sm">Shipping Address</flux:heading>
                                    <flux:checkbox wire:model.live="sameAsBilling" label="Same as billing address" />
                                </div>

                                @if(!$sameAsBilling)
                                    <div class="space-y-4">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <flux:field>
                                                <flux:label>First Name</flux:label>
                                                <flux:input wire:model="shippingAddress.first_name" />
                                                <flux:error name="shippingAddress.first_name" />
                                            </flux:field>

                                            <flux:field>
                                                <flux:label>Last Name</flux:label>
                                                <flux:input wire:model="shippingAddress.last_name" />
                                                <flux:error name="shippingAddress.last_name" />
                                            </flux:field>
                                        </div>

                                        <flux:field>
                                            <flux:label>Company (Optional)</flux:label>
                                            <flux:input wire:model="shippingAddress.company" />
                                        </flux:field>

                                        <flux:field>
                                            <flux:label>Address</flux:label>
                                            <flux:input wire:model="shippingAddress.address_line_1" placeholder="Street address" />
                                            <flux:error name="shippingAddress.address_line_1" />
                                        </flux:field>

                                        <flux:field>
                                            <flux:input wire:model="shippingAddress.address_line_2" placeholder="Apartment, suite, etc. (optional)" />
                                        </flux:field>

                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                            <flux:field>
                                                <flux:label>City</flux:label>
                                                <flux:input wire:model="shippingAddress.city" />
                                                <flux:error name="shippingAddress.city" />
                                            </flux:field>

                                            <flux:field>
                                                <flux:label>State</flux:label>
                                                <flux:input wire:model="shippingAddress.state" />
                                                <flux:error name="shippingAddress.state" />
                                            </flux:field>

                                            <flux:field>
                                                <flux:label>Postal Code</flux:label>
                                                <flux:input wire:model="shippingAddress.postal_code" />
                                                <flux:error name="shippingAddress.postal_code" />
                                            </flux:field>
                                        </div>

                                        <flux:field>
                                            <flux:label>Delivery Instructions (Optional)</flux:label>
                                            <flux:textarea wire:model="shippingAddress.delivery_instructions" placeholder="Special delivery instructions..." />
                                        </flux:field>
                                    </div>
                                @endif
                            </div>

                            <!-- Order Notes -->
                            <flux:field>
                                <flux:label>Order Notes (Optional)</flux:label>
                                <flux:textarea wire:model="customerData.notes" placeholder="Any special requests or notes..." />
                            </flux:field>
                        </div>

                        <div class="mt-8 flex justify-between">
                            <flux:button variant="outline" href="{{ route('cart') }}">
                                <flux:icon name="arrow-left" class="w-4 h-4 mr-2" />
                                Back to Cart
                            </flux:button>

                            <flux:button variant="primary" wire:click="proceedToPayment">
                                Continue to Payment
                                <flux:icon name="arrow-right" class="w-4 h-4 ml-2" />
                            </flux:button>
                        </div>
                    </div>
                @elseif($currentStep === 'payment')
                    <!-- Payment Step -->
                    <div class="bg-white rounded-lg shadow-sm border p-6">
                        <flux:heading size="lg" class="mb-6">Payment Method</flux:heading>

                        <div class="space-y-4">
                            <!-- Payment Methods -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="border rounded-lg p-4 {{ $paymentMethod === 'credit_card' ? 'border-blue-600 bg-blue-50' : 'border-gray-200' }}">
                                    <flux:radio wire:model.live="paymentMethod" value="credit_card" label="Credit Card" />
                                    <flux:text size="sm" class="text-gray-600 mt-1">Visa, Mastercard</flux:text>
                                </div>

                                <div class="border rounded-lg p-4 {{ $paymentMethod === 'debit_card' ? 'border-blue-600 bg-blue-50' : 'border-gray-200' }}">
                                    <flux:radio wire:model.live="paymentMethod" value="debit_card" label="Debit Card" />
                                    <flux:text size="sm" class="text-gray-600 mt-1">Visa, Mastercard</flux:text>
                                </div>

                                <div class="border rounded-lg p-4 {{ $paymentMethod === 'fpx' ? 'border-blue-600 bg-blue-50' : 'border-gray-200' }}">
                                    <flux:radio wire:model.live="paymentMethod" value="fpx" label="FPX Online Banking" />
                                    <flux:text size="sm" class="text-gray-600 mt-1">Malaysian Banks</flux:text>
                                </div>

                                <div class="border rounded-lg p-4 {{ $paymentMethod === 'grabpay' ? 'border-blue-600 bg-blue-50' : 'border-gray-200' }}">
                                    <flux:radio wire:model.live="paymentMethod" value="grabpay" label="GrabPay" />
                                    <flux:text size="sm" class="text-gray-600 mt-1">Digital Wallet</flux:text>
                                </div>

                                <div class="border rounded-lg p-4 {{ $paymentMethod === 'boost' ? 'border-blue-600 bg-blue-50' : 'border-gray-200' }}">
                                    <flux:radio wire:model.live="paymentMethod" value="boost" label="Boost" />
                                    <flux:text size="sm" class="text-gray-600 mt-1">Digital Wallet</flux:text>
                                </div>

                                <div class="border rounded-lg p-4 {{ $paymentMethod === 'cash' ? 'border-blue-600 bg-blue-50' : 'border-gray-200' }}">
                                    <flux:radio wire:model.live="paymentMethod" value="cash" label="Cash on Delivery" />
                                    <flux:text size="sm" class="text-gray-600 mt-1">Pay when you receive</flux:text>
                                </div>
                            </div>

                            @if(in_array($paymentMethod, ['credit_card', 'debit_card']))
                                <div class="mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                                    <flux:text size="sm" class="text-yellow-800">
                                        <flux:icon name="information-circle" class="w-4 h-4 inline mr-1" />
                                        You will be redirected to a secure payment gateway to complete your payment.
                                    </flux:text>
                                </div>
                            @endif
                        </div>

                        <div class="mt-8 flex justify-between">
                            <flux:button variant="outline" wire:click="backToInformation">
                                <flux:icon name="arrow-left" class="w-4 h-4 mr-2" />
                                Back
                            </flux:button>

                            <flux:button
                                variant="primary"
                                wire:click="processOrder"
                                wire:loading.attr="disabled"
                                :disabled="$isProcessing"
                            >
                                <span wire:loading.remove wire:target="processOrder">Complete Order</span>
                                <span wire:loading wire:target="processOrder">Processing...</span>
                            </flux:button>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Order Summary -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-sm border p-6 sticky top-6">
                    <flux:heading size="lg" class="mb-4">Order Summary</flux:heading>

                    <!-- Cart Items -->
                    <div class="space-y-4 mb-6">
                        @foreach($cart->items as $item)
                            <div class="flex items-center space-x-3">
                                <div class="w-12 h-12 bg-gray-100 rounded flex items-center justify-center">
                                    <flux:icon name="photo" class="w-6 h-6 text-gray-400" />
                                </div>

                                <div class="flex-1 min-w-0">
                                    <flux:text size="sm" class="font-medium">{{ $item->getDisplayName() }}</flux:text>
                                    <flux:text size="xs" class="text-gray-600">Qty: {{ $item->quantity }}</flux:text>
                                </div>

                                <flux:text size="sm" class="font-semibold">MYR {{ number_format($item->total_price, 2) }}</flux:text>
                            </div>
                        @endforeach
                    </div>

                    <!-- Totals -->
                    <div class="border-t pt-4 space-y-2">
                        <div class="flex justify-between">
                            <flux:text>Subtotal</flux:text>
                            <flux:text>MYR {{ $this->getCartSubtotal() }}</flux:text>
                        </div>

                        <div class="flex justify-between">
                            <flux:text>Tax (GST 6%)</flux:text>
                            <flux:text>MYR {{ $this->getCartTax() }}</flux:text>
                        </div>

                        <div class="border-t pt-2">
                            <div class="flex justify-between">
                                <flux:text class="font-semibold text-lg">Total</flux:text>
                                <flux:text class="font-semibold text-lg">MYR {{ $this->getCartTotal() }}</flux:text>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

<script>
    document.addEventListener('livewire:init', function () {
        Livewire.on('checkout-error', (event) => {
            alert(event.message);
        });
    });
</script>