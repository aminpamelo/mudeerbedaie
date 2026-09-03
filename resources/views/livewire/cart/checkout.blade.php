<?php

use App\Models\ProductCart;
use App\Models\ProductCartItem;
use App\Models\ProductOrder;
use App\Services\BayarcashService;
use App\Services\SettingsService;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.store')] class extends Component
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

    // Shipping (flat-rate by region)
    public string $selectedShippingProvider = '';

    public string $selectedShippingService = '';

    public float $selectedShippingCost = 0;

    public array $availableShippingRates = [];

    public bool $isLoadingRates = false;

    public bool $hasShippingProviders = true; // Recomputed in mount() from cart contents

    /** East Malaysia states for RM 14 rate. */
    private const EAST_MALAYSIA_STATES = ['sabah', 'sarawak', 'labuan'];

    public function mount(): void
    {
        $this->loadCart();
        $this->prefillUserData();

        // Redirect if cart is empty
        if (! $this->cart || $this->cart->isEmpty()) {
            $this->redirectRoute('cart');

            return;
        }

        // Skip shipping entirely when nothing in the cart needs physical delivery
        // (e.g. a system-subscription-only purchase). No shipping step, no fee.
        $this->hasShippingProviders = $this->cartRequiresShipping();
    }

    private function cartRequiresShipping(): bool
    {
        return (bool) $this->cart?->items->contains(fn (ProductCartItem $item): bool => $item->requiresShipping());
    }

    public function loadCart(): void
    {
        if (auth()->check()) {
            $this->cart = ProductCart::where('user_id', auth()->id())
                ->with(['items.product', 'items.variant', 'items.warehouse', 'items.package', 'items.course'])
                ->first();
        } else {
            $this->cart = ProductCart::where('session_id', session()->getId())
                ->with(['items.product', 'items.variant', 'items.warehouse', 'items.package', 'items.course'])
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
            'customerData.phone' => 'required|string|max:30',
            'customerData.email' => 'nullable|email',
            'billingAddress.first_name' => 'required|min:2',
            'billingAddress.last_name' => 'required|min:2',
            'billingAddress.address_line_1' => 'required|min:5',
            'billingAddress.city' => 'required|min:2',
            'billingAddress.state' => 'required|min:2',
            'billingAddress.postal_code' => 'required|min:5',
            'billingAddress.country' => 'required',
        ]);

        if (! $this->sameAsBilling) {
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
        if (! $this->hasShippingProviders) {
            $this->currentStep = 'payment';

            return;
        }

        $this->currentStep = 'shipping';
        $this->fetchShippingRates();
    }

    public function fetchShippingRates(): void
    {
        $address = $this->sameAsBilling ? $this->billingAddress : $this->shippingAddress;
        $state = strtolower(trim($address['state'] ?? ''));
        $isEastMalaysia = in_array($state, self::EAST_MALAYSIA_STATES);

        $this->availableShippingRates = [
            [
                'provider_slug' => 'flat_rate',
                'provider_name' => $isEastMalaysia ? 'Sabah & Sarawak' : 'Semenanjung Malaysia',
                'service_name' => 'Penghantaran Standard',
                'service_code' => $isEastMalaysia ? 'east_malaysia' : 'west_malaysia',
                'cost' => $isEastMalaysia ? 15.00 : 8.00,
                'currency' => 'MYR',
                'estimated_days' => $isEastMalaysia ? 5 : 3,
            ],
        ];

        // Auto-select the only rate
        $rate = $this->availableShippingRates[0];
        $this->selectedShippingProvider = $rate['provider_slug'];
        $this->selectedShippingService = $rate['service_code'];
        $this->selectedShippingCost = $rate['cost'];
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
        if (! $this->cart) {
            return 0.5;
        }

        $weight = 0;
        foreach ($this->cart->items as $item) {
            $itemWeight = $item->product?->weight_kg ?? 0.5;
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

            // Final stock validation (products only; packages/courses have no inventory)
            foreach ($this->cart->items as $item) {
                if ($item->isPackage() || $item->isCourse()) {
                    continue;
                }

                if ($item->variant) {
                    if (! $item->variant->checkStockAvailability($item->quantity, $item->warehouse_id)) {
                        throw new Exception("Insufficient stock for {$item->getDisplayName()}");
                    }
                } else {
                    if ($item->product && ! $item->product->checkStockAvailability($item->quantity, $item->warehouse_id)) {
                        throw new Exception("Insufficient stock for {$item->getDisplayName()}");
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
                $orderUpdate['total_amount'] = $order->subtotal + $this->selectedShippingCost - $order->discount_amount;
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

        } catch (Exception $e) {
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

        $payerName = trim($this->billingAddress['first_name'].' '.$this->billingAddress['last_name']);
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
        return match ($this->paymentMethod) {
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
        return 'TXN-'.date('Ymd').'-'.strtoupper(Str::random(8));
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
        if (! $this->cart) {
            return '0.00';
        }

        // Subtotal + shipping (no tax)
        return number_format($this->cart->subtotal + $this->selectedShippingCost, 2);
    }

    public function getShippingCostFormatted(): string
    {
        if ($this->selectedShippingCost > 0) {
            return number_format($this->selectedShippingCost, 2);
        }

        return '0.00';
    }
}; ?>
<div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8"
     x-data="{ err: '', errT: null }"
     x-on:checkout-error.window="err = $event.detail.message; clearTimeout(errT); errT = setTimeout(() => err = '', 5000)">

    @if($currentStep === 'confirmation')
        {{-- ===================== CONFIRMATION ===================== --}}
        <div class="mx-auto max-w-lg py-10 text-center">
            <div class="mx-auto flex h-24 w-24 items-center justify-center rounded-full bg-gradient-to-br from-emerald-400 to-teal-500 text-white shadow-xl shadow-emerald-500/30">
                <flux:icon name="check" class="h-12 w-12" />
            </div>
            <h1 class="font-display mt-6 text-2xl font-extrabold text-zinc-900 sm:text-3xl">{{ __('store.co_confirmed_title') }}</h1>
            <p class="mt-2 text-zinc-500">{{ __('store.co_confirmed_thanks') }}</p>
            <div class="mt-4 inline-flex items-center gap-2 rounded-2xl border border-zinc-200 bg-white px-5 py-3 shadow-sm">
                <span class="text-sm text-zinc-500">{{ __('store.co_order_label') }}</span>
                <span class="font-display text-lg font-extrabold text-violet-700">#{{ session('order_number') }}</span>
            </div>
            <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                <a href="{{ route('shop') }}" wire:navigate class="store-grad store-grad-hover inline-flex items-center gap-2 rounded-2xl px-6 py-3 text-sm font-bold text-white">
                    <flux:icon name="squares-2x2" class="h-5 w-5" />
                    {{ __('store.cart_continue') }}
                </a>
                @auth
                    <a href="{{ route('student.orders') }}" wire:navigate class="inline-flex items-center gap-2 rounded-2xl border border-zinc-200 bg-white px-6 py-3 text-sm font-bold text-zinc-700 shadow-sm transition hover:border-violet-300 hover:text-violet-700">
                        <flux:icon name="clipboard-document-list" class="h-5 w-5" />
                        {{ __('store.co_view_orders') }}
                    </a>
                @endauth
            </div>
        </div>
    @else
        {{-- Heading --}}
        <div class="mb-6 flex items-center gap-3">
            <span class="store-grad grid h-11 w-11 shrink-0 place-items-center rounded-2xl text-white shadow-lg shadow-fuchsia-500/25">
                <flux:icon name="lock-closed" class="h-5 w-5" />
            </span>
            <div>
                <h1 class="font-display text-2xl font-extrabold text-zinc-900 sm:text-3xl">{{ __('store.co_title') }}</h1>
                <p class="mt-0.5 text-sm text-zinc-500">{{ __('store.co_subtitle') }}</p>
            </div>
        </div>

        {{-- ===================== PROGRESS STEPS ===================== --}}
        @php
            $steps = [['key' => 'information', 'label' => __('store.co_step_information'), 'n' => 1]];
            if ($hasShippingProviders) {
                $steps[] = ['key' => 'shipping', 'label' => __('store.co_step_shipping'), 'n' => 2];
            }
            $steps[] = ['key' => 'payment', 'label' => __('store.co_step_payment'), 'n' => $hasShippingProviders ? 3 : 2];
            $order = ['information' => 1, 'shipping' => 2, 'payment' => 3, 'confirmation' => 4];
            $currentPos = $order[$currentStep] ?? 1;
        @endphp
        <div class="mb-8 rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm sm:p-5">
            <div class="flex items-center">
                @foreach($steps as $i => $step)
                    @php $done = $currentPos > $order[$step['key']]; $active = $currentStep === $step['key']; @endphp
                    <div class="flex items-center gap-2.5">
                        <span @class([
                            'grid h-9 w-9 shrink-0 place-items-center rounded-full text-sm font-bold transition',
                            'store-grad text-white shadow-md shadow-fuchsia-500/25' => $active,
                            'bg-emerald-500 text-white' => $done,
                            'bg-zinc-100 text-zinc-400' => ! $active && ! $done,
                        ])>
                            @if($done)<flux:icon name="check" class="h-4 w-4" />@else{{ $step['n'] }}@endif
                        </span>
                        <span @class([
                            'hidden text-sm font-semibold sm:block',
                            'store-grad-text' => $active,
                            'text-zinc-700' => $done,
                            'text-zinc-400' => ! $active && ! $done,
                        ])>{{ $step['label'] }}</span>
                    </div>
                    @if(! $loop->last)
                        <div class="mx-3 h-0.5 flex-1 rounded-full {{ $currentPos > $order[$step['key']] ? 'bg-emerald-400' : 'bg-zinc-100' }}"></div>
                    @endif
                @endforeach
            </div>
        </div>

        {{-- Inline error toast --}}
        <div x-show="err" x-cloak x-transition.opacity
             class="mb-4 flex items-center gap-2.5 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">
            <flux:icon name="exclamation-triangle" class="h-5 w-5 shrink-0 text-rose-500" />
            <span x-text="err"></span>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3 lg:gap-8">
            {{-- ===================== MAIN COLUMN ===================== --}}
            <div class="lg:col-span-2">
                @if($currentStep === 'information')
                    <div class="rounded-3xl border border-zinc-200 bg-white p-5 shadow-sm sm:p-6">
                        {{-- Contact --}}
                        <h2 class="font-display text-xs font-bold uppercase tracking-wider text-zinc-500">{{ __('store.co_contact') }}</h2>
                        <div class="mt-3 grid grid-cols-1 gap-4 md:grid-cols-2">
                            @include('livewire.cart.partials.co-field', ['model' => 'customerData.phone', 'label' => __('store.co_phone'), 'ph' => '+60123456789', 'req' => true, 'type' => 'tel', 'inputmode' => 'tel'])
                            @include('livewire.cart.partials.co-field', ['model' => 'customerData.email', 'label' => __('store.co_email'), 'ph' => 'john@example.com', 'type' => 'email', 'inputmode' => 'email'])
                        </div>

                        {{-- Billing --}}
                        <h2 class="font-display mt-7 text-xs font-bold uppercase tracking-wider text-zinc-500">{{ __('store.co_billing') }}</h2>
                        <div class="mt-3 space-y-4">
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                @include('livewire.cart.partials.co-field', ['model' => 'billingAddress.first_name', 'label' => __('store.co_first_name'), 'req' => true])
                                @include('livewire.cart.partials.co-field', ['model' => 'billingAddress.last_name', 'label' => __('store.co_last_name'), 'req' => true])
                            </div>
                            @include('livewire.cart.partials.co-field', ['model' => 'billingAddress.company', 'label' => __('store.co_company')])
                            @include('livewire.cart.partials.co-field', ['model' => 'billingAddress.address_line_1', 'label' => __('store.co_address'), 'ph' => __('store.co_address_ph'), 'req' => true])
                            @include('livewire.cart.partials.co-field', ['model' => 'billingAddress.address_line_2', 'ph' => __('store.co_address2_ph')])
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                @include('livewire.cart.partials.co-field', ['model' => 'billingAddress.city', 'label' => __('store.co_city'), 'req' => true])
                                @include('livewire.cart.partials.co-field', ['model' => 'billingAddress.state', 'label' => __('store.co_state'), 'req' => true])
                                @include('livewire.cart.partials.co-field', ['model' => 'billingAddress.postal_code', 'label' => __('store.co_postal'), 'req' => true, 'inputmode' => 'numeric'])
                            </div>
                        </div>

                        {{-- Shipping address --}}
                        <div class="mt-7 flex items-center justify-between">
                            <h2 class="font-display text-xs font-bold uppercase tracking-wider text-zinc-500">{{ __('store.co_shipping_addr') }}</h2>
                            <label class="flex cursor-pointer items-center gap-2 text-sm font-medium text-zinc-600">
                                <input type="checkbox" wire:model.live="sameAsBilling" class="h-4 w-4 rounded border-zinc-300 accent-violet-600" />
                                {{ __('store.co_same_billing') }}
                            </label>
                        </div>

                        @if(! $sameAsBilling)
                            <div class="mt-3 space-y-4">
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    @include('livewire.cart.partials.co-field', ['model' => 'shippingAddress.first_name', 'label' => __('store.co_first_name'), 'req' => true])
                                    @include('livewire.cart.partials.co-field', ['model' => 'shippingAddress.last_name', 'label' => __('store.co_last_name'), 'req' => true])
                                </div>
                                @include('livewire.cart.partials.co-field', ['model' => 'shippingAddress.company', 'label' => __('store.co_company')])
                                @include('livewire.cart.partials.co-field', ['model' => 'shippingAddress.address_line_1', 'label' => __('store.co_address'), 'ph' => __('store.co_address_ph'), 'req' => true])
                                @include('livewire.cart.partials.co-field', ['model' => 'shippingAddress.address_line_2', 'ph' => __('store.co_address2_ph')])
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                    @include('livewire.cart.partials.co-field', ['model' => 'shippingAddress.city', 'label' => __('store.co_city'), 'req' => true])
                                    @include('livewire.cart.partials.co-field', ['model' => 'shippingAddress.state', 'label' => __('store.co_state'), 'req' => true])
                                    @include('livewire.cart.partials.co-field', ['model' => 'shippingAddress.postal_code', 'label' => __('store.co_postal'), 'req' => true, 'inputmode' => 'numeric'])
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-sm font-semibold text-zinc-700">{{ __('store.co_delivery_notes') }}</label>
                                    <textarea wire:model="shippingAddress.delivery_instructions" rows="2" placeholder="{{ __('store.co_delivery_notes_ph') }}"
                                              class="w-full rounded-xl border border-zinc-200 bg-white px-4 py-2.5 text-sm text-zinc-900 placeholder-zinc-400 shadow-sm transition focus:border-violet-400 focus:outline-none focus:ring-2 focus:ring-violet-200/70"></textarea>
                                </div>
                            </div>
                        @endif

                        {{-- Order notes --}}
                        <div class="mt-7">
                            <label class="mb-1.5 block text-sm font-semibold text-zinc-700">{{ __('store.co_order_notes') }}</label>
                            <textarea wire:model="customerData.notes" rows="2" placeholder="{{ __('store.co_order_notes_ph') }}"
                                      class="w-full rounded-xl border border-zinc-200 bg-white px-4 py-2.5 text-sm text-zinc-900 placeholder-zinc-400 shadow-sm transition focus:border-violet-400 focus:outline-none focus:ring-2 focus:ring-violet-200/70"></textarea>
                        </div>

                        <div class="mt-8 flex items-center justify-between gap-3">
                            <a href="{{ route('cart') }}" wire:navigate class="inline-flex items-center gap-1.5 rounded-xl px-3 py-2.5 text-sm font-semibold text-zinc-500 transition hover:bg-zinc-50 hover:text-zinc-800">
                                <flux:icon name="arrow-left" class="h-4 w-4" />
                                {{ __('store.co_back_cart') }}
                            </a>
                            <button type="button" wire:click="proceedToShipping" wire:loading.attr="disabled"
                                    class="store-grad store-grad-hover inline-flex items-center gap-2 rounded-2xl px-6 py-3 text-sm font-bold text-white">
                                {{ $hasShippingProviders ? __('store.co_continue_shipping') : __('store.co_continue_payment') }}
                                <flux:icon name="arrow-right" class="h-5 w-5" />
                            </button>
                        </div>
                    </div>

                @elseif($currentStep === 'shipping')
                    <div class="rounded-3xl border border-zinc-200 bg-white p-5 shadow-sm sm:p-6">
                        <h2 class="font-display text-lg font-extrabold text-zinc-900">{{ __('store.co_shipping_method') }}</h2>

                        <div class="mt-4 space-y-3">
                            @foreach($availableShippingRates as $rate)
                                @php $sel = $selectedShippingProvider === $rate['provider_slug'] && $selectedShippingService === $rate['service_code']; @endphp
                                <div @class([
                                        'flex w-full items-center justify-between gap-3 rounded-2xl border p-4 text-left transition',
                                        'border-violet-500 bg-violet-50 ring-2 ring-violet-200' => $sel,
                                        'border-zinc-200' => ! $sel,
                                    ])>
                                    <span class="flex items-center gap-3">
                                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl store-grad text-white">
                                            <flux:icon name="truck" class="h-5 w-5" />
                                        </span>
                                        <span>
                                            <span class="block text-sm font-bold text-zinc-900">{{ $rate['service_name'] }}</span>
                                            <span class="block text-xs text-zinc-500">{{ $rate['provider_name'] }}</span>
                                        </span>
                                    </span>
                                    <span class="text-right">
                                        <span class="block font-display text-base font-extrabold text-zinc-900">RM {{ number_format($rate['cost'], 2) }}</span>
                                        @if($rate['estimated_days'])
                                            <span class="block text-xs text-zinc-500">~{{ $rate['estimated_days'] }} hari</span>
                                        @endif
                                    </span>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-8 flex items-center justify-between gap-3">
                            <button type="button" wire:click="backToInformation" class="inline-flex items-center gap-1.5 rounded-xl px-3 py-2.5 text-sm font-semibold text-zinc-500 transition hover:bg-zinc-50 hover:text-zinc-800">
                                <flux:icon name="arrow-left" class="h-4 w-4" />
                                {{ __('store.co_back') }}
                            </button>
                            <button type="button" wire:click="proceedToPayment" @disabled(empty($selectedShippingProvider))
                                    class="store-grad store-grad-hover inline-flex items-center gap-2 rounded-2xl px-6 py-3 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-50">
                                {{ __('store.co_continue_payment') }}
                                <flux:icon name="arrow-right" class="h-5 w-5" />
                            </button>
                        </div>
                    </div>

                @elseif($currentStep === 'payment')
                    <div class="rounded-3xl border border-zinc-200 bg-white p-5 shadow-sm sm:p-6">
                        <h2 class="font-display text-lg font-extrabold text-zinc-900">{{ __('store.co_payment_method') }}</h2>

                        @php
                            $methods = [
                                ['value' => 'credit_card', 'icon' => 'credit-card', 'title' => __('store.co_pm_credit_card'), 'sub' => __('store.co_pm_cards_sub')],
                                ['value' => 'debit_card', 'icon' => 'credit-card', 'title' => __('store.co_pm_debit_card'), 'sub' => __('store.co_pm_cards_sub')],
                                ['value' => 'fpx', 'icon' => 'building-library', 'title' => __('store.co_pm_fpx'), 'sub' => __('store.co_pm_fpx_sub')],
                                ['value' => 'grabpay', 'icon' => 'wallet', 'title' => __('store.co_pm_grabpay'), 'sub' => __('store.co_pm_wallet_sub')],
                                ['value' => 'boost', 'icon' => 'wallet', 'title' => __('store.co_pm_boost'), 'sub' => __('store.co_pm_wallet_sub')],
                            ];
                            if (app(\App\Services\SettingsService::class)->isCodEnabled()) {
                                $methods[] = ['value' => 'cod', 'icon' => 'banknotes', 'title' => __('store.co_pm_cod'), 'sub' => __('store.co_pm_cod_sub')];
                            }
                        @endphp

                        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                            @foreach($methods as $m)
                                @php $on = $paymentMethod === $m['value']; @endphp
                                <label @class([
                                    'flex cursor-pointer items-center gap-3 rounded-2xl border p-4 transition',
                                    'border-violet-500 bg-violet-50 ring-2 ring-violet-200' => $on,
                                    'border-zinc-200 hover:border-violet-300 hover:bg-violet-50/40' => ! $on,
                                ])>
                                    <input type="radio" wire:model.live="paymentMethod" value="{{ $m['value'] }}" class="h-4 w-4 accent-violet-600" />
                                    <span @class(['grid h-10 w-10 shrink-0 place-items-center rounded-xl', 'store-grad text-white' => $on, 'bg-zinc-100 text-zinc-500' => ! $on])>
                                        <flux:icon name="{{ $m['icon'] }}" class="h-5 w-5" />
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block text-sm font-bold text-zinc-900">{{ $m['title'] }}</span>
                                        <span class="block truncate text-xs text-zinc-500">{{ $m['sub'] }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>

                        @if($paymentMethod === 'cod')
                            @php $codInstructions = app(\App\Services\SettingsService::class)->getCodInstructions(); @endphp
                            @if($codInstructions)
                                <div class="mt-4 flex items-start gap-2 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                                    <flux:icon name="information-circle" class="mt-0.5 h-4 w-4 shrink-0" />
                                    <span>{{ $codInstructions }}</span>
                                </div>
                            @endif
                        @endif

                        @if(in_array($paymentMethod, ['credit_card', 'debit_card', 'fpx', 'grabpay', 'boost']))
                            <div class="mt-4 flex items-start gap-2 rounded-2xl border border-violet-200 bg-violet-50 p-4 text-sm text-violet-800">
                                <flux:icon name="shield-check" class="mt-0.5 h-4 w-4 shrink-0" />
                                <span>{{ __('store.co_redirect_notice') }}</span>
                            </div>
                        @endif

                        <div class="mt-8 flex items-center justify-between gap-3">
                            <button type="button" wire:click="{{ $hasShippingProviders ? 'backToShipping' : 'backToInformation' }}" class="inline-flex items-center gap-1.5 rounded-xl px-3 py-2.5 text-sm font-semibold text-zinc-500 transition hover:bg-zinc-50 hover:text-zinc-800">
                                <flux:icon name="arrow-left" class="h-4 w-4" />
                                {{ __('store.co_back') }}
                            </button>
                            <button type="button" wire:click="processOrder" wire:loading.attr="disabled" wire:target="processOrder" @disabled($isProcessing)
                                    class="store-grad store-grad-hover inline-flex items-center gap-2 rounded-2xl px-6 py-3 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-60">
                                <span wire:loading.remove wire:target="processOrder" class="inline-flex items-center gap-2">
                                    <flux:icon name="lock-closed" class="h-5 w-5" />
                                    {{ __('store.co_complete_order') }}
                                </span>
                                <span wire:loading wire:target="processOrder" class="inline-flex items-center gap-2">
                                    <flux:icon name="arrow-path" class="h-5 w-5 animate-spin" />
                                    {{ __('store.co_processing') }}
                                </span>
                            </button>
                        </div>
                    </div>
                @endif
            </div>

            {{-- ===================== ORDER SUMMARY ===================== --}}
            <div class="lg:col-span-1">
                <div class="sticky top-24 overflow-hidden rounded-3xl border border-zinc-200 bg-white shadow-sm">
                    <div class="store-grad px-5 py-4">
                        <h2 class="font-display text-lg font-extrabold text-white">{{ __('store.co_summary') }}</h2>
                    </div>

                    <div class="p-5">
                        <div class="space-y-3">
                            @foreach($cart->items as $item)
                                @php $img = $item->getImageUrl(); @endphp
                                <div class="flex items-center gap-3" wire:key="co-item-{{ $item->id }}">
                                    <div class="relative h-12 w-12 shrink-0 overflow-hidden rounded-xl bg-gradient-to-br from-violet-50 to-fuchsia-50 ring-1 ring-zinc-900/5">
                                        @if($img)
                                            <img src="{{ $img }}" alt="{{ $item->getDisplayName() }}" loading="lazy" class="h-full w-full object-cover"
                                                 onerror="this.style.display='none';this.nextElementSibling.style.display='flex';" />
                                            <span class="hidden h-full w-full items-center justify-center" style="display:none;">
                                                <flux:icon name="photo" class="h-5 w-5 text-violet-300" />
                                            </span>
                                        @else
                                            <span class="flex h-full w-full items-center justify-center">
                                                <flux:icon name="{{ $item->isCourse() ? 'academic-cap' : ($item->isPackage() ? 'gift' : 'photo') }}" class="h-5 w-5 text-violet-300" />
                                            </span>
                                        @endif
                                        <span class="absolute -right-1.5 -top-1.5 grid h-5 min-w-[20px] place-items-center rounded-full bg-zinc-900 px-1 text-[10px] font-bold text-white">{{ $item->quantity }}</span>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-semibold text-zinc-800">{{ $item->getDisplayName() }}</p>
                                        <p class="text-xs text-zinc-400">{{ __('store.co_qty') }}: {{ $item->quantity }}</p>
                                    </div>
                                    <span class="text-sm font-bold tabular-nums text-zinc-900">MYR {{ number_format($item->total_price, 2) }}</span>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-4 space-y-2.5 border-t border-dashed border-zinc-200 pt-4 text-sm">
                            <div class="flex items-center justify-between">
                                <span class="text-zinc-500">{{ __('store.cart_subtotal') }}</span>
                                <span class="font-semibold tabular-nums text-zinc-800">MYR {{ $this->getCartSubtotal() }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-zinc-500">{{ __('store.co_shipping') }}</span>
                                <span class="font-semibold tabular-nums text-zinc-800">
                                    @if($selectedShippingCost > 0)
                                        MYR {{ $this->getShippingCostFormatted() }}
                                    @else
                                        <span class="font-medium text-zinc-400">{{ $hasShippingProviders ? __('store.co_calc_next') : __('store.co_free') }}</span>
                                    @endif
                                </span>
                            </div>
                        </div>

                        <div class="mt-4 flex items-end justify-between border-t border-dashed border-zinc-200 pt-4">
                            <span class="font-display text-base font-bold text-zinc-900">{{ __('store.cart_total') }}</span>
                            <span class="store-grad-text font-display text-2xl font-extrabold tabular-nums">MYR {{ $this->getCartTotal() }}</span>
                        </div>

                        <p class="mt-3 flex items-center justify-center gap-1.5 text-xs text-zinc-400">
                            <flux:icon name="lock-closed" class="h-3.5 w-3.5" />
                            {{ __('store.cart_secure') }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
