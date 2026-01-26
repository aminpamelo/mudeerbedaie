<?php

use App\Models\Funnel;
use App\Models\FunnelCart;
use App\Models\FunnelOrder;
use App\Models\FunnelSession;
use App\Models\FunnelStep;
use App\Services\BayarcashService;
use App\Services\SettingsService;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component
{
    public Funnel $funnel;

    public FunnelStep $step;

    public ?FunnelSession $funnelSession = null;

    public ?FunnelCart $cart = null;

    public array $selectedProducts = [];

    public array $selectedBumps = [];

    public array $customerData = [
        'email' => '',
        'name' => '',
        'phone' => '',
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
    ];

    public string $paymentMethod = '';

    public string $currentStep = 'cart'; // cart, information, payment

    public bool $isProcessing = false;

    public array $availablePaymentMethods = [];

    public function mount(Funnel $funnel, FunnelStep $step, ?FunnelSession $session = null): void
    {
        $this->funnel = $funnel;
        $this->step = $step->load(['products.product', 'products.course', 'orderBumps.product']);
        $this->funnelSession = $session;

        $this->loadCart();
        $this->prefillFromSession();
        $this->loadAvailablePaymentMethods();

        // Pre-select first product if none selected
        if (empty($this->selectedProducts) && $this->step->products->isNotEmpty()) {
            $firstProduct = $this->step->products->first();
            $this->selectedProducts[$firstProduct->id] = true;
        }

        // Set default payment method to first available
        if (empty($this->paymentMethod) && ! empty($this->availablePaymentMethods)) {
            $this->paymentMethod = $this->availablePaymentMethods[0]['id'];
        }
    }

    private function isBayarcashEnabled(): bool
    {
        return app(SettingsService::class)->isBayarcashEnabled();
    }

    private function isStripeEnabled(): bool
    {
        return app(SettingsService::class)->isStripeConfigured();
    }

    private function loadAvailablePaymentMethods(): void
    {
        $methods = [];

        // Get funnel-specific payment settings
        $paymentSettings = $this->funnel->payment_settings ?? [];
        $enabledMethods = $paymentSettings['enabled_methods'] ?? ['stripe', 'bayarcash_fpx'];
        $customLabels = $paymentSettings['custom_labels'] ?? [];
        $showMethodSelector = $paymentSettings['show_method_selector'] ?? true;
        $defaultMethod = $paymentSettings['default_method'] ?? 'stripe';

        // Add Stripe payment methods if globally configured and enabled for this funnel
        if ($this->isStripeEnabled() && in_array('stripe', $enabledMethods)) {
            $stripeLabel = $customLabels['stripe'] ?? 'Credit/Debit Card';
            $methods[] = [
                'id' => 'credit_card',
                'name' => $stripeLabel,
                'description' => 'Visa, Mastercard',
                'icon' => 'credit-card',
            ];
        }

        // Add Bayarcash FPX if globally configured and enabled for this funnel
        if ($this->isBayarcashEnabled() && in_array('bayarcash_fpx', $enabledMethods)) {
            $fpxLabel = $customLabels['bayarcash_fpx'] ?? 'FPX Online Banking';
            $methods[] = [
                'id' => 'fpx',
                'name' => $fpxLabel,
                'description' => 'Malaysian Banks',
                'icon' => 'building-library',
            ];
        }

        // If only one method available or show_method_selector is false, just use default
        if (! $showMethodSelector && count($methods) > 1) {
            // Find the default method
            $defaultId = $defaultMethod === 'stripe' ? 'credit_card' : 'fpx';
            $methods = array_filter($methods, fn ($m) => $m['id'] === $defaultId);
            $methods = array_values($methods);
        }

        // Reorder methods to put the default one first
        if (count($methods) > 1) {
            $defaultId = $defaultMethod === 'stripe' ? 'credit_card' : 'fpx';
            usort($methods, function ($a, $b) use ($defaultId) {
                if ($a['id'] === $defaultId) {
                    return -1;
                }
                if ($b['id'] === $defaultId) {
                    return 1;
                }

                return 0;
            });
        }

        $this->availablePaymentMethods = $methods;
    }

    public function loadCart(): void
    {
        if ($this->funnelSession) {
            $this->cart = $this->funnelSession->cart;

            if ($this->cart) {
                $cartData = $this->cart->cart_data ?? [];
                $this->selectedProducts = $cartData['products'] ?? [];
                $this->selectedBumps = $cartData['bumps'] ?? [];
            }
        }
    }

    public function prefillFromSession(): void
    {
        if ($this->funnelSession) {
            $this->customerData['email'] = $this->funnelSession->email ?? '';
            $this->customerData['phone'] = $this->funnelSession->phone ?? '';
        }

        if (auth()->check()) {
            $user = auth()->user();
            $this->customerData['email'] = $this->customerData['email'] ?: $user->email;
            $this->customerData['name'] = $user->name ?? '';
            $this->billingAddress['first_name'] = $user->name ?? '';
        }
    }

    public function toggleProduct(int $productId): void
    {
        if (isset($this->selectedProducts[$productId])) {
            unset($this->selectedProducts[$productId]);
        } else {
            $this->selectedProducts[$productId] = true;
        }

        $this->updateCart();
    }

    public function toggleBump(int $bumpId): void
    {
        if (isset($this->selectedBumps[$bumpId])) {
            unset($this->selectedBumps[$bumpId]);
        } else {
            $this->selectedBumps[$bumpId] = true;
        }

        $this->updateCart();
    }

    public function updateCart(): void
    {
        if (! $this->funnelSession) {
            return;
        }

        $cartData = [
            'products' => $this->selectedProducts,
            'bumps' => $this->selectedBumps,
        ];

        $totalAmount = $this->calculateTotal();

        if (! $this->cart) {
            $this->cart = FunnelCart::create([
                'funnel_id' => $this->funnel->id,
                'session_id' => $this->funnelSession->id,
                'step_id' => $this->step->id,
                'email' => $this->customerData['email'] ?: null,
                'phone' => $this->customerData['phone'] ?: null,
                'cart_data' => $cartData,
                'total_amount' => $totalAmount,
                'recovery_status' => 'pending',
            ]);
        } else {
            $this->cart->update([
                'cart_data' => $cartData,
                'total_amount' => $totalAmount,
            ]);
        }

        // Track cart update event
        $this->funnelSession->trackEvent('cart_updated', [
            'products' => array_keys($this->selectedProducts),
            'bumps' => array_keys($this->selectedBumps),
            'total' => $totalAmount,
        ], $this->step);
    }

    public function calculateSubtotal(): float
    {
        $subtotal = 0;

        foreach ($this->step->products as $product) {
            if (isset($this->selectedProducts[$product->id])) {
                $subtotal += (float) $product->funnel_price;
            }
        }

        return $subtotal;
    }

    public function calculateBumpsTotal(): float
    {
        $total = 0;

        foreach ($this->step->orderBumps as $bump) {
            if (isset($this->selectedBumps[$bump->id])) {
                $total += (float) $bump->price;
            }
        }

        return $total;
    }

    public function calculateTotal(): float
    {
        return $this->calculateSubtotal() + $this->calculateBumpsTotal();
    }

    public function proceedToInformation(): void
    {
        if (empty($this->selectedProducts)) {
            $this->addError('products', 'Please select at least one product.');

            return;
        }

        $this->updateCart();
        $this->currentStep = 'information';
    }

    public function proceedToPayment(): void
    {
        $this->validate([
            'customerData.email' => 'required|email',
            'customerData.name' => 'required|min:2',
            'billingAddress.first_name' => 'required|min:2',
            'billingAddress.last_name' => 'required|min:2',
            'billingAddress.address_line_1' => 'required|min:5',
            'billingAddress.city' => 'required|min:2',
            'billingAddress.state' => 'required|min:2',
            'billingAddress.postal_code' => 'required|min:5',
        ]);

        // Update session with contact info
        if ($this->funnelSession) {
            $this->funnelSession->update([
                'email' => $this->customerData['email'],
                'phone' => $this->customerData['phone'] ?? null,
            ]);
        }

        // Update cart with contact info
        if ($this->cart) {
            $this->cart->update([
                'email' => $this->customerData['email'],
                'phone' => $this->customerData['phone'] ?? null,
            ]);
        }

        // Track event
        $this->funnelSession?->trackEvent('checkout_info_completed', [
            'email' => $this->customerData['email'],
        ], $this->step);

        $this->currentStep = 'payment';
    }

    public function backToCart(): void
    {
        $this->currentStep = 'cart';
    }

    public function backToInformation(): void
    {
        $this->currentStep = 'information';
    }

    public function processOrder(): void
    {
        $this->isProcessing = true;

        try {
            // Validate payment method is available
            $availableMethodIds = collect($this->availablePaymentMethods)->pluck('id')->toArray();
            $this->validate([
                'paymentMethod' => 'required|in:'.implode(',', $availableMethodIds),
            ]);

            // Create the order using existing ProductOrder system
            $orderData = $this->prepareOrderData();

            // Create ProductOrder
            $productOrder = \App\Models\ProductOrder::create([
                'order_number' => \App\Models\ProductOrder::generateOrderNumber(),
                'user_id' => auth()->id(),
                'student_id' => auth()->user()?->student?->id,
                'guest_email' => $this->customerData['email'],
                'customer_name' => $this->customerData['name'],
                'customer_phone' => $this->customerData['phone'] ?? null,
                'billing_address' => $this->billingAddress,
                'shipping_address' => $this->billingAddress,
                'subtotal' => $this->calculateSubtotal(),
                'discount_amount' => 0,
                'tax_amount' => 0,
                'total_amount' => $this->calculateTotal(),
                'currency' => 'MYR',
                'status' => 'pending',
                'payment_status' => 'pending',
                'payment_method' => $this->paymentMethod,
                'source' => 'funnel',
                'source_reference' => $this->funnel->slug,
                'notes' => 'Funnel order: '.$this->funnel->name,
                'metadata' => [
                    'funnel_id' => $this->funnel->id,
                    'funnel_slug' => $this->funnel->slug,
                    'step_id' => $this->step->id,
                    'step_slug' => $this->step->slug,
                    'session_uuid' => $this->funnelSession?->uuid,
                ],
            ]);

            // Add order items
            foreach ($this->step->products as $product) {
                if (isset($this->selectedProducts[$product->id])) {
                    $productOrder->items()->create([
                        'product_id' => $product->product_id,
                        'product_name' => $product->name,
                        'quantity_ordered' => 1,
                        'unit_price' => $product->funnel_price,
                        'total_price' => $product->funnel_price,
                        'item_metadata' => [
                            'funnel_product_id' => $product->id,
                            'course_id' => $product->course_id,
                            'type' => $product->type,
                            'description' => $product->description,
                        ],
                    ]);
                }
            }

            // Add order bump items
            foreach ($this->step->orderBumps as $bump) {
                if (isset($this->selectedBumps[$bump->id])) {
                    $productOrder->items()->create([
                        'product_id' => $bump->product_id,
                        'product_name' => $bump->name,
                        'quantity_ordered' => 1,
                        'unit_price' => $bump->price,
                        'total_price' => $bump->price,
                        'item_metadata' => [
                            'order_bump_id' => $bump->id,
                            'is_order_bump' => true,
                            'description' => $bump->description,
                        ],
                    ]);
                }
            }

            // Create FunnelOrder to track this in funnel analytics
            $funnelOrder = FunnelOrder::create([
                'funnel_id' => $this->funnel->id,
                'session_id' => $this->funnelSession?->id,
                'product_order_id' => $productOrder->id,
                'step_id' => $this->step->id,
                'order_type' => 'main',
                'funnel_revenue' => $this->calculateTotal(),
                'bumps_offered' => $this->step->orderBumps->count(),
                'bumps_accepted' => count($this->selectedBumps),
            ]);

            // Track checkout initiated event (payment not yet completed)
            $this->funnelSession?->trackEvent('checkout_initiated', [
                'order_id' => $productOrder->id,
                'order_number' => $productOrder->order_number,
                'total' => $this->calculateTotal(),
                'payment_method' => $this->paymentMethod,
            ], $this->step);

            // Process payment based on method
            if ($this->paymentMethod === 'fpx' && $this->isBayarcashEnabled()) {
                $this->processBayarcashPayment($productOrder);

                return; // Redirect happens in processBayarcashPayment
            }

            // For Stripe payments (credit_card, debit_card)
            // Dispatch browser event for Stripe.js payment processing
            $this->dispatch('funnel-order-created', [
                'orderId' => $productOrder->id,
                'orderNumber' => $productOrder->order_number,
                'paymentMethod' => $this->paymentMethod,
                'total' => $this->calculateTotal(),
            ]);

            // For now, redirect to thank you (Stripe integration can be added later)
            $this->redirectToNextStep($productOrder);

        } catch (\Exception $e) {
            $this->addError('payment', $e->getMessage());
        } finally {
            $this->isProcessing = false;
        }
    }

    private function processBayarcashPayment(\App\Models\ProductOrder $order): void
    {
        try {
            $bayarcashService = app(BayarcashService::class);

            $paymentIntent = $bayarcashService->createPaymentIntent([
                'order_number' => $order->order_number,
                'amount' => $order->total_amount,
                'payer_name' => $this->customerData['name'],
                'payer_email' => $this->customerData['email'],
                'payer_phone' => $this->customerData['phone'] ?? '',
            ]);

            // Store Bayarcash transaction info
            $order->update([
                'metadata' => array_merge($order->metadata ?? [], [
                    'bayarcash_initiated' => true,
                ]),
            ]);

            // Track payment redirect event
            $this->funnelSession?->trackEvent('payment_redirect', [
                'order_id' => $order->id,
                'payment_method' => 'fpx',
                'provider' => 'bayarcash',
            ], $this->step);

            // Redirect to Bayarcash payment page
            $this->redirect($paymentIntent->url);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Bayarcash payment failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            $this->addError('payment', 'Failed to initiate payment: '.$e->getMessage());
            $this->isProcessing = false;
        }
    }

    private function redirectToNextStep(\App\Models\ProductOrder $productOrder): void
    {
        // Get next step URL
        $nextStep = $this->step->next_step_id
            ? $this->funnel->steps()->find($this->step->next_step_id)
            : $this->funnel->steps()->where('sort_order', '>', $this->step->sort_order)->first();

        if ($nextStep) {
            $this->redirect("/f/{$this->funnel->slug}/{$nextStep->slug}?order={$productOrder->order_number}");
        } else {
            // No next step - show thank you
            session()->flash('order_completed', true);
            session()->flash('order_number', $productOrder->order_number);
            $this->redirect("/f/{$this->funnel->slug}?complete=1&order={$productOrder->order_number}");
        }
    }

    protected function prepareOrderData(): array
    {
        return [
            'products' => collect($this->step->products)
                ->filter(fn ($p) => isset($this->selectedProducts[$p->id]))
                ->toArray(),
            'bumps' => collect($this->step->orderBumps)
                ->filter(fn ($b) => isset($this->selectedBumps[$b->id]))
                ->toArray(),
        ];
    }

    protected function updateConversionAnalytics(): void
    {
        // Update analytics - step level
        $stepAnalytics = \App\Models\FunnelAnalytics::getOrCreateForToday($this->funnel->id, $this->step->id);
        $stepAnalytics->incrementConversions($this->calculateTotal());

        // Update analytics - funnel level (for summary stats)
        $funnelAnalytics = \App\Models\FunnelAnalytics::getOrCreateForToday($this->funnel->id);
        $funnelAnalytics->incrementConversions($this->calculateTotal());
    }

    public function getSelectedProductsProperty()
    {
        return $this->step->products->filter(fn ($p) => isset($this->selectedProducts[$p->id]));
    }

    public function getSelectedBumpsProperty()
    {
        return $this->step->orderBumps->filter(fn ($b) => isset($this->selectedBumps[$b->id]));
    }
}; ?>

