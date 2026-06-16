<?php

use App\Models\Funnel;
use App\Models\FunnelCart;
use App\Models\FunnelOrder;
use App\Models\FunnelSession;
use App\Models\FunnelStep;
use App\Models\ProductOrder;
use App\Services\BayarcashService;
use App\Services\SettingsService;
use App\Services\StripeService;
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

    public bool $isProcessing = false;

    public array $availablePaymentMethods = [];

    public string $stripePublishableKey = '';

    public bool $disableShipping = false;

    public string $productSelectionMode = 'multi';

    public string $shippingZone = 'semenanjung';

    public bool $shippingCostEnabled = false;

    public float $shippingSemenanjungCost = 0;

    public float $shippingSabahSarawakCost = 0;

    public string $countryCode = '+60';

    public function mount(Funnel $funnel, FunnelStep $step, ?FunnelSession $session = null): void
    {
        $this->funnel = $funnel;
        $this->step = $step->load(['products.product', 'products.course', 'orderBumps.product']);
        $this->funnelSession = $session;
        $this->disableShipping = (bool) $funnel->disable_shipping;
        $this->productSelectionMode = $funnel->settings['product_selection_mode'] ?? 'multi';

        $shippingSettings = $funnel->shipping_settings ?? [];
        $this->shippingCostEnabled = ! $this->disableShipping && (bool) ($shippingSettings['enabled'] ?? false);
        $this->shippingSemenanjungCost = (float) ($shippingSettings['semenanjung_cost'] ?? 0);
        $this->shippingSabahSarawakCost = (float) ($shippingSettings['sabah_sarawak_cost'] ?? 0);

        $this->loadCart();
        $this->prefillFromSession();
        $this->loadAvailablePaymentMethods();

        // Resolve the Stripe publishable key safely. The funnel can run on
        // FPX/COD alone, so a missing/partial Stripe config must not crash the
        // checkout — only expose the key when Stripe is fully configured.
        $settings = app(SettingsService::class);
        $this->stripePublishableKey = $settings->isStripeConfigured()
            ? (string) $settings->get('stripe_publishable_key')
            : '';

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

        // Add COD if globally enabled and enabled for this funnel
        if (app(SettingsService::class)->isCodEnabled() && in_array('cod', $enabledMethods)) {
            $codLabel = $customLabels['cod'] ?? 'Cash on Delivery';
            $methods[] = [
                'id' => 'cod',
                'name' => $codLabel,
                'description' => 'Bayar semasa penghantaran',
                'icon' => 'truck',
            ];
        }

        // If only one method available or show_method_selector is false, just use default
        if (! $showMethodSelector && count($methods) > 1) {
            // Find the default method
            $defaultId = match ($defaultMethod) {
                'stripe' => 'credit_card',
                'cod' => 'cod',
                default => 'fpx',
            };
            $methods = array_filter($methods, fn ($m) => $m['id'] === $defaultId);
            $methods = array_values($methods);
        }

        // Reorder methods to put the default one first
        if (count($methods) > 1) {
            $defaultId = match ($defaultMethod) {
                'stripe' => 'credit_card',
                'cod' => 'cod',
                default => 'fpx',
            };
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
            $rawPhone = $this->funnelSession->phone ?? '';
            if ($rawPhone) {
                $this->parsePhoneWithCountryCode($rawPhone);
            }
        }

        if (auth()->check()) {
            $user = auth()->user();
            $this->customerData['email'] = $this->customerData['email'] ?: $user->email;
            $this->customerData['name'] = $user->name ?? '';
            $this->billingAddress['first_name'] = $user->name ?? '';
        }
    }

    private function parsePhoneWithCountryCode(string $phone): void
    {
        $countryCodes = ['+856', '+855', '+886', '+852', '+971', '+966', '+974', '+673', '+60', '+65', '+62', '+66', '+63', '+84', '+95', '+91', '+86', '+81', '+82', '+61', '+64', '+44', '+49', '+33', '+1'];

        foreach ($countryCodes as $code) {
            if (str_starts_with($phone, $code)) {
                $this->countryCode = $code;
                $this->customerData['phone'] = substr($phone, strlen($code));

                return;
            }
        }

        $this->customerData['phone'] = $phone;
    }

    public function getFullPhone(): string
    {
        $phone = $this->customerData['phone'] ?? '';

        return $phone ? $this->countryCode.$phone : '';
    }

    public function toggleProduct(int $productId): void
    {
        if ($this->productSelectionMode === 'single') {
            // Single-select: replace selection (don't allow deselect)
            $this->selectedProducts = [$productId => true];
        } else {
            // Multi-select: toggle
            if (isset($this->selectedProducts[$productId])) {
                unset($this->selectedProducts[$productId]);
            } else {
                $this->selectedProducts[$productId] = true;
            }
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
                'phone' => $this->getFullPhone() ?: null,
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

    public function calculateShippingCost(): float
    {
        if (! $this->shippingCostEnabled) {
            return 0;
        }

        return $this->shippingZone === 'sabah_sarawak'
            ? $this->shippingSabahSarawakCost
            : $this->shippingSemenanjungCost;
    }

    public function calculateTotal(): float
    {
        return $this->calculateSubtotal() + $this->calculateBumpsTotal() + $this->calculateShippingCost();
    }

    /**
     * Single-page checkout validation: product selection, contact details and
     * (when shipping is enabled) the delivery address are all checked in one
     * pass before the order is created. Field errors render inline next to each
     * input. Returns false only for the soft "no product" guard; hard field
     * failures throw a ValidationException so Livewire renders the error bag.
     */
    private function validateCheckout(): bool
    {
        if (empty($this->selectedProducts)) {
            $this->addError('products', 'Sila pilih sekurang-kurangnya satu produk.');
            $this->dispatch('checkout-validation-failed');

            return false;
        }

        $this->updateCart();

        // Trim whitespace so values that look valid in the UI (e.g. "12312 ")
        // don't fail length checks after Laravel's TrimStrings middleware.
        $this->customerData['email'] = trim($this->customerData['email'] ?? '');
        $this->customerData['name'] = trim($this->customerData['name'] ?? '');
        $this->customerData['phone'] = trim($this->customerData['phone'] ?? '');

        foreach (['first_name', 'address_line_1', 'address_line_2', 'city', 'state', 'postal_code'] as $field) {
            $this->billingAddress[$field] = trim($this->billingAddress[$field] ?? '');
        }

        // Auto-populate billing name from customer name
        if (! $this->disableShipping) {
            $this->billingAddress['first_name'] = $this->customerData['name'] ?? '';
        }

        $rules = [
            'customerData.email' => 'nullable|email',
            'customerData.name' => 'required|string|min:2',
            'customerData.phone' => 'required|string|min:7',
        ];

        if (! $this->disableShipping) {
            $rules['billingAddress.first_name'] = 'required|string|min:2';
            $rules['billingAddress.address_line_1'] = 'required|string|min:5';
            $rules['billingAddress.city'] = 'required|string|min:2';
            $rules['billingAddress.state'] = 'required|string|min:2';
            $rules['billingAddress.postal_code'] = 'required|string|min:5';
        }

        $messages = [
            'customerData.email.email' => 'Sila masukkan alamat emel yang sah.',
            'customerData.name.required' => 'Nama penuh diperlukan.',
            'customerData.name.min' => 'Nama penuh mestilah sekurang-kurangnya 2 aksara.',
            'customerData.phone.required' => 'Nombor telefon diperlukan.',
            'customerData.phone.min' => 'Nombor telefon mestilah sekurang-kurangnya 7 digit.',
            'billingAddress.first_name.required' => 'Nama diperlukan.',
            'billingAddress.address_line_1.required' => 'Alamat diperlukan.',
            'billingAddress.address_line_1.min' => 'Alamat mestilah sekurang-kurangnya 5 aksara.',
            'billingAddress.city.required' => 'Bandar diperlukan.',
            'billingAddress.state.required' => 'Negeri diperlukan.',
            'billingAddress.postal_code.required' => 'Poskod diperlukan.',
            'billingAddress.postal_code.min' => 'Poskod mestilah sekurang-kurangnya 5 aksara.',
        ];

        try {
            $this->validate($rules, $messages);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->dispatch('checkout-validation-failed');

            throw $e;
        }

        return true;
    }

    private function persistContactInfo(): void
    {
        $fullPhone = $this->getFullPhone();

        if ($this->funnelSession) {
            $this->funnelSession->update([
                'email' => $this->customerData['email'],
                'phone' => $fullPhone ?: null,
            ]);
        }

        if ($this->cart) {
            $this->cart->update([
                'email' => $this->customerData['email'],
                'phone' => $fullPhone ?: null,
            ]);
        }

        $this->funnelSession?->trackEvent('checkout_info_completed', [
            'email' => $this->customerData['email'],
        ], $this->step);
    }

    public function processOrder(): void
    {
        // Single-page checkout: run product + contact + delivery validation first
        // so any field errors surface inline before we create the order or touch
        // the payment provider. A hard field failure throws and stops here.
        if (! $this->validateCheckout()) {
            return;
        }

        $this->persistContactInfo();

        $this->isProcessing = true;

        try {
            // Validate payment method is available
            $availableMethodIds = collect($this->availablePaymentMethods)->pluck('id')->toArray();
            $this->validate([
                'paymentMethod' => 'required|in:'.implode(',', $availableMethodIds),
            ]);

            // Create the order using existing ProductOrder system
            $orderData = $this->prepareOrderData();

            // Prepare billing address (use fallback when shipping is disabled)
            $billingAddress = $this->disableShipping ? [
                'first_name' => explode(' ', $this->customerData['name'])[0] ?? $this->customerData['name'],
                'last_name' => explode(' ', $this->customerData['name'])[1] ?? '',
                'address_line_1' => '',
                'city' => '',
                'state' => '',
                'postal_code' => '',
                'country' => 'Malaysia',
            ] : $this->billingAddress;

            // Create ProductOrder
            $productOrder = \App\Models\ProductOrder::create([
                'order_number' => \App\Models\ProductOrder::generateOrderNumber(),
                'user_id' => auth()->id(),
                'student_id' => auth()->user()?->student?->id,
                'guest_email' => $this->customerData['email'],
                'customer_name' => $this->customerData['name'],
                'customer_phone' => $this->getFullPhone() ?: null,
                'billing_address' => $billingAddress,
                'shipping_address' => $billingAddress,
                'subtotal' => $this->calculateSubtotal(),
                'shipping_cost' => $this->calculateShippingCost(),
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
                        'product_name' => $bump->headline,
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
                'class_session_id' => $this->funnelSession?->class_session_id,
                'order_type' => 'main',
                'funnel_revenue' => $this->calculateTotal(),
                'bumps_offered' => $this->step->orderBumps->count(),
                'bumps_accepted' => count($this->selectedBumps),
            ]);

            // Track form submission event (for affiliate checkout fill stats)
            $this->funnelSession?->trackEvent('form_submit', [
                'order_id' => $productOrder->id,
                'order_number' => $productOrder->order_number,
            ], $this->step);

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

            // COD: create payment record and move the order to 'processing' so the team can
            // prepare it for fulfilment. Payment stays 'pending' until cash is collected on delivery.
            if ($this->paymentMethod === 'cod') {
                $productOrder->payments()->create([
                    'payment_method' => 'cod',
                    'payment_provider' => 'cod',
                    'amount' => $productOrder->total_amount,
                    'currency' => $productOrder->currency,
                    'status' => 'pending',
                    'transaction_id' => 'COD-'.date('Ymd').'-'.strtoupper(\Illuminate\Support\Str::random(8)),
                ]);
                $productOrder->update(['status' => 'processing']);
                $productOrder->addSystemNote('COD order placed — moved to processing for fulfilment');

                // Track Facebook Pixel Purchase event (server-side)
                if ($this->funnelSession) {
                    app(\App\Services\Funnel\FacebookPixelService::class)->trackPurchase(
                        $this->funnel,
                        $productOrder,
                        $this->funnelSession,
                        null,
                        request()->fullUrl()
                    );
                }

                // Calculate affiliate commission if applicable
                if ($this->funnelSession && $funnelOrder) {
                    app(\App\Services\Funnel\AffiliateCommissionService::class)
                        ->calculateCommission($funnelOrder, $this->funnelSession);
                }

                // Trigger funnel automations for purchase completed
                app(\App\Services\Funnel\FunnelAutomationService::class)
                    ->triggerPurchaseCompleted($productOrder, $this->funnelSession);

                $this->redirectToNextStep($productOrder);

                return;
            }

            // For Stripe payments (credit_card, debit_card) — create a PaymentIntent
            // server-side and hand the client_secret to Stripe.js so it can charge
            // the card. Charging completes in confirmStripePayment() below.
            $this->processStripePayment($productOrder);

            return;

        } catch (\Exception $e) {
            $this->addError('payment', $e->getMessage());
        } finally {
            $this->isProcessing = false;
        }
    }

    private function processStripePayment(ProductOrder $order): void
    {
        try {
            $stripeService = app(StripeService::class);

            if (! $stripeService->isConfigured()) {
                throw new \RuntimeException('Stripe is not configured.');
            }

            $stripe = $stripeService->getStripe();

            $paymentIntent = $stripe->paymentIntents->create([
                'amount' => (int) round($order->total_amount * 100),
                'currency' => strtolower($order->currency ?: 'MYR'),
                'automatic_payment_methods' => ['enabled' => true],
                'receipt_email' => $this->customerData['email'],
                'description' => 'Funnel purchase: '.$this->funnel->name,
                'metadata' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'funnel_id' => $this->funnel->id,
                    'session_uuid' => $this->funnelSession?->uuid,
                ],
            ]);

            $order->update([
                'payment_provider' => 'stripe',
                'metadata' => array_merge($order->metadata ?? [], [
                    'stripe_payment_intent_id' => $paymentIntent->id,
                ]),
            ]);

            $this->funnelSession?->trackEvent('checkout_initiated', [
                'order_id' => $order->id,
                'payment_method' => 'credit_card',
                'provider' => 'stripe',
                'payment_intent_id' => $paymentIntent->id,
            ], $this->step);

            // Hand control to Stripe.js with the client_secret. The JS confirms
            // the card with Stripe, then calls confirmStripePayment() below.
            // Keep isProcessing=true here so the form button stays disabled
            // while the customer confirms the card in the Stripe modal.
            $this->dispatch('funnel-stripe-charge', [
                'orderId' => $order->id,
                'orderNumber' => $order->order_number,
                'clientSecret' => $paymentIntent->client_secret,
                'publishableKey' => $stripeService->getPublishableKey(),
                'paymentIntentId' => $paymentIntent->id,
                'total' => $this->calculateTotal(),
                'customerEmail' => $this->customerData['email'],
                'customerName' => $this->customerData['name'],
            ]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Stripe PaymentIntent creation failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            $this->addError('payment', 'Failed to initialise card payment: '.$e->getMessage());
            $this->isProcessing = false;
        }
    }

    #[On('funnel-stripe-charge-failed')]
    public function resetProcessingAfterStripeFailure(): void
    {
        // JS calls this when Stripe.js confirmCardPayment fails so the customer
        // can re-enter card details and retry without refreshing.
        $this->isProcessing = false;
    }

    public function confirmStripePayment(int $orderId, string $paymentIntentId): void
    {
        try {
            $order = ProductOrder::find($orderId);

            if (! $order) {
                $this->addError('payment', 'Order not found.');
                $this->isProcessing = false;

                return;
            }

            // Re-verify with Stripe (don't trust the client) before marking paid.
            $stripe = app(StripeService::class)->getStripe();
            $paymentIntent = $stripe->paymentIntents->retrieve($paymentIntentId);

            if ($paymentIntent->status !== 'succeeded') {
                $this->addError('payment', 'Payment was not completed. Status: '.$paymentIntent->status);
                $this->isProcessing = false;

                return;
            }

            // Defence against payment_intent_id swap: it must match the one we
            // stamped onto the order's metadata during processStripePayment.
            $storedIntentId = $order->metadata['stripe_payment_intent_id'] ?? null;
            if ($storedIntentId !== $paymentIntentId) {
                \Illuminate\Support\Facades\Log::warning('Stripe payment_intent_id mismatch on confirm', [
                    'order_id' => $order->id,
                    'expected' => $storedIntentId,
                    'received' => $paymentIntentId,
                ]);
                $this->addError('payment', 'Payment verification failed.');
                $this->isProcessing = false;

                return;
            }

            $order->update([
                'status' => 'confirmed',
                'payment_status' => 'paid',
                'paid_time' => $order->paid_time ?? now(),
            ]);

            $this->funnelSession?->markAsConverted();
            $this->funnelSession?->trackEvent('payment_completed', [
                'order_id' => $order->id,
                'amount' => $order->total_amount,
                'payment_method' => 'credit_card',
                'provider' => 'stripe',
                'payment_intent_id' => $paymentIntentId,
            ], $this->step);

            // Mark cart as recovered
            if ($this->cart) {
                $this->cart->markAsRecovered($order);
            }

            // Update funnel analytics
            $stepAnalytics = \App\Models\FunnelAnalytics::getOrCreateForToday($this->funnel->id, $this->step->id);
            $stepAnalytics->incrementConversions($this->calculateTotal());

            $funnelAnalytics = \App\Models\FunnelAnalytics::getOrCreateForToday($this->funnel->id);
            $funnelAnalytics->incrementConversions($this->calculateTotal());

            // Track Facebook Pixel Purchase event
            app(\App\Services\Funnel\FacebookPixelService::class)->trackPurchase(
                $this->funnel,
                $order,
                $this->funnelSession,
                null,
                request()->fullUrl()
            );

            // Affiliate commission
            $funnelOrder = FunnelOrder::where('product_order_id', $order->id)->first();
            if ($this->funnelSession && $funnelOrder) {
                app(\App\Services\Funnel\AffiliateCommissionService::class)
                    ->calculateCommission($funnelOrder, $this->funnelSession);
            }

            // Funnel automations
            app(\App\Services\Funnel\FunnelAutomationService::class)
                ->triggerPurchaseCompleted($order, $this->funnelSession);

            $this->redirectToNextStep($order);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Stripe payment confirmation failed', [
                'order_id' => $orderId,
                'payment_intent_id' => $paymentIntentId,
                'error' => $e->getMessage(),
            ]);
            $this->addError('payment', 'Failed to confirm payment: '.$e->getMessage());
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
                'payer_phone' => $this->getFullPhone(),
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

        $isCustomDomain = request()->attributes->has('custom_domain');

        if ($nextStep) {
            $url = $isCustomDomain
                ? "/{$nextStep->slug}?order={$productOrder->order_number}"
                : "/f/{$this->funnel->slug}/{$nextStep->slug}?order={$productOrder->order_number}";
            $this->redirect($url);
        } else {
            // No next step - show thank you
            session()->flash('order_completed', true);
            session()->flash('order_number', $productOrder->order_number);
            $url = $isCustomDomain
                ? "/?complete=1&order={$productOrder->order_number}"
                : "/f/{$this->funnel->slug}?complete=1&order={$productOrder->order_number}";
            $this->redirect($url);
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

<div class="funnel-checkout" data-funnel-stripe-pk="{{ $stripePublishableKey }}">
    {{-- Secure checkout header --}}
    <div class="fc-topbar">
        <span class="fc-topbar-lock">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 0h10.5a2.25 2.25 0 012.25 2.25v6.75a2.25 2.25 0 01-2.25 2.25H6.75a2.25 2.25 0 01-2.25-2.25v-6.75a2.25 2.25 0 012.25-2.25z"/></svg>
        </span>
        <span class="fc-topbar-text">Pembayaran selamat &amp; disulitkan SSL</span>
        <span class="fc-topbar-sep"></span>
        <span class="fc-topbar-muted">Lengkapkan pesanan dalam satu halaman</span>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 fc-main">
        {{-- LEFT: checkout sections --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Section 1: Package selection --}}
            <div class="fc-card" id="fc-section-packages">
                <div class="fc-section-head">
                    <span class="fc-step">1</span>
                    <div class="fc-section-heading">
                        <h3 class="fc-section-title">Pilih Pakej Anda</h3>
                        <p class="fc-section-sub">Pilih pakej yang paling sesuai untuk anda</p>
                    </div>
                </div>

                @error('products')
                    <div class="fc-alert fc-alert-error">{{ $message }}</div>
                @enderror

                <div class="space-y-3">
                    @forelse($step->products as $product)
                        <div
                            wire:key="fc-pkg-{{ $product->id }}"
                            wire:click="toggleProduct({{ $product->id }})"
                            class="fc-pkg {{ isset($selectedProducts[$product->id]) ? 'is-active' : '' }}"
                        >
                            @if($loop->first && $step->products->count() > 1)
                                <span class="fc-badge-popular">
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M9.05 2.93c.3-.92 1.6-.92 1.9 0l1.42 4.37h4.6c.97 0 1.37 1.24.59 1.81l-3.72 2.7 1.42 4.37c.3.92-.75 1.69-1.54 1.12L10 14.97l-3.72 2.7c-.79.57-1.84-.2-1.54-1.12l1.42-4.37-3.72-2.7c-.78-.57-.38-1.81.59-1.81h4.6L9.05 2.93z"/></svg>
                                    Paling Popular
                                </span>
                            @endif

                            <div class="fc-pkg-select">
                                @if($productSelectionMode === 'single')
                                    <span class="fc-radio {{ isset($selectedProducts[$product->id]) ? 'is-active' : '' }}">
                                        <span class="fc-radio-dot"></span>
                                    </span>
                                @else
                                    <span class="fc-check {{ isset($selectedProducts[$product->id]) ? 'is-active' : '' }}">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3.5" d="M5 13l4 4L19 7"/></svg>
                                    </span>
                                @endif
                            </div>

                            <div class="fc-pkg-body">
                                <div class="fc-pkg-toprow">
                                    <div class="fc-pkg-name-wrap">
                                        <h4 class="fc-pkg-name">{{ $product->name }}</h4>
                                        @if($product->description)
                                            <p class="fc-pkg-desc">{{ $product->description }}</p>
                                        @endif
                                    </div>
                                    <div class="fc-pkg-pricing">
                                        <div class="fc-pkg-price">RM {{ number_format($product->funnel_price, 2) }}</div>
                                        @if($product->compare_at_price && $product->compare_at_price > $product->funnel_price)
                                            <div class="fc-pkg-compare">RM {{ number_format($product->compare_at_price, 2) }}</div>
                                        @endif
                                    </div>
                                </div>

                                @if(($product->compare_at_price && $product->compare_at_price > $product->funnel_price) || $product->is_recurring)
                                    <div class="fc-pkg-tags">
                                        @if($product->compare_at_price && $product->compare_at_price > $product->funnel_price)
                                            <span class="fc-save">Jimat RM {{ number_format($product->compare_at_price - $product->funnel_price, 2) }}</span>
                                        @endif
                                        @if($product->is_recurring)
                                            <span class="fc-tag-sub">Langganan {{ ucfirst($product->billing_interval) }}</span>
                                        @endif
                                    </div>
                                @endif

                                @if($product->isPackage() && $product->package)
                                    <div class="fc-pkg-includes">
                                        <p class="fc-includes-label">Termasuk dalam pakej:</p>
                                        <div class="space-y-1">
                                            @foreach($product->package->items as $pkgItem)
                                                <div class="fc-include-item">
                                                    <svg class="w-3.5 h-3.5 fc-include-check" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                                    <span>{{ $pkgItem->quantity > 1 ? $pkgItem->quantity . 'x ' : '' }}{{ $pkgItem->getDisplayName() }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="fc-empty">Tiada produk tersedia untuk dipilih.</div>
                    @endforelse
                </div>
            </div>

            {{-- Order Bumps --}}
            @if($step->orderBumps->isNotEmpty())
                <div class="fc-bump-wrap">
                    <div class="fc-bump-head">
                        <span class="fc-bump-flag">TUNGGU!</span>
                        <h3 class="fc-bump-title">Tawaran Istimewa Sekali Sahaja</h3>
                    </div>

                    <div class="space-y-3">
                        @foreach($step->orderBumps as $bump)
                            <div
                                wire:key="fc-bump-{{ $bump->id }}"
                                wire:click="toggleBump({{ $bump->id }})"
                                class="fc-bump {{ isset($selectedBumps[$bump->id]) ? 'is-active' : '' }}"
                            >
                                <div class="fc-bump-select">
                                    <span class="fc-check {{ isset($selectedBumps[$bump->id]) ? 'is-active' : '' }}">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3.5" d="M5 13l4 4L19 7"/></svg>
                                    </span>
                                </div>
                                <div class="fc-bump-body">
                                    <div class="fc-bump-toprow">
                                        <div class="fc-bump-name-wrap">
                                            <span class="fc-bump-kicker">Ya, tambah ke pesanan saya</span>
                                            <h4 class="fc-bump-name">{{ $bump->headline }}</h4>
                                            @if($bump->description)
                                                <p class="fc-bump-desc">{{ $bump->description }}</p>
                                            @endif
                                        </div>
                                        <div class="fc-bump-pricing">
                                            <div class="fc-bump-price">+RM {{ number_format($bump->price, 2) }}</div>
                                            @if($bump->compare_at_price && $bump->compare_at_price > $bump->price)
                                                <div class="fc-pkg-compare">RM {{ number_format($bump->compare_at_price, 2) }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

    {{-- Section 2: Customer information --}}
    <div class="fc-card" id="fc-section-info">
        <div class="fc-section-head">
            <span class="fc-step">2</span>
            <div class="fc-section-heading">
                <h3 class="fc-section-title">Maklumat Anda</h3>
                <p class="fc-section-sub">Untuk menghantar &amp; menghubungi anda</p>
            </div>
        </div>

        <div class="space-y-6">
            {{-- Contact Information --}}
            <div>
                <h4 class="fc-subhead">Maklumat Perhubungan</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="fc-label">Telefon *</label>
                        <div class="flex" x-data="{
                                        open: false,
                                        search: '',
                                        codes: [
                                            { code: '+60', flag: '🇲🇾', name: 'Malaysia' },
                                            { code: '+65', flag: '🇸🇬', name: 'Singapore' },
                                            { code: '+62', flag: '🇮🇩', name: 'Indonesia' },
                                            { code: '+66', flag: '🇹🇭', name: 'Thailand' },
                                            { code: '+63', flag: '🇵🇭', name: 'Philippines' },
                                            { code: '+84', flag: '🇻🇳', name: 'Vietnam' },
                                            { code: '+673', flag: '🇧🇳', name: 'Brunei' },
                                            { code: '+95', flag: '🇲🇲', name: 'Myanmar' },
                                            { code: '+856', flag: '🇱🇦', name: 'Laos' },
                                            { code: '+855', flag: '🇰🇭', name: 'Cambodia' },
                                            { code: '+91', flag: '🇮🇳', name: 'India' },
                                            { code: '+86', flag: '🇨🇳', name: 'China' },
                                            { code: '+81', flag: '🇯🇵', name: 'Japan' },
                                            { code: '+82', flag: '🇰🇷', name: 'South Korea' },
                                            { code: '+886', flag: '🇹🇼', name: 'Taiwan' },
                                            { code: '+852', flag: '🇭🇰', name: 'Hong Kong' },
                                            { code: '+61', flag: '🇦🇺', name: 'Australia' },
                                            { code: '+64', flag: '🇳🇿', name: 'New Zealand' },
                                            { code: '+44', flag: '🇬🇧', name: 'United Kingdom' },
                                            { code: '+1', flag: '🇺🇸', name: 'United States' },
                                            { code: '+971', flag: '🇦🇪', name: 'UAE' },
                                            { code: '+966', flag: '🇸🇦', name: 'Saudi Arabia' },
                                            { code: '+974', flag: '🇶🇦', name: 'Qatar' },
                                            { code: '+49', flag: '🇩🇪', name: 'Germany' },
                                            { code: '+33', flag: '🇫🇷', name: 'France' },
                                        ],
                                        get filtered() {
                                            if (!this.search) return this.codes;
                                            const s = this.search.toLowerCase();
                                            return this.codes.filter(c => c.name.toLowerCase().includes(s) || c.code.includes(s));
                                        },
                                        get selected() {
                                            return this.codes.find(c => c.code === $wire.countryCode) || this.codes[0];
                                        },
                                        select(code) {
                                            $wire.countryCode = code;
                                            this.open = false;
                                            this.search = '';
                                        }
                                    }" @click.outside="open = false; search = ''">
                                        <div class="relative">
                                            <button
                                                type="button"
                                                @click="open = !open"
                                                class="flex items-center gap-1.5 px-3 py-2 border border-r-0 border-gray-300 rounded-l-lg bg-gray-50 hover:bg-gray-100 transition-colors text-sm whitespace-nowrap"
                                            >
                                                <span x-text="selected.flag"></span>
                                                <span class="text-gray-700" x-text="selected.code"></span>
                                                <svg class="w-3.5 h-3.5 text-gray-400 transition-transform" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                                </svg>
                                            </button>

                                            <div
                                                x-show="open"
                                                x-transition:enter="transition ease-out duration-100"
                                                x-transition:enter-start="opacity-0 scale-95"
                                                x-transition:enter-end="opacity-100 scale-100"
                                                x-transition:leave="transition ease-in duration-75"
                                                x-transition:leave-start="opacity-100 scale-100"
                                                x-transition:leave-end="opacity-0 scale-95"
                                                class="absolute z-50 mt-1 w-64 bg-white border border-gray-200 rounded-lg shadow-lg overflow-hidden"
                                                x-cloak
                                            >
                                                <div class="p-2 border-b border-gray-100">
                                                    <input
                                                        type="text"
                                                        x-model="search"
                                                        placeholder="Cari negara..."
                                                        class="w-full px-3 py-1.5 text-sm border border-gray-200 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                                        @click.stop
                                                    >
                                                </div>
                                                <div class="max-h-48 overflow-y-auto">
                                                    <template x-for="item in filtered" :key="item.code">
                                                        <button
                                                            type="button"
                                                            @click="select(item.code)"
                                                            class="w-full flex items-center gap-2 px-3 py-2 text-sm hover:bg-blue-50 transition-colors"
                                                            :class="item.code === $wire.countryCode && 'bg-blue-50 text-blue-700'"
                                                        >
                                                            <span x-text="item.flag"></span>
                                                            <span class="flex-1 text-left" x-text="item.name"></span>
                                                            <span class="text-gray-500" x-text="item.code"></span>
                                                        </button>
                                                    </template>
                                                    <div x-show="filtered.length === 0" class="px-3 py-2 text-sm text-gray-500">Tiada hasil ditemui</div>
                                                </div>
                                            </div>
                                        </div>
                                        <input
                                            type="tel"
                                            wire:model="customerData.phone"
                                            class="flex-1 min-w-0 px-3 py-2 border border-gray-300 rounded-r-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                            placeholder="12 345 6789"
                                        >
                                    </div>
                                    @error('customerData.phone') <span class="fc-error">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="fc-label">Nama Penuh *</label>
                                    <input
                                        type="text"
                                        wire:model="customerData.name"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                        placeholder="Cth: Nurul Aina"
                                    >
                                    @error('customerData.name') <span class="fc-error">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="fc-label">Emel (Pilihan)</label>
                                    <input
                                        type="email"
                                        wire:model="customerData.email"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                        placeholder="emel@contoh.com"
                                    >
                                    @error('customerData.email') <span class="fc-error">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>

                        {{-- Shipping Zone Selector --}}
                        @if(!$disableShipping && $shippingCostEnabled)
                            <div>
                                <h4 class="fc-subhead">Zon Penghantaran</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <label class="fc-zone {{ $shippingZone === 'semenanjung' ? 'is-active' : '' }}">
                                        <input type="radio" wire:model.live="shippingZone" value="semenanjung" class="fc-zone-radio">
                                        <div class="fc-zone-info">
                                            <p class="fc-zone-name">Semenanjung Malaysia</p>
                                            <p class="fc-zone-sub">West Malaysia</p>
                                        </div>
                                        <span class="fc-zone-price">RM {{ number_format($shippingSemenanjungCost, 2) }}</span>
                                    </label>

                                    <label class="fc-zone {{ $shippingZone === 'sabah_sarawak' ? 'is-active' : '' }}">
                                        <input type="radio" wire:model.live="shippingZone" value="sabah_sarawak" class="fc-zone-radio">
                                        <div class="fc-zone-info">
                                            <p class="fc-zone-name">Sabah &amp; Sarawak</p>
                                            <p class="fc-zone-sub">East Malaysia</p>
                                        </div>
                                        <span class="fc-zone-price">RM {{ number_format($shippingSabahSarawakCost, 2) }}</span>
                                    </label>
                                </div>
                            </div>
                        @endif

                        {{-- Billing Address --}}
                        @if(!$disableShipping)
                            <div>
                                <h4 class="fc-subhead">Alamat Penghantaran</h4>
                                <div class="space-y-4">
                                    <div>
                                        <label class="fc-label">Alamat *</label>
                                        <input type="text" wire:model="billingAddress.address_line_1" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="No. & nama jalan">
                                        @error('billingAddress.address_line_1') <span class="fc-error">{{ $message }}</span> @enderror
                                    </div>

                                    <div>
                                        <input type="text" wire:model="billingAddress.address_line_2" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Apartmen, unit, dll. (pilihan)">
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div>
                                            <label class="fc-label">Bandar *</label>
                                            <input type="text" wire:model="billingAddress.city" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                            @error('billingAddress.city') <span class="fc-error">{{ $message }}</span> @enderror
                                        </div>

                                        <div>
                                            <label class="fc-label">Negeri *</label>
                                            <input type="text" wire:model="billingAddress.state" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                            @error('billingAddress.state') <span class="fc-error">{{ $message }}</span> @enderror
                                        </div>

                                        <div>
                                            <label class="fc-label">Poskod *</label>
                                            <input type="text" inputmode="numeric" maxlength="10" wire:model="billingAddress.postal_code" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                            @error('billingAddress.postal_code') <span class="fc-error">{{ $message }}</span> @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                </div>
            </div>

            {{-- Section 3: Payment --}}
            <div class="fc-card" id="fc-section-payment">
                <div class="fc-section-head">
                    <span class="fc-step">3</span>
                    <div class="fc-section-heading">
                        <h3 class="fc-section-title">Pembayaran</h3>
                        <p class="fc-section-sub">Pilih kaedah pembayaran pilihan anda</p>
                    </div>
                </div>

                @error('payment')
                    <div class="fc-alert fc-alert-error">{{ $message }}</div>
                @enderror

                @if(empty($availablePaymentMethods))
                    <div class="fc-alert fc-alert-warn">
                        <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                        <span>Tiada kaedah pembayaran tersedia buat masa ini. Sila hubungi sokongan.</span>
                    </div>
                @else
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        @foreach($availablePaymentMethods as $method)
                            <div
                                wire:key="fc-pay-{{ $method['id'] }}"
                                wire:click="$set('paymentMethod', '{{ $method['id'] }}')"
                                class="fc-pay {{ $paymentMethod === $method['id'] ? 'is-active' : '' }}"
                            >
                                <span class="fc-radio {{ $paymentMethod === $method['id'] ? 'is-active' : '' }}">
                                    <span class="fc-radio-dot"></span>
                                </span>
                                <span class="fc-pay-icon">
                                    @if(($method['icon'] ?? '') === 'building-library')
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.33A48.4 48.4 0 0012 9.75c-2.55 0-5.06.2-7.5.58V21M3 21h18"/></svg>
                                    @elseif(($method['icon'] ?? '') === 'truck')
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M8.25 18.75a1.5 1.5 0 01-3 0m9 0a1.5 1.5 0 01-3 0m-6 0H3.4a1.13 1.13 0 01-1.13-1.13V6.62c0-.62.5-1.12 1.12-1.12h9.76c.62 0 1.12.5 1.12 1.12v11.13m0 0h2.25m3 0h.38a1.13 1.13 0 001.1-1.12 17.9 17.9 0 00-3.21-9.2 2.06 2.06 0 00-1.58-.86H15"/></svg>
                                    @else
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3M3.75 19.5h16.5A2.25 2.25 0 0022.5 17.25V6.75A2.25 2.25 0 0020.25 4.5H3.75A2.25 2.25 0 001.5 6.75v10.5A2.25 2.25 0 003.75 19.5z"/></svg>
                                    @endif
                                </span>
                                <span class="fc-pay-body">
                                    <span class="fc-pay-name">{{ $method['name'] }}</span>
                                    <span class="fc-pay-desc">{{ $method['description'] }}</span>
                                </span>
                                @if($method['id'] === 'fpx')
                                    <span class="fc-pay-badge">FPX</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                @if(in_array($paymentMethod, ['credit_card', 'debit_card']))
                    <div class="fc-pay-panel">
                        <label class="fc-label">Maklumat Kad</label>
                        {{-- Stripe Card Element mounts here. See <script> block at end of view. --}}
                        <div id="stripe-card-element" class="fc-card-element"></div>
                        <div id="stripe-card-errors" role="alert" class="fc-card-errors"></div>
                        <p class="fc-pay-note">Pembayaran diproses dengan selamat oleh Stripe. Kami tidak menyimpan butiran kad anda.</p>
                    </div>
                @elseif($paymentMethod === 'fpx')
                    <div class="fc-pay-notice">
                        <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4zm6 4a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/></svg>
                        <span>Anda akan dialihkan ke perbankan dalam talian bank anda untuk melengkapkan pembayaran melalui FPX.</span>
                    </div>
                @elseif($paymentMethod === 'cod')
                    <div class="fc-pay-notice">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6"/></svg>
                        <span>Bayar secara tunai semasa pesanan anda dihantar.</span>
                    </div>
                @endif
            </div>

            {{-- Trust badges (desktop) --}}
            <div class="fc-trust">
                <div class="fc-trust-item">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 0h10.5a2.25 2.25 0 012.25 2.25v6.75a2.25 2.25 0 01-2.25 2.25H6.75a2.25 2.25 0 01-2.25-2.25v-6.75a2.25 2.25 0 012.25-2.25z"/></svg>
                    <span>SSL Disulitkan</span>
                </div>
                <div class="fc-trust-item">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M9 12.75l2.25 2.25 4.5-5.25M12 3l7.5 3v6c0 4.5-3.15 7.5-7.5 9-4.35-1.5-7.5-4.5-7.5-9V6L12 3z"/></svg>
                    <span>Jaminan Wang Dikembalikan</span>
                </div>
                <div class="fc-trust-item">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 8.25h16.5"/></svg>
                    <span>Pelbagai Kaedah Bayaran</span>
                </div>
            </div>
        </div>

        {{-- RIGHT: order summary + primary CTA (desktop sticky) --}}
        <div class="lg:col-span-1">
            <div class="fc-sticky">
                @include('livewire.funnel.partials.order-summary', ['step' => $step, 'selectedProducts' => $selectedProducts, 'selectedBumps' => $selectedBumps, 'shippingCostEnabled' => $shippingCostEnabled, 'shippingZone' => $shippingZone, 'shippingSemenanjungCost' => $shippingSemenanjungCost, 'shippingSabahSarawakCost' => $shippingSabahSarawakCost])

                <button
                    type="button"
                    wire:click="processOrder"
                    data-checkout-submit
                    wire:loading.attr="disabled"
                    wire:loading.class="is-loading"
                    wire:target="processOrder"
                    @disabled($isProcessing || empty($availablePaymentMethods))
                    class="fc-cta fc-cta-desktop"
                >
                    <span class="fc-cta-inner fc-cta-default">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 0h10.5a2.25 2.25 0 012.25 2.25v6.75a2.25 2.25 0 01-2.25 2.25H6.75a2.25 2.25 0 01-2.25-2.25v-6.75a2.25 2.25 0 012.25-2.25z"/></svg>
                        Lengkapkan Pembelian &bull; RM {{ number_format($this->calculateTotal(), 2) }}
                    </span>
                    <span class="fc-cta-inner fc-cta-loading">
                        <svg class="fc-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" opacity="0.25"/><path d="M12 2a10 10 0 0110 10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
                        Memproses...
                    </span>
                </button>

                <div class="fc-guarantee">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M9 12.75l2.25 2.25 4.5-5.25M12 3l7.5 3v6c0 4.5-3.15 7.5-7.5 9-4.35-1.5-7.5-4.5-7.5-9V6L12 3z"/></svg>
                    Jaminan wang dikembalikan 30 hari
                </div>
            </div>
        </div>
    </div>

    {{-- Mobile sticky checkout bar --}}
    <div class="fc-mobilebar">
        <div class="fc-mobilebar-info">
            <span class="fc-mobilebar-label">Jumlah</span>
            <span class="fc-mobilebar-total">RM {{ number_format($this->calculateTotal(), 2) }}</span>
        </div>
        <button
            type="button"
            wire:click="processOrder"
            data-checkout-submit
            wire:loading.attr="disabled"
            wire:loading.class="is-loading"
            wire:target="processOrder"
            @disabled($isProcessing || empty($availablePaymentMethods))
            class="fc-cta fc-mobilebar-cta"
        >
            <span class="fc-cta-inner fc-cta-default">Bayar Sekarang</span>
            <span class="fc-cta-inner fc-cta-loading">
                <svg class="fc-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" opacity="0.25"/><path d="M12 2a10 10 0 0110 10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
                Memproses
            </span>
        </button>
    </div>
    <div class="fc-mobilebar-spacer"></div>
</div>

<script>
    // Single-page checkout: when validation fails on submit, bring the first
    // error into view so the buyer immediately sees what to fix.
    (function () {
        function scrollToFirstError() {
            try {
                var root = document.querySelector('.funnel-checkout');
                if (!root) {
                    return;
                }
                var el = root.querySelector('.fc-alert-error, .fc-error');
                if (el) {
                    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            } catch (e) { /* ignore */ }
        }

        document.addEventListener('livewire:init', function () {
            if (window.Livewire && typeof Livewire.on === 'function') {
                Livewire.on('checkout-validation-failed', function () {
                    requestAnimationFrame(function () {
                        setTimeout(scrollToFirstError, 60);
                    });
                });
            }
        });
    })();
</script>

<script src="https://js.stripe.com/v3/"></script>
<script>
    (function () {
        // Funnel-level Stripe.js handler. Lazy-initialised on first card-element mount
        // (which happens when the customer picks "Credit/Debit Card") and reused for
        // subsequent re-renders. The card element re-mounts when Livewire re-renders
        // the payment step, so we re-attach defensively on every snapshot change.
        let stripe = null;
        let elements = null;
        let cardElement = null;
        let mountedContainer = null;
        let mountedPublishableKey = null;

        function ensureStripe(publishableKey) {
            if (!publishableKey || typeof Stripe === 'undefined') {
                return null;
            }
            if (!stripe || mountedPublishableKey !== publishableKey) {
                stripe = Stripe(publishableKey);
                elements = stripe.elements();
                mountedPublishableKey = publishableKey;
                cardElement = null;
                mountedContainer = null;
            }
            return stripe;
        }

        function mountCardElement(publishableKey) {
            const container = document.getElementById('stripe-card-element');
            if (!container) {
                mountedContainer = null;
                return;
            }
            // Skip re-mount if already mounted to this exact container DOM node.
            // The MutationObserver below fires on every body mutation including
            // the iframes Stripe.js itself injects during mount, so without this
            // guard mountCardElement → Stripe mutation → observer → mountCardElement
            // becomes an infinite loop that hangs the tab.
            if (cardElement && mountedContainer === container) {
                return;
            }
            if (!ensureStripe(publishableKey)) {
                container.innerHTML = '<p class="text-sm text-red-600">Card payments are not configured.</p>';
                return;
            }
            if (cardElement) {
                try { cardElement.destroy(); } catch (e) { /* ignore */ }
                cardElement = null;
            }
            cardElement = elements.create('card', {
                style: {
                    base: {
                        fontSize: '16px',
                        color: '#1f2937',
                        '::placeholder': { color: '#9ca3af' },
                    },
                    invalid: { color: '#dc2626' },
                },
            });
            cardElement.mount('#stripe-card-element');
            mountedContainer = container;
            cardElement.on('change', ({ error }) => {
                const err = document.getElementById('stripe-card-errors');
                if (err) {
                    err.textContent = error ? error.message : '';
                }
            });
        }

        document.addEventListener('livewire:init', function () {
            // The funnel exposes its Stripe publishable key via a data attribute on
            // the wrapper. If credit_card is the default selected method at first
            // render, mount immediately; otherwise wait for the user to pick it.
            const wrapper = document.querySelector('[data-funnel-stripe-pk]');
            const initialKey = wrapper ? wrapper.dataset.funnelStripePk : null;

            // Mount when the card element appears in the DOM.
            const observer = new MutationObserver(() => {
                if (document.getElementById('stripe-card-element') && initialKey) {
                    mountCardElement(initialKey);
                }
            });
            observer.observe(document.body, { childList: true, subtree: true });

            // Initial mount if the element is already on the page.
            if (document.getElementById('stripe-card-element') && initialKey) {
                mountCardElement(initialKey);
            }

            Livewire.on('funnel-stripe-charge', async (event) => {
                const payload = Array.isArray(event) ? event[0] : event;
                const { clientSecret, publishableKey, orderId, paymentIntentId, customerEmail, customerName } = payload;

                if (!ensureStripe(publishableKey)) {
                    alert('Stripe is not configured on this site.');
                    return;
                }

                if (!cardElement) {
                    mountCardElement(publishableKey);
                }

                if (!cardElement) {
                    document.getElementById('stripe-card-errors').textContent =
                        'Card input is not available. Please refresh and try again.';
                    return;
                }

                const { error, paymentIntent } = await stripe.confirmCardPayment(clientSecret, {
                    payment_method: {
                        card: cardElement,
                        billing_details: {
                            email: customerEmail || undefined,
                            name: customerName || undefined,
                        },
                    },
                });

                if (error) {
                    document.getElementById('stripe-card-errors').textContent = error.message;
                    // Re-enable the form. processStripePayment left isProcessing=true.
                    Livewire.dispatch('funnel-stripe-charge-failed');
                    return;
                }

                if (paymentIntent && paymentIntent.status === 'succeeded') {
                    // Hand control back to the Livewire component to mark the order
                    // paid + redirect to the next step. The server re-verifies the
                    // payment_intent with Stripe before trusting this call.
                    window.Livewire.find(
                        document.querySelector('[wire\\:id]').getAttribute('wire:id')
                    ).call('confirmStripePayment', orderId, paymentIntentId);
                } else {
                    document.getElementById('stripe-card-errors').textContent =
                        'Payment did not complete. Status: ' + (paymentIntent ? paymentIntent.status : 'unknown');
                    Livewire.dispatch('funnel-stripe-charge-failed');
                }
            });
        });
    })();
</script>
