<?php

use App\Models\Funnel;
use App\Models\FunnelCart;
use App\Models\FunnelOrder;
use App\Models\FunnelSession;
use App\Models\FunnelStep;
use App\Services\BayarcashService;
use App\Services\SettingsService;
use Livewire\Volt\Component;

new class extends Component
{
    public Funnel $funnel;

    public FunnelStep $step;

    public ?FunnelSession $funnelSession = null;

    public ?FunnelCart $cart = null;

    public array $selectedProducts = [];

    public array $selectedBumps = [];

    public string $email = '';

    public string $name = '';

    public string $phone = '';

    public array $billingAddress = [
        'address_line_1' => '',
        'city' => '',
        'state' => '',
        'postal_code' => '',
        'country' => 'Malaysia',
    ];

    public string $paymentMethod = '';

    public bool $isProcessing = false;

    public bool $showBillingAddress = false;

    public bool $isEmbedded = false;

    public array $availablePaymentMethods = [];

    public function mount(Funnel $funnel, FunnelStep $step, ?FunnelSession $session = null, bool $embedded = false): void
    {
        $this->funnel = $funnel;
        $this->step = $step->load(['products.product', 'products.course', 'orderBumps.product']);
        $this->funnelSession = $session;
        $this->isEmbedded = $embedded;

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
            $stripeLabel = $customLabels['stripe'] ?? 'Card';
            $methods[] = [
                'id' => 'credit_card',
                'name' => $stripeLabel,
                'description' => 'Visa, Mastercard',
                'icon' => 'credit-card',
                'color' => 'from-blue-600 to-blue-400',
            ];
        }

        // Add Bayarcash FPX if globally configured and enabled for this funnel
        if ($this->isBayarcashEnabled() && in_array('bayarcash_fpx', $enabledMethods)) {
            $fpxLabel = $customLabels['bayarcash_fpx'] ?? 'FPX';
            $methods[] = [
                'id' => 'fpx',
                'name' => $fpxLabel,
                'description' => 'Online Banking',
                'icon' => 'fpx',
                'color' => 'from-green-600 to-green-400',
            ];
        }

        // If only one method available or show_method_selector is false, just use default
        if (! $showMethodSelector && count($methods) > 1) {
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
            $this->email = $this->funnelSession->email ?? '';
            $this->phone = $this->funnelSession->phone ?? '';
        }

        if (auth()->check()) {
            $user = auth()->user();
            $this->email = $this->email ?: $user->email;
            $this->name = $user->name ?? '';
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
                'email' => $this->email ?: null,
                'phone' => $this->phone ?: null,
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

    public function processOrder(): void
    {
        $this->isProcessing = true;

        try {
            // Build dynamic validation for available payment methods
            $availableIds = collect($this->availablePaymentMethods)->pluck('id')->implode(',');

            // Validate required fields
            $rules = [
                'email' => 'nullable|email',
                'name' => 'required|min:2',
                'phone' => 'required|min:10',
                'paymentMethod' => 'required|in:'.($availableIds ?: 'credit_card'),
            ];

            // Add billing address validation if shown
            if ($this->showBillingAddress) {
                $rules['billingAddress.address_line_1'] = 'required|min:5';
                $rules['billingAddress.city'] = 'required|min:2';
                $rules['billingAddress.state'] = 'required|min:2';
                $rules['billingAddress.postal_code'] = 'required|min:5';
            }

            $this->validate($rules);

            if (empty($this->selectedProducts)) {
                $this->addError('products', 'Please select at least one product.');

                return;
            }

            // Prepare billing address
            $billingAddress = $this->showBillingAddress ? $this->billingAddress : [
                'first_name' => explode(' ', $this->name)[0] ?? $this->name,
                'last_name' => explode(' ', $this->name)[1] ?? '',
                'address_line_1' => '',
                'city' => '',
                'state' => '',
                'postal_code' => '',
                'country' => 'Malaysia',
            ];

            // Update session with contact info
            if ($this->funnelSession) {
                $this->funnelSession->update([
                    'email' => $this->email,
                    'phone' => $this->phone ?? null,
                ]);
            }

            // Update cart with contact info
            if ($this->cart) {
                $this->cart->update([
                    'email' => $this->email,
                    'phone' => $this->phone ?? null,
                ]);
            }

            // Create ProductOrder
            $productOrder = \App\Models\ProductOrder::create([
                'order_number' => \App\Models\ProductOrder::generateOrderNumber(),
                'user_id' => auth()->id(),
                'student_id' => auth()->user()?->student?->id,
                'guest_email' => $this->email,
                'customer_name' => $this->name,
                'customer_phone' => $this->phone,
                'billing_address' => $billingAddress,
                'shipping_address' => $billingAddress,
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
                    'embedded' => $this->isEmbedded,
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
            FunnelOrder::create([
                'funnel_id' => $this->funnel->id,
                'session_id' => $this->funnelSession?->id,
                'product_order_id' => $productOrder->id,
                'step_id' => $this->step->id,
                'order_type' => 'main',
                'funnel_revenue' => $this->calculateTotal(),
                'bumps_offered' => $this->step->orderBumps->count(),
                'bumps_accepted' => count($this->selectedBumps),
            ]);

            // Mark cart as recovered if it was abandoned
            if ($this->cart) {
                $this->cart->markAsRecovered($productOrder);
            }

            // Track form submission event (for affiliate checkout fill stats)
            $this->funnelSession?->trackEvent('form_submit', [
                'order_id' => $productOrder->id,
                'order_number' => $productOrder->order_number,
            ], $this->step);

            // Track checkout initiated event
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

            // For Stripe payments (credit_card) - mark session as converted
            $this->funnelSession?->markAsConverted();

            // Track conversion event
            $this->funnelSession?->trackEvent('purchase_completed', [
                'order_id' => $productOrder->id,
                'order_number' => $productOrder->order_number,
                'total' => $this->calculateTotal(),
                'embedded' => $this->isEmbedded,
            ], $this->step);

            // Update analytics - step level
            $stepAnalytics = \App\Models\FunnelAnalytics::getOrCreateForToday($this->funnel->id, $this->step->id);
            $stepAnalytics->incrementConversions($this->calculateTotal());

            // Update analytics - funnel level (for summary stats)
            $funnelAnalytics = \App\Models\FunnelAnalytics::getOrCreateForToday($this->funnel->id);
            $funnelAnalytics->incrementConversions($this->calculateTotal());

            // Dispatch browser event for payment processing and embed communication
            $this->dispatch('funnel-order-created', [
                'orderId' => $productOrder->id,
                'orderNumber' => $productOrder->order_number,
                'paymentMethod' => $this->paymentMethod,
                'total' => $this->calculateTotal(),
                'embedded' => $this->isEmbedded,
            ]);

            // For embedded forms, send message to parent window
            if ($this->isEmbedded) {
                $this->dispatch('embed-checkout-complete', [
                    'orderId' => $productOrder->id,
                    'orderNumber' => $productOrder->order_number,
                    'total' => $this->calculateTotal(),
                ]);
            } else {
                // Get next step URL
                $nextStep = $this->step->next_step_id
                    ? $this->funnel->steps()->find($this->step->next_step_id)
                    : $this->funnel->steps()->where('sort_order', '>', $this->step->sort_order)->first();

                if ($nextStep) {
                    $this->redirect("/f/{$this->funnel->slug}/{$nextStep->slug}");
                } else {
                    session()->flash('order_completed', true);
                    session()->flash('order_number', $productOrder->order_number);
                    $this->redirect("/f/{$this->funnel->slug}?complete=1&order={$productOrder->order_number}");
                }
            }

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
                'payer_name' => $this->name,
                'payer_email' => $this->email ?: '',
                'payer_phone' => $this->phone,
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
}; ?>

<div class="embed-checkout-form {{ $isEmbedded ? 'embedded' : '' }}" x-data="{ showDetails: false }">
    {{-- Modern Single-Page Checkout Form --}}
    <div class="max-w-lg mx-auto">
        {{-- Header --}}
        <div class="text-center mb-6">
            <h2 class="text-2xl font-bold text-gray-900">Complete Your Purchase</h2>
            <p class="text-gray-500 mt-1">Secure checkout powered by Stripe</p>
        </div>

        {{-- Product Selection --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden mb-4">
            <div class="px-5 py-4 bg-gray-50 border-b border-gray-200">
                <h3 class="font-semibold text-gray-900">Select Package</h3>
            </div>

            <div class="p-4 space-y-3">
                @error('products')
                    <div class="p-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm">
                        {{ $message }}
                    </div>
                @enderror

                @foreach($step->products as $product)
                    <div
                        wire:click="toggleProduct({{ $product->id }})"
                        class="relative border-2 rounded-xl p-4 cursor-pointer transition-all duration-200 {{ isset($selectedProducts[$product->id]) ? 'border-blue-500 bg-blue-50/50' : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50' }}"
                    >
                        {{-- Selection indicator --}}
                        <div class="absolute top-4 right-4">
                            <div class="w-6 h-6 rounded-full {{ isset($selectedProducts[$product->id]) ? 'bg-blue-500' : 'bg-gray-200' }} flex items-center justify-center transition-colors">
                                @if(isset($selectedProducts[$product->id]))
                                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                                    </svg>
                                @endif
                            </div>
                        </div>

                        <div class="pr-10">
                            <div class="flex items-start justify-between">
                                <div>
                                    <h4 class="font-semibold text-gray-900">{{ $product->name }}</h4>
                                    @if($product->description)
                                        <p class="text-sm text-gray-500 mt-1 line-clamp-2">{{ $product->description }}</p>
                                    @endif
                                </div>
                            </div>

                            <div class="mt-3 flex items-baseline gap-2">
                                <span class="text-2xl font-bold text-gray-900">RM {{ number_format($product->funnel_price, 2) }}</span>
                                @if($product->compare_at_price && $product->compare_at_price > $product->funnel_price)
                                    <span class="text-sm text-gray-400 line-through">RM {{ number_format($product->compare_at_price, 2) }}</span>
                                    <span class="text-xs font-semibold text-green-600 bg-green-100 px-2 py-0.5 rounded-full">
                                        SAVE {{ round((1 - $product->funnel_price / $product->compare_at_price) * 100) }}%
                                    </span>
                                @endif
                            </div>

                            @if($product->is_recurring)
                                <span class="inline-block mt-2 px-2 py-1 bg-purple-100 text-purple-700 text-xs font-medium rounded-full">
                                    {{ ucfirst($product->billing_interval) }} subscription
                                </span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Order Bumps --}}
        @if($step->orderBumps->isNotEmpty())
            <div class="bg-gradient-to-r from-amber-50 to-orange-50 rounded-2xl border-2 border-dashed border-amber-300 overflow-hidden mb-4">
                <div class="px-5 py-3 bg-amber-100/50">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center justify-center w-5 h-5 bg-amber-500 text-white text-xs font-bold rounded">!</span>
                        <span class="font-semibold text-amber-800">Special One-Time Offer</span>
                    </div>
                </div>

                <div class="p-4 space-y-3">
                    @foreach($step->orderBumps as $bump)
                        <div
                            wire:click="toggleBump({{ $bump->id }})"
                            class="bg-white border-2 rounded-xl p-4 cursor-pointer transition-all duration-200 {{ isset($selectedBumps[$bump->id]) ? 'border-green-500 shadow-md' : 'border-gray-200 hover:border-gray-300' }}"
                        >
                            <div class="flex items-start gap-3">
                                <div class="flex-shrink-0 mt-0.5">
                                    <div class="w-5 h-5 rounded border-2 {{ isset($selectedBumps[$bump->id]) ? 'bg-green-500 border-green-500' : 'border-gray-300' }} flex items-center justify-center transition-colors">
                                        @if(isset($selectedBumps[$bump->id]))
                                            <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                                            </svg>
                                        @endif
                                    </div>
                                </div>

                                <div class="flex-1 min-w-0">
                                    @if($bump->headline)
                                        <p class="text-xs font-bold text-green-600 uppercase tracking-wide">{{ $bump->headline }}</p>
                                    @endif
                                    <h4 class="font-semibold text-gray-900">{{ $bump->name }}</h4>
                                    @if($bump->description)
                                        <p class="text-sm text-gray-500 mt-0.5">{{ $bump->description }}</p>
                                    @endif
                                </div>

                                <div class="flex-shrink-0 text-right">
                                    <div class="font-bold text-green-600">+RM {{ number_format($bump->price, 2) }}</div>
                                    @if($bump->compare_at_price && $bump->compare_at_price > $bump->price)
                                        <div class="text-xs text-gray-400 line-through">RM {{ number_format($bump->compare_at_price, 2) }}</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Contact Information --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden mb-4">
            <div class="px-5 py-4 bg-gray-50 border-b border-gray-200">
                <h3 class="font-semibold text-gray-900">Your Information</h3>
            </div>

            <div class="p-5 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Phone number</label>
                    <input
                        type="tel"
                        wire:model.blur="phone"
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                        placeholder="+60 12 345 6789"
                    >
                    @error('phone') <span class="text-red-500 text-sm mt-1">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Full name</label>
                    <input
                        type="text"
                        wire:model.blur="name"
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                        placeholder="John Doe"
                    >
                    @error('name') <span class="text-red-500 text-sm mt-1">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Email address <span class="text-gray-400">(optional)</span></label>
                    <input
                        type="email"
                        wire:model.blur="email"
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                        placeholder="you@example.com"
                    >
                    @error('email') <span class="text-red-500 text-sm mt-1">{{ $message }}</span> @enderror
                </div>

                {{-- Billing Address Toggle --}}
                <div class="pt-2">
                    <button
                        type="button"
                        wire:click="$toggle('showBillingAddress')"
                        class="flex items-center text-sm text-gray-600 hover:text-gray-900 transition-colors"
                    >
                        <svg class="w-4 h-4 mr-1.5 transition-transform {{ $showBillingAddress ? 'rotate-90' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                        {{ $showBillingAddress ? 'Hide' : 'Add' }} billing address
                    </button>
                </div>

                {{-- Billing Address Fields --}}
                @if($showBillingAddress)
                    <div class="pt-4 border-t border-gray-100 space-y-4" wire:transition>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Street address</label>
                            <input
                                type="text"
                                wire:model.blur="billingAddress.address_line_1"
                                class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                placeholder="123 Main Street"
                            >
                            @error('billingAddress.address_line_1') <span class="text-red-500 text-sm mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">City</label>
                                <input
                                    type="text"
                                    wire:model.blur="billingAddress.city"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                    placeholder="Kuala Lumpur"
                                >
                                @error('billingAddress.city') <span class="text-red-500 text-sm mt-1">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">State</label>
                                <input
                                    type="text"
                                    wire:model.blur="billingAddress.state"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                    placeholder="Selangor"
                                >
                                @error('billingAddress.state') <span class="text-red-500 text-sm mt-1">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Postal code</label>
                            <input
                                type="text"
                                wire:model.blur="billingAddress.postal_code"
                                class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                placeholder="50000"
                            >
                            @error('billingAddress.postal_code') <span class="text-red-500 text-sm mt-1">{{ $message }}</span> @enderror
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Payment Method --}}
        @if(count($availablePaymentMethods) > 0)
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden mb-4">
            <div class="px-5 py-4 bg-gray-50 border-b border-gray-200">
                <h3 class="font-semibold text-gray-900">Payment Method</h3>
            </div>

            <div class="p-5">
                @error('payment')
                    <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-xl text-red-700 text-sm">
                        {{ $message }}
                    </div>
                @enderror

                <div class="grid grid-cols-{{ count($availablePaymentMethods) > 1 ? '2' : '1' }} gap-3">
                    @foreach($availablePaymentMethods as $method)
                        <button
                            type="button"
                            wire:click="$set('paymentMethod', '{{ $method['id'] }}')"
                            class="border-2 rounded-xl p-4 text-left transition-all duration-200 {{ $paymentMethod === $method['id'] ? 'border-blue-500 bg-blue-50/50' : 'border-gray-200 hover:border-gray-300' }}"
                        >
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-6 bg-gradient-to-r {{ $method['color'] }} rounded flex items-center justify-center">
                                    @if($method['icon'] === 'credit-card')
                                        <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2zm0 4v2h16V8H4zm0 6v2h4v-2H4zm6 0v2h4v-2h-4z"/>
                                        </svg>
                                    @elseif($method['icon'] === 'fpx')
                                        <span class="text-white text-xs font-bold">FPX</span>
                                    @else
                                        <span class="text-white text-xs font-bold">{{ strtoupper(substr($method['name'], 0, 1)) }}</span>
                                    @endif
                                </div>
                                <div>
                                    <div class="font-medium text-gray-900">{{ $method['name'] }}</div>
                                    <div class="text-xs text-gray-500">{{ $method['description'] }}</div>
                                </div>
                            </div>
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
        @else
        <div class="bg-yellow-50 rounded-2xl border border-yellow-200 p-5 mb-4">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <p class="text-sm text-yellow-700">No payment methods available. Please contact support.</p>
            </div>
        </div>
        @endif

        {{-- Order Summary --}}
        <div class="bg-gray-50 rounded-2xl border border-gray-200 overflow-hidden mb-6">
            <button
                type="button"
                @click="showDetails = !showDetails"
                class="w-full px-5 py-4 flex items-center justify-between text-left"
            >
                <span class="font-semibold text-gray-900">Order Summary</span>
                <div class="flex items-center gap-2">
                    <span class="font-bold text-gray-900">RM {{ number_format($this->calculateTotal(), 2) }}</span>
                    <svg class="w-5 h-5 text-gray-500 transition-transform" :class="{ 'rotate-180': showDetails }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </div>
            </button>

            <div x-show="showDetails" x-collapse class="px-5 pb-4 border-t border-gray-200">
                <div class="pt-4 space-y-2">
                    @foreach($step->products as $product)
                        @if(isset($selectedProducts[$product->id]))
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">{{ $product->name }}</span>
                                <span class="font-medium text-gray-900">RM {{ number_format($product->funnel_price, 2) }}</span>
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

                    <div class="pt-3 mt-3 border-t border-gray-200 flex justify-between">
                        <span class="font-semibold text-gray-900">Total</span>
                        <span class="font-bold text-gray-900">RM {{ number_format($this->calculateTotal(), 2) }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Submit Button --}}
        <button
            wire:click="processOrder"
            wire:loading.attr="disabled"
            wire:loading.class="opacity-75"
            class="w-full py-4 px-6 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white font-semibold rounded-xl transition-all duration-200 flex items-center justify-center gap-2 shadow-lg shadow-blue-600/25"
            {{ $isProcessing ? 'disabled' : '' }}
        >
            <span wire:loading.remove wire:target="processOrder">
                Pay RM {{ number_format($this->calculateTotal(), 2) }}
            </span>
            <span wire:loading wire:target="processOrder" class="flex items-center gap-2">
                <svg class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Processing...
            </span>
        </button>

        {{-- Trust Badges --}}
        <div class="mt-6 flex items-center justify-center gap-6 text-gray-400">
            <div class="flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                </svg>
                <span class="text-xs">SSL Secure</span>
            </div>

            <div class="flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <span class="text-xs">Money-back guarantee</span>
            </div>
        </div>

        {{-- Powered by --}}
        @if($isEmbedded)
            <div class="mt-6 text-center">
                <a href="{{ url('/') }}" target="_blank" class="text-xs text-gray-400 hover:text-gray-500 transition-colors">
                    Powered by {{ config('app.name') }}
                </a>
            </div>
        @endif
    </div>
</div>

@if($isEmbedded)
<script>
    document.addEventListener('livewire:init', function () {
        Livewire.on('embed-checkout-complete', (event) => {
            // Send message to parent window
            if (window.parent !== window) {
                window.parent.postMessage({
                    type: 'funnel-checkout-complete',
                    data: event[0] || event
                }, '*');
            }
        });

        Livewire.on('funnel-order-created', (event) => {
            // Send order created message to parent
            if (window.parent !== window) {
                window.parent.postMessage({
                    type: 'funnel-order-created',
                    data: event[0] || event
                }, '*');
            }
        });
    });
</script>
@endif