<div class="funnel-checkout">
    {{-- Progress Steps --}}
    <div class="mb-8">
        <div class="flex items-center justify-center space-x-4">
            <div class="flex items-center">
                <div class="w-8 h-8 rounded-full {{ $currentStep === 'cart' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-600' }} flex items-center justify-center font-semibold text-sm">
                    1
                </div>
                <span class="ml-2 text-sm {{ $currentStep === 'cart' ? 'font-semibold' : '' }}">Cart</span>
            </div>

            <div class="flex-1 h-px bg-gray-200 max-w-16"></div>

            <div class="flex items-center">
                <div class="w-8 h-8 rounded-full {{ $currentStep === 'information' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-600' }} flex items-center justify-center font-semibold text-sm">
                    2
                </div>
                <span class="ml-2 text-sm {{ $currentStep === 'information' ? 'font-semibold' : '' }}">Information</span>
            </div>

            <div class="flex-1 h-px bg-gray-200 max-w-16"></div>

            <div class="flex items-center">
                <div class="w-8 h-8 rounded-full {{ $currentStep === 'payment' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-600' }} flex items-center justify-center font-semibold text-sm">
                    3
                </div>
                <span class="ml-2 text-sm {{ $currentStep === 'payment' ? 'font-semibold' : '' }}">Payment</span>
            </div>
        </div>
    </div>

    @if($currentStep === 'cart')
        {{-- Cart Step --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2 space-y-6">
                {{-- Products Selection --}}
                <div class="bg-white rounded-lg shadow-sm border p-6">
                    <h3 class="text-lg font-semibold mb-4">Select Your Package</h3>

                    @error('products')
                        <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded text-red-700 text-sm">
                            {{ $message }}
                        </div>
                    @enderror

                    <div class="space-y-4">
                        @foreach($step->products as $product)
                            <div
                                wire:click="toggleProduct({{ $product->id }})"
                                class="border rounded-lg p-4 cursor-pointer transition-all {{ isset($selectedProducts[$product->id]) ? 'border-blue-600 bg-blue-50 ring-2 ring-blue-600' : 'border-gray-200 hover:border-gray-300' }}"
                            >
                                <div class="flex items-start">
                                    <div class="flex-shrink-0">
                                        <div class="w-5 h-5 rounded-full border-2 {{ isset($selectedProducts[$product->id]) ? 'border-blue-600 bg-blue-600' : 'border-gray-300' }} flex items-center justify-center">
                                            @if(isset($selectedProducts[$product->id]))
                                                <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                </svg>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="ml-4 flex-1">
                                        <div class="flex items-start justify-between">
                                            <div>
                                                <h4 class="font-semibold text-gray-900">{{ $product->name }}</h4>
                                                @if($product->description)
                                                    <p class="text-sm text-gray-600 mt-1">{{ $product->description }}</p>
                                                @endif
                                            </div>

                                            <div class="text-right">
                                                <div class="text-lg font-bold text-gray-900">
                                                    RM {{ number_format($product->funnel_price, 2) }}
                                                </div>
                                                @if($product->compare_at_price && $product->compare_at_price > $product->funnel_price)
                                                    <div class="text-sm text-gray-500 line-through">
                                                        RM {{ number_format($product->compare_at_price, 2) }}
                                                    </div>
                                                @endif
                                            </div>
                                        </div>

                                        @if($product->is_recurring)
                                            <span class="inline-block mt-2 px-2 py-1 bg-purple-100 text-purple-800 text-xs font-medium rounded">
                                                {{ ucfirst($product->billing_interval) }} Subscription
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Order Bumps --}}
                @if($step->orderBumps->isNotEmpty())
                    <div class="bg-yellow-50 border-2 border-yellow-400 border-dashed rounded-lg p-6">
                        <div class="flex items-center mb-4">
                            <span class="bg-yellow-400 text-yellow-900 text-xs font-bold px-2 py-1 rounded mr-2">WAIT!</span>
                            <h3 class="text-lg font-semibold text-yellow-900">Special One-Time Offers</h3>
                        </div>

                        <div class="space-y-4">
                            @foreach($step->orderBumps as $bump)
                                <div
                                    wire:click="toggleBump({{ $bump->id }})"
                                    class="bg-white border rounded-lg p-4 cursor-pointer transition-all {{ isset($selectedBumps[$bump->id]) ? 'border-green-600 ring-2 ring-green-600' : 'border-gray-200 hover:border-gray-300' }}"
                                >
                                    <div class="flex items-start">
                                        <div class="flex-shrink-0">
                                            <input
                                                type="checkbox"
                                                class="w-5 h-5 text-green-600 rounded focus:ring-green-500"
                                                {{ isset($selectedBumps[$bump->id]) ? 'checked' : '' }}
                                                readonly
                                            >
                                        </div>

                                        <div class="ml-4 flex-1">
                                            <div class="flex items-start justify-between">
                                                <div>
                                                    <span class="text-sm font-bold text-green-700 uppercase">
                                                        {{ $bump->headline ?? 'Add This To Your Order' }}
                                                    </span>
                                                    <h4 class="font-semibold text-gray-900 mt-1">{{ $bump->name }}</h4>
                                                    @if($bump->description)
                                                        <p class="text-sm text-gray-600 mt-1">{{ $bump->description }}</p>
                                                    @endif
                                                </div>

                                                <div class="text-right ml-4">
                                                    <div class="text-lg font-bold text-green-700">
                                                        +RM {{ number_format($bump->price, 2) }}
                                                    </div>
                                                    @if($bump->compare_at_price && $bump->compare_at_price > $bump->price)
                                                        <div class="text-sm text-gray-500 line-through">
                                                            RM {{ number_format($bump->compare_at_price, 2) }}
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- Order Summary Sidebar --}}
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-sm border p-6 sticky top-6">
                    <h3 class="text-lg font-semibold mb-4">Order Summary</h3>

                    <div class="space-y-3 mb-6">
                        @foreach($step->products as $product)
                            @if(isset($selectedProducts[$product->id]))
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">{{ $product->name }}</span>
                                    <span class="font-medium">RM {{ number_format($product->funnel_price, 2) }}</span>
                                </div>
                            @endif
                        @endforeach

                        @foreach($step->orderBumps as $bump)
                            @if(isset($selectedBumps[$bump->id]))
                                <div class="flex justify-between text-sm text-green-700">
                                    <span>+ {{ $bump->name }}</span>
                                    <span class="font-medium">RM {{ number_format($bump->price, 2) }}</span>
                                </div>
                            @endif
                        @endforeach
                    </div>

                    <div class="border-t pt-4">
                        <div class="flex justify-between mb-2">
                            <span class="text-gray-600">Subtotal</span>
                            <span class="font-medium">RM {{ number_format($this->calculateSubtotal(), 2) }}</span>
                        </div>

                        @if($this->calculateBumpsTotal() > 0)
                            <div class="flex justify-between mb-2 text-green-700">
                                <span>Order Bumps</span>
                                <span class="font-medium">RM {{ number_format($this->calculateBumpsTotal(), 2) }}</span>
                            </div>
                        @endif

                        <div class="flex justify-between text-lg font-bold border-t pt-2 mt-2">
                            <span>Total</span>
                            <span>RM {{ number_format($this->calculateTotal(), 2) }}</span>
                        </div>
                    </div>

                    <button
                        wire:click="proceedToInformation"
                        class="w-full mt-6 bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg transition-colors"
                    >
                        Continue to Checkout
                        <svg class="inline-block w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>

                    <div class="mt-4 flex items-center justify-center text-sm text-gray-500">
                        <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                        </svg>
                        Secure Checkout
                    </div>
                </div>
            </div>
        </div>

    @elseif($currentStep === 'information')
        {{-- Information Step --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow-sm border p-6">
                    <h3 class="text-lg font-semibold mb-6">Customer Information</h3>

                    <div class="space-y-6">
                        {{-- Contact Information --}}
                        <div>
                            <h4 class="font-medium text-gray-900 mb-4">Contact Details</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                                    <input
                                        type="email"
                                        wire:model="customerData.email"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                        placeholder="your@email.com"
                                    >
                                    @error('customerData.email') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
                                    <input
                                        type="text"
                                        wire:model="customerData.name"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                        placeholder="John Doe"
                                    >
                                    @error('customerData.name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                </div>

                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Phone (Optional)</label>
                                    <input
                                        type="text"
                                        wire:model="customerData.phone"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                        placeholder="+60123456789"
                                    >
                                </div>
                            </div>
                        </div>

                        {{-- Billing Address --}}
                        <div>
                            <h4 class="font-medium text-gray-900 mb-4">Billing Address</h4>
                            <div class="space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">First Name *</label>
                                        <input
                                            type="text"
                                            wire:model="billingAddress.first_name"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                        >
                                        @error('billingAddress.first_name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Last Name *</label>
                                        <input
                                            type="text"
                                            wire:model="billingAddress.last_name"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                        >
                                        @error('billingAddress.last_name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Address *</label>
                                    <input
                                        type="text"
                                        wire:model="billingAddress.address_line_1"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                        placeholder="Street address"
                                    >
                                    @error('billingAddress.address_line_1') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <input
                                        type="text"
                                        wire:model="billingAddress.address_line_2"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                        placeholder="Apartment, suite, etc. (optional)"
                                    >
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">City *</label>
                                        <input
                                            type="text"
                                            wire:model="billingAddress.city"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                        >
                                        @error('billingAddress.city') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">State *</label>
                                        <input
                                            type="text"
                                            wire:model="billingAddress.state"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                        >
                                        @error('billingAddress.state') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Postal Code *</label>
                                        <input
                                            type="text"
                                            wire:model="billingAddress.postal_code"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                        >
                                        @error('billingAddress.postal_code') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 flex justify-between">
                        <button
                            wire:click="backToCart"
                            class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors"
                        >
                            <svg class="inline-block w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                            Back
                        </button>

                        <button
                            wire:click="proceedToPayment"
                            class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-lg transition-colors"
                        >
                            Continue to Payment
                            <svg class="inline-block w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            {{-- Order Summary Sidebar --}}
            <div class="lg:col-span-1">
                @include('livewire.funnel.partials.order-summary', ['step' => $step, 'selectedProducts' => $selectedProducts, 'selectedBumps' => $selectedBumps])
            </div>
        </div>

    @elseif($currentStep === 'payment')
        {{-- Payment Step --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow-sm border p-6">
                    <h3 class="text-lg font-semibold mb-6">Payment Method</h3>

                    @error('payment')
                        <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded text-red-700 text-sm">
                            {{ $message }}
                        </div>
                    @enderror

                    @if(empty($availablePaymentMethods))
                        <div class="mb-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                            <div class="flex items-center text-yellow-800">
                                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                </svg>
                                <span class="text-sm font-medium">No payment methods are currently available. Please contact support.</span>
                            </div>
                        </div>
                    @else
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                            @foreach($availablePaymentMethods as $method)
                                <div
                                    wire:click="$set('paymentMethod', '{{ $method['id'] }}')"
                                    class="border rounded-lg p-4 cursor-pointer transition-all {{ $paymentMethod === $method['id'] ? 'border-blue-600 bg-blue-50 ring-2 ring-blue-600' : 'border-gray-200 hover:border-gray-300' }}"
                                >
                                    <div class="flex items-center">
                                        <input
                                            type="radio"
                                            name="payment"
                                            value="{{ $method['id'] }}"
                                            {{ $paymentMethod === $method['id'] ? 'checked' : '' }}
                                            class="mr-3"
                                            readonly
                                        >
                                        <div class="flex-1">
                                            <div class="font-medium">{{ $method['name'] }}</div>
                                            <div class="text-sm text-gray-500">{{ $method['description'] }}</div>
                                        </div>
                                        @if($method['id'] === 'fpx')
                                            <div class="ml-2">
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                    FPX
                                                </span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if(in_array($paymentMethod, ['credit_card', 'debit_card']))
                        <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg mb-6">
                            <div class="flex items-center text-blue-800">
                                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                </svg>
                                <span class="text-sm">You will be redirected to a secure payment gateway to complete your payment.</span>
                            </div>
                        </div>
                    @elseif($paymentMethod === 'fpx')
                        <div class="p-4 bg-green-50 border border-green-200 rounded-lg mb-6">
                            <div class="flex items-center text-green-800">
                                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4zm6 4a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/>
                                </svg>
                                <span class="text-sm">You will be redirected to your bank's secure online banking to complete the payment via FPX.</span>
                            </div>
                        </div>
                    @endif

                    <div class="flex justify-between">
                        <button
                            wire:click="backToInformation"
                            class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors"
                        >
                            <svg class="inline-block w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                            Back
                        </button>

                        <button
                            wire:click="processOrder"
                            wire:loading.attr="disabled"
                            wire:loading.class="opacity-75 cursor-not-allowed"
                            class="px-8 py-3 bg-green-600 hover:bg-green-700 text-white font-bold rounded-lg transition-colors flex items-center"
                            {{ $isProcessing ? 'disabled' : '' }}
                        >
                            <span wire:loading.remove wire:target="processOrder">
                                Complete Purchase - RM {{ number_format($this->calculateTotal(), 2) }}
                            </span>
                            <span wire:loading wire:target="processOrder">
                                Processing...
                            </span>
                        </button>
                    </div>
                </div>

                {{-- Trust Badges --}}
                <div class="mt-6 flex items-center justify-center space-x-6 text-gray-500">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-1" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                        </svg>
                        <span class="text-sm">SSL Secure</span>
                    </div>

                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-1" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <span class="text-sm">Money Back Guarantee</span>
                    </div>
                </div>
            </div>

            {{-- Order Summary Sidebar --}}
            <div class="lg:col-span-1">
                @include('livewire.funnel.partials.order-summary', ['step' => $step, 'selectedProducts' => $selectedProducts, 'selectedBumps' => $selectedBumps])
            </div>
        </div>
    @endif
</div>

<script>
    document.addEventListener('livewire:init', function () {
        Livewire.on('funnel-order-created', (event) => {
            console.log('Order created:', event);
            // Here you can integrate with Stripe.js or other payment processors
        });
    });
</script>
