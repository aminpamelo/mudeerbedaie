<?php

use App\Models\ProductCart;
use App\Models\ProductOrder;
use App\Models\ProductOrderPayment;
use App\Services\BayarcashService;
use App\Services\SettingsService;
use App\Services\Shipping\ShippingManager;
use App\DTOs\Shipping\ShippingRateRequest;
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
    public string $currentStep = 'information'; // information, shipping, payment, confirmation

    // Shipping
    public string $selectedShippingProvider = '';
    public string $selectedShippingService = '';
    public float $selectedShippingCost = 0;
    public array $availableShippingRates = [];
    public bool $isLoadingRates = false;
    public bool $hasShippingProviders = false;

    public function mount(): void
    {
        $this->loadCart();
        $this->prefillUserData();

        // Check if any shipping providers are enabled
        $shippingManager = app(ShippingManager::class);
        $this->hasShippingProviders = count($shippingManager->getEnabledProviders()) > 0;

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

    public function proceedToShipping(): void
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

        // Skip shipping step if no providers enabled
        if (!$this->hasShippingProviders) {
            $this->currentStep = 'payment';
            return;
        }

        $this->currentStep = 'shipping';
        $this->fetchShippingRates();
    }

    public function fetchShippingRates(): void
    {
        $this->isLoadingRates = true;

        try {
            $shippingManager = app(ShippingManager::class);
            $senderDefaults = app(SettingsService::class)->getShippingSenderDefaults();
            $address = $this->sameAsBilling ? $this->billingAddress : $this->shippingAddress;

            $request = new ShippingRateRequest(
                originPostalCode: $senderDefaults['postal_code'] ?? '',
                originCity: $senderDefaults['city'] ?? '',
                originState: $senderDefaults['state'] ?? '',
                destinationPostalCode: $address['postal_code'] ?? '',
                destinationCity: $address['city'] ?? '',
                destinationState: $address['state'] ?? '',
                weightKg: $this->calculateTotalWeight(),
            );

            $rates = $shippingManager->getRatesFromAllProviders($request);

            $this->availableShippingRates = array_map(fn ($rate) => [
                'provider_slug' => $rate->providerSlug,
                'provider_name' => $rate->providerName,
                'service_name' => $rate->serviceName,
                'service_code' => $rate->serviceCode,
                'cost' => $rate->cost,
                'currency' => $rate->currency,
                'estimated_days' => $rate->estimatedDays,
            ], $rates);
        } catch (\Exception $e) {
            $this->availableShippingRates = [];
            $this->dispatch('checkout-error', message: 'Failed to load shipping rates: ' . $e->getMessage());
        } finally {
            $this->isLoadingRates = false;
        }
    }

    public function selectShippingRate(string $providerSlug, string $serviceCode, float $cost): void
    {
        $this->selectedShippingProvider = $providerSlug;
        $this->selectedShippingService = $serviceCode;
        $this->selectedShippingCost = $cost;
    }

    public function proceedToPayment(): void
    {
        // If shipping step is active, validate shipping selection
        if ($this->hasShippingProviders && empty($this->selectedShippingProvider)) {
            $this->dispatch('checkout-error', message: 'Please select a shipping method.');
            return;
        }

        $this->currentStep = 'payment';
    }

    public function backToInformation(): void
    {
        $this->currentStep = 'information';
    }

    public function backToShipping(): void
    {
        $this->currentStep = 'shipping';
    }

    private function calculateTotalWeight(): float
    {
        if (!$this->cart) {
            return 0.5;
        }

        $weight = 0;
        foreach ($this->cart->items as $item) {
            $itemWeight = $item->product->weight_kg ?? 0.5;
            $weight += $itemWeight * $item->quantity;
        }

        return max($weight, 0.5);
    }

    public function processOrder(): void
    {
        $this->isProcessing = true;

        try {
            // Validate payment method
            $this->validate([
                'paymentMethod' => 'required|in:credit_card,debit_card,bank_transfer,cod,fpx,grabpay,boost',
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

            // Update order with payment method and shipping info
            $orderUpdate = ['payment_method' => $this->paymentMethod];

            if ($this->selectedShippingProvider) {
                $orderUpdate['shipping_cost'] = $this->selectedShippingCost;
                $orderUpdate['shipping_provider'] = $this->selectedShippingProvider;
                $orderUpdate['delivery_option'] = $this->selectedShippingService;
                $orderUpdate['weight_kg'] = $this->calculateTotalWeight();
                $orderUpdate['total_amount'] = $order->subtotal + $this->selectedShippingCost + $order->tax_amount - $order->discount_amount;
            }

            $order->update($orderUpdate);

            // Create payment record
            $payment = $order->payments()->create([
                'payment_method' => $this->paymentMethod,
                'payment_provider' => $this->getPaymentProvider(),
                'amount' => $this->cart->total_amount,
                'currency' => $this->cart->currency,
                'status' => 'pending',
                'transaction_id' => $this->generateTransactionId(),
            ]);

            // Handle FPX payments via Bayarcash
            if ($this->paymentMethod === 'fpx' && $this->isBayarcashEnabled()) {
                $this->processBayarcashPayment($order);
                return; // Will redirect to Bayarcash
            }

            // COD: set order to processing, payment remains pending until delivery
            if ($this->paymentMethod === 'cod') {
                $order->markAsProcessing();
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

    /**
     * Check if Bayarcash is enabled for FPX payments.
     */
    private function isBayarcashEnabled(): bool
    {
        return app(SettingsService::class)->isBayarcashEnabled();
    }

    /**
     * Process payment via Bayarcash and redirect to payment page.
     */
    private function processBayarcashPayment(ProductOrder $order): void
    {
        $bayarcashService = app(BayarcashService::class);

        $payerName = trim($this->billingAddress['first_name'] . ' ' . $this->billingAddress['last_name']);
        $payerEmail = $this->customerData['email'];
        $payerPhone = $this->customerData['phone'] ?? '';

        $response = $bayarcashService->createPaymentIntent([
            'order_number' => $order->order_number,
            'amount' => $order->total_amount,
            'payer_name' => $payerName,
            'payer_email' => $payerEmail,
            'payer_phone' => $payerPhone,
        ]);

        // Clear the cart before redirecting
        $this->cart->clear();

        // Redirect to Bayarcash payment page
        $this->redirect($response->url);
    }

    private function getPaymentProvider(): ?string
    {
        return match($this->paymentMethod) {
            'credit_card', 'debit_card' => 'stripe',
            'fpx' => 'bayarcash',
            'grabpay' => 'grabpay',
            'boost' => 'boost',
            'cod' => 'cod',
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
        if (!$this->cart) {
            return '0.00';
        }

        return number_format($this->cart->total_amount + $this->selectedShippingCost, 2);
    }

    public function getShippingCostFormatted(): string
    {
        if ($this->selectedShippingCost > 0) {
            return number_format($this->selectedShippingCost, 2);
        }

        return '0.00';
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
                    <div class="w-8 h-8 rounded-full {{ $currentStep === 'information' ? 'bg-blue-600 text-white' : (in_array($currentStep, ['shipping', 'payment']) ? 'bg-green-600 text-white' : 'bg-gray-200 text-gray-600') }} flex items-center justify-center font-semibold">
                        1
                    </div>
                    <flux:text class="ml-2 {{ $currentStep === 'information' ? 'font-semibold' : '' }}">Information</flux:text>
                </div>

                <div class="flex-1 h-px bg-gray-200"></div>

                @if($hasShippingProviders)
                <div class="flex items-center">
                    <div class="w-8 h-8 rounded-full {{ $currentStep === 'shipping' ? 'bg-blue-600 text-white' : ($currentStep === 'payment' ? 'bg-green-600 text-white' : 'bg-gray-200 text-gray-600') }} flex items-center justify-center font-semibold">
                        2
                    </div>
                    <flux:text class="ml-2 {{ $currentStep === 'shipping' ? 'font-semibold' : '' }}">Shipping</flux:text>
                </div>

                <div class="flex-1 h-px bg-gray-200"></div>
                @endif

                <div class="flex items-center">
                    <div class="w-8 h-8 rounded-full {{ $currentStep === 'payment' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-600' }} flex items-center justify-center font-semibold">
                        {{ $hasShippingProviders ? '3' : '2' }}
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

                            <flux:button variant="primary" wire:click="proceedToShipping">
                                {{ $hasShippingProviders ? 'Continue to Shipping' : 'Continue to Payment' }}
                                <flux:icon name="arrow-right" class="w-4 h-4 ml-2" />
                            </flux:button>
                        </div>
                    </div>
                @elseif($currentStep === 'shipping')
                    <!-- Shipping Step -->
                    <div class="bg-white rounded-lg shadow-sm border p-6">
                        <flux:heading size="lg" class="mb-6">Shipping Method</flux:heading>

                        @if($isLoadingRates)
                            <div class="text-center py-8">
                                <div class="inline-flex items-center space-x-2 text-gray-500">
                                    <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span>Loading shipping rates...</span>
                                </div>
                            </div>
                        @elseif(empty($availableShippingRates))
                            <div class="text-center py-8">
                                <flux:icon name="truck" class="w-12 h-12 text-gray-300 mx-auto mb-4" />
                                <flux:text class="text-gray-500">No shipping rates available for your location.</flux:text>
                                <flux:text size="sm" class="text-gray-400 mt-1">Please check your address details and try again.</flux:text>
                                <flux:button variant="outline" wire:click="fetchShippingRates" class="mt-4" size="sm">
                                    Retry
                                </flux:button>
                            </div>
                        @else
                            <div class="space-y-3">
                                @foreach($availableShippingRates as $index => $rate)
                                    <div
                                        wire:click="selectShippingRate('{{ $rate['provider_slug'] }}', '{{ $rate['service_code'] }}', {{ $rate['cost'] }})"
                                        class="border rounded-lg p-4 cursor-pointer transition-colors {{ $selectedShippingProvider === $rate['provider_slug'] && $selectedShippingService === $rate['service_code'] ? 'border-blue-600 bg-blue-50' : 'border-gray-200 hover:border-gray-300' }}"
                                    >
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center space-x-3">
                                                <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center">
                                                    <flux:icon name="truck" class="w-5 h-5 text-gray-600" />
                                                </div>
                                                <div>
                                                    <flux:text class="font-medium">{{ $rate['service_name'] }}</flux:text>
                                                    <flux:text size="sm" class="text-gray-500">{{ $rate['provider_name'] }}</flux:text>
                                                </div>
                                            </div>
                                            <div class="text-right">
                                                <flux:text class="font-semibold">MYR {{ number_format($rate['cost'], 2) }}</flux:text>
                                                @if($rate['estimated_days'])
                                                    <flux:text size="sm" class="text-gray-500">~{{ $rate['estimated_days'] }} {{ $rate['estimated_days'] === 1 ? 'day' : 'days' }}</flux:text>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <div class="mt-8 flex justify-between">
                            <flux:button variant="outline" wire:click="backToInformation">
                                <div class="flex items-center justify-center">
                                    <flux:icon name="arrow-left" class="w-4 h-4 mr-1" />
                                    Back
                                </div>
                            </flux:button>

                            <flux:button
                                variant="primary"
                                wire:click="proceedToPayment"
                                :disabled="empty($selectedShippingProvider)"
                            >
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

                                @if(app(\App\Services\SettingsService::class)->isCodEnabled())
                                <div class="border rounded-lg p-4 {{ $paymentMethod === 'cod' ? 'border-blue-600 bg-blue-50' : 'border-gray-200' }}">
                                    <flux:radio wire:model.live="paymentMethod" value="cod" label="Cash on Delivery" />
                                    <flux:text size="sm" class="text-gray-600 mt-1">Pay when you receive</flux:text>
                                </div>
                                @endif
                            </div>

                            @if($paymentMethod === 'cod')
                                @php
                                    $codInstructions = app(\App\Services\SettingsService::class)->getCodInstructions();
                                @endphp
                                @if($codInstructions)
                                    <div class="mt-4 p-4 bg-amber-50 border border-amber-200 rounded-lg">
                                        <flux:text size="sm" class="text-amber-800">
                                            <flux:icon name="information-circle" class="w-4 h-4 inline mr-1" />
                                            {{ $codInstructions }}
                                        </flux:text>
                                    </div>
                                @endif
                            @endif

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
                            <flux:button variant="outline" wire:click="{{ $hasShippingProviders ? 'backToShipping' : 'backToInformation' }}">
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

                        <div class="flex justify-between">
                            <flux:text>Shipping</flux:text>
                            <flux:text>
                                @if($selectedShippingCost > 0)
                                    MYR {{ $this->getShippingCostFormatted() }}
                                @else
                                    <span class="text-gray-400">{{ $hasShippingProviders ? 'Calculated at next step' : 'Free' }}</span>
                                @endif
                            </flux:text>
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