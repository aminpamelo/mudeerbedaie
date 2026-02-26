<?php

namespace App\Services\Funnel;

use App\Models\Funnel;
use App\Models\FunnelCart;
use App\Models\FunnelOrder;
use App\Models\FunnelSession;
use App\Models\FunnelStep;
use App\Models\FunnelStepOrderBump;
use App\Models\FunnelStepProduct;
use App\Models\PackagePurchase;
use App\Models\ProductOrder;
use App\Models\StockMovement;
use App\Models\StripeCustomer;
use App\Models\User;
use App\Services\SettingsService;
use App\Services\StripeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Stripe\StripeClient;

class FunnelCheckoutService
{
    private StripeClient $stripe;

    public function __construct(
        protected StripeService $stripeService,
        protected SettingsService $settingsService,
        protected FacebookPixelService $pixelService
    ) {
        $this->stripe = $this->stripeService->getStripe();
    }

    /**
     * Create a checkout session for a funnel purchase.
     */
    public function createCheckout(
        FunnelSession $session,
        FunnelStep $step,
        array $selectedProducts,
        array $selectedBumps,
        array $customerData,
        array $billingAddress
    ): array {
        return DB::transaction(function () use ($session, $step, $selectedProducts, $selectedBumps, $customerData, $billingAddress) {
            // Load products and bumps
            $products = FunnelStepProduct::whereIn('id', array_keys($selectedProducts))
                ->where('step_id', $step->id)
                ->where('is_active', true)
                ->get();

            $bumps = FunnelStepOrderBump::whereIn('id', array_keys($selectedBumps))
                ->where('step_id', $step->id)
                ->where('is_active', true)
                ->get();

            // Calculate totals
            $subtotal = $products->sum('funnel_price');
            $bumpsTotal = $bumps->sum('price');
            $total = $subtotal + $bumpsTotal;

            if ($total <= 0) {
                throw new \Exception('Invalid order total');
            }

            // Check and reserve stock for packages and products
            $packagePurchases = $this->reserveStockForProducts($products, $customerData, $session);

            // Create or update cart
            $cart = $this->createOrUpdateCart($session, $step, $products, $bumps, $customerData, $total);

            // Create ProductOrder
            $productOrder = $this->createProductOrder(
                $session,
                $step,
                $products,
                $bumps,
                $customerData,
                $billingAddress,
                $subtotal,
                $bumpsTotal,
                $total
            );

            // Create FunnelOrder for analytics tracking
            $funnelOrder = $this->createFunnelOrder($session, $step, $productOrder, $bumps, $total);

            // Create Stripe Payment Intent
            $paymentIntent = $this->createPaymentIntent(
                $productOrder,
                $customerData,
                $session
            );

            // Update order with payment intent and package purchase references
            $packagePurchaseIds = $packagePurchases->pluck('id')->toArray();
            $productOrder->update([
                'payment_provider' => 'stripe',
                'metadata' => array_merge($productOrder->metadata ?? [], [
                    'stripe_payment_intent_id' => $paymentIntent->id,
                    'package_purchase_ids' => $packagePurchaseIds,
                ]),
            ]);

            // Track checkout initiated event
            $session->trackEvent('checkout_initiated', [
                'order_id' => $productOrder->id,
                'total' => $total,
                'payment_intent_id' => $paymentIntent->id,
            ], $step);

            return [
                'success' => true,
                'order' => $productOrder,
                'funnel_order' => $funnelOrder,
                'payment_intent' => [
                    'id' => $paymentIntent->id,
                    'client_secret' => $paymentIntent->client_secret,
                    'status' => $paymentIntent->status,
                ],
                'total' => $total,
            ];
        });
    }

    /**
     * Create or update the funnel cart.
     */
    protected function createOrUpdateCart(
        FunnelSession $session,
        FunnelStep $step,
        $products,
        $bumps,
        array $customerData,
        float $total
    ): FunnelCart {
        $cartData = [
            'products' => $products->pluck('id')->mapWithKeys(fn ($id) => [$id => true])->toArray(),
            'bumps' => $bumps->pluck('id')->mapWithKeys(fn ($id) => [$id => true])->toArray(),
            'items' => $products->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'price' => $p->funnel_price,
                'type' => $p->type,
            ])->merge($bumps->map(fn ($b) => [
                'id' => $b->id,
                'name' => $b->name,
                'price' => $b->price,
                'is_bump' => true,
            ]))->toArray(),
        ];

        return FunnelCart::updateOrCreate(
            [
                'session_id' => $session->id,
                'funnel_id' => $session->funnel_id,
            ],
            [
                'step_id' => $step->id,
                'email' => $customerData['email'] ?? null,
                'phone' => $customerData['phone'] ?? null,
                'cart_data' => $cartData,
                'total_amount' => $total,
                'recovery_status' => 'pending',
            ]
        );
    }

    /**
     * Create the ProductOrder record.
     */
    protected function createProductOrder(
        FunnelSession $session,
        FunnelStep $step,
        $products,
        $bumps,
        array $customerData,
        array $billingAddress,
        float $subtotal,
        float $bumpsTotal,
        float $total
    ): ProductOrder {
        $user = auth()->user();

        $orderData = [
            'order_number' => ProductOrder::generateOrderNumber(),
            'user_id' => $user?->id,
            'student_id' => $user?->student?->id,
            'email' => $customerData['email'],
            'customer_name' => $customerData['name'] ?? null,
            'customer_phone' => $customerData['phone'] ?? null,
            'billing_address' => $billingAddress,
            'shipping_address' => $billingAddress,
            'subtotal' => $subtotal,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => $total,
            'currency' => $this->stripeService->getCurrency(),
            'status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'credit_card',
            'source' => 'funnel',
            'source_reference' => $session->funnel->slug,
            'hidden_from_admin' => ! $session->funnel->shouldShowOrdersInAdmin(),
            'notes' => 'Funnel purchase: '.$session->funnel->name,
            'metadata' => [
                'funnel_id' => $session->funnel_id,
                'funnel_slug' => $session->funnel->slug,
                'step_id' => $step->id,
                'session_uuid' => $session->uuid,
                'bumps_total' => $bumpsTotal,
            ],
        ];

        $order = ProductOrder::create($orderData);

        // Add product items
        foreach ($products as $product) {
            $order->items()->create([
                'product_id' => $product->product_id,
                'course_id' => $product->course_id,
                'name' => $product->name,
                'description' => $product->description,
                'quantity' => 1,
                'unit_price' => $product->funnel_price,
                'total_price' => $product->funnel_price,
                'metadata' => [
                    'funnel_product_id' => $product->id,
                    'type' => $product->type,
                    'is_recurring' => $product->is_recurring,
                    'billing_interval' => $product->billing_interval,
                    'package_id' => $product->package_id,
                ],
            ]);
        }

        // Add bump items
        foreach ($bumps as $bump) {
            $order->items()->create([
                'product_id' => $bump->product_id,
                'name' => $bump->name,
                'description' => $bump->description,
                'quantity' => 1,
                'unit_price' => $bump->price,
                'total_price' => $bump->price,
                'metadata' => [
                    'order_bump_id' => $bump->id,
                    'is_order_bump' => true,
                ],
            ]);
        }

        return $order;
    }

    /**
     * Create the FunnelOrder for analytics.
     */
    protected function createFunnelOrder(
        FunnelSession $session,
        FunnelStep $step,
        ProductOrder $productOrder,
        $bumps,
        float $total
    ): FunnelOrder {
        return FunnelOrder::create([
            'funnel_id' => $session->funnel_id,
            'session_id' => $session->id,
            'product_order_id' => $productOrder->id,
            'step_id' => $step->id,
            'order_type' => 'main',
            'funnel_revenue' => $total,
            'bumps_offered' => $step->orderBumps()->where('is_active', true)->count(),
            'bumps_accepted' => $bumps->count(),
        ]);
    }

    /**
     * Create a Stripe Payment Intent.
     */
    protected function createPaymentIntent(
        ProductOrder $order,
        array $customerData,
        FunnelSession $session
    ): PaymentIntent {
        try {
            $stripeCustomerId = null;

            // Get or create Stripe customer if user is authenticated
            if (auth()->check()) {
                $stripeCustomer = $this->stripeService->createOrGetCustomer(auth()->user());
                $stripeCustomerId = $stripeCustomer->stripe_customer_id;
            }

            $paymentIntentData = [
                'amount' => (int) ($order->total_amount * 100), // Convert to cents
                'currency' => strtolower($order->currency),
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
                'metadata' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'funnel_id' => $session->funnel_id,
                    'session_uuid' => $session->uuid,
                    'customer_email' => $customerData['email'],
                ],
                'receipt_email' => $customerData['email'],
                'description' => 'Funnel purchase: '.$session->funnel->name,
            ];

            if ($stripeCustomerId) {
                $paymentIntentData['customer'] = $stripeCustomerId;
            }

            return $this->stripe->paymentIntents->create($paymentIntentData);

        } catch (ApiErrorException $e) {
            Log::error('Failed to create Stripe payment intent', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('Payment initialization failed: '.$e->getMessage());
        }
    }

    /**
     * Confirm payment was successful.
     */
    public function confirmPayment(string $paymentIntentId): array
    {
        try {
            $paymentIntent = $this->stripe->paymentIntents->retrieve($paymentIntentId);

            if ($paymentIntent->status !== 'succeeded') {
                return [
                    'success' => false,
                    'status' => $paymentIntent->status,
                    'message' => 'Payment not completed',
                ];
            }

            // Find and update the order
            $order = ProductOrder::where('metadata->stripe_payment_intent_id', $paymentIntentId)
                ->orWhereJsonContains('metadata', ['stripe_payment_intent_id' => $paymentIntentId])
                ->first();

            if ($order) {
                $order->update([
                    'status' => 'confirmed',
                    'payment_status' => 'paid',
                    'paid_at' => now(),
                ]);

                // Complete package purchases (deduct stock + create enrollments)
                $this->completePackagePurchases($order, $paymentIntentId);

                // Deduct stock for regular products
                $this->deductStockForProducts($order);

                // Update funnel session
                $funnelOrder = FunnelOrder::where('product_order_id', $order->id)->first();
                if ($funnelOrder && $funnelOrder->session) {
                    $funnelOrder->session->markAsConverted();
                    $funnelOrder->session->trackEvent('payment_completed', [
                        'order_id' => $order->id,
                        'amount' => $order->total_amount,
                        'payment_intent_id' => $paymentIntentId,
                    ]);
                }

                // Mark cart as recovered
                $cart = FunnelCart::where('session_id', $funnelOrder?->session_id)->first();
                if ($cart) {
                    $cart->markAsRecovered($order);
                }

                // Update analytics
                $this->updateConversionAnalytics($funnelOrder);

                // Track Facebook Pixel Purchase event (server-side)
                if ($funnelOrder && $funnelOrder->session) {
                    $funnel = $funnelOrder->session->funnel;
                    $this->pixelService->trackPurchase(
                        $funnel,
                        $order,
                        $funnelOrder->session,
                        null, // Will generate new event ID
                        url("/f/{$funnel->slug}/thank-you")
                    );

                    // Calculate affiliate commission if applicable
                    $affiliateCommissionService = app(AffiliateCommissionService::class);
                    $affiliateCommissionService->calculateCommission($funnelOrder, $funnelOrder->session);

                    // Trigger funnel automations for purchase completed
                    app(FunnelAutomationService::class)->triggerPurchaseCompleted($order, $funnelOrder->session);
                }
            }

            return [
                'success' => true,
                'status' => 'succeeded',
                'order' => $order,
                'payment_intent' => $paymentIntent,
            ];

        } catch (ApiErrorException $e) {
            Log::error('Failed to confirm payment', [
                'payment_intent_id' => $paymentIntentId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to confirm payment: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Process a one-click upsell (uses saved payment method).
     */
    public function processOneClickUpsell(
        FunnelSession $session,
        FunnelStep $upsellStep,
        FunnelStepProduct $upsellProduct,
        FunnelOrder $originalOrder
    ): array {
        // Can only do one-click if we have a saved payment method
        if (! auth()->check()) {
            return [
                'success' => false,
                'message' => 'Authentication required for one-click purchase',
                'requires_payment' => true,
            ];
        }

        $stripeCustomer = StripeCustomer::forUser(auth()->id())->first();
        if (! $stripeCustomer || ! $stripeCustomer->default_payment_method_id) {
            return [
                'success' => false,
                'message' => 'No saved payment method',
                'requires_payment' => true,
            ];
        }

        try {
            return DB::transaction(function () use ($session, $upsellStep, $upsellProduct, $originalOrder, $stripeCustomer) {
                // Create the upsell order
                $order = ProductOrder::create([
                    'order_number' => ProductOrder::generateOrderNumber(),
                    'user_id' => auth()->id(),
                    'student_id' => auth()->user()->student?->id,
                    'email' => $session->email,
                    'customer_phone' => $originalOrder->productOrder->customer_phone ?? null,
                    'billing_address' => $originalOrder->productOrder->billing_address ?? [],
                    'shipping_address' => $originalOrder->productOrder->shipping_address ?? [],
                    'subtotal' => $upsellProduct->funnel_price,
                    'discount_amount' => 0,
                    'tax_amount' => 0,
                    'total_amount' => $upsellProduct->funnel_price,
                    'currency' => $this->stripeService->getCurrency(),
                    'status' => 'pending',
                    'payment_status' => 'pending',
                    'payment_method' => 'credit_card',
                    'source' => 'funnel',
                    'source_reference' => $session->funnel->slug,
                    'hidden_from_admin' => ! $session->funnel->shouldShowOrdersInAdmin(),
                    'notes' => 'Funnel upsell: '.$upsellProduct->name,
                    'metadata' => [
                        'funnel_id' => $session->funnel_id,
                        'funnel_slug' => $session->funnel->slug,
                        'step_id' => $upsellStep->id,
                        'session_uuid' => $session->uuid,
                        'original_order_id' => $originalOrder->product_order_id,
                        'is_upsell' => true,
                    ],
                ]);

                // Add upsell item
                $order->items()->create([
                    'product_id' => $upsellProduct->product_id,
                    'course_id' => $upsellProduct->course_id,
                    'name' => $upsellProduct->name,
                    'description' => $upsellProduct->description,
                    'quantity' => 1,
                    'unit_price' => $upsellProduct->funnel_price,
                    'total_price' => $upsellProduct->funnel_price,
                    'metadata' => [
                        'funnel_product_id' => $upsellProduct->id,
                        'type' => $upsellProduct->type,
                        'is_upsell' => true,
                        'package_id' => $upsellProduct->package_id,
                    ],
                ]);

                // Reserve stock for upsell packages/products
                $upsellPackagePurchases = $this->reserveStockForProducts(
                    collect([$upsellProduct]),
                    ['email' => $session->email],
                    $session
                );
                $order->update([
                    'metadata' => array_merge($order->metadata ?? [], [
                        'package_purchase_ids' => $upsellPackagePurchases->pluck('id')->toArray(),
                    ]),
                ]);

                // Charge with saved payment method
                $paymentIntent = $this->stripe->paymentIntents->create([
                    'amount' => (int) ($upsellProduct->funnel_price * 100),
                    'currency' => strtolower($this->stripeService->getCurrency()),
                    'customer' => $stripeCustomer->stripe_customer_id,
                    'payment_method' => $stripeCustomer->default_payment_method_id,
                    'off_session' => true,
                    'confirm' => true,
                    'metadata' => [
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                        'funnel_id' => $session->funnel_id,
                        'is_upsell' => true,
                    ],
                ]);

                if ($paymentIntent->status === 'succeeded') {
                    $order->update([
                        'status' => 'confirmed',
                        'payment_status' => 'paid',
                        'paid_at' => now(),
                        'metadata' => array_merge($order->metadata ?? [], [
                            'stripe_payment_intent_id' => $paymentIntent->id,
                        ]),
                    ]);

                    // Complete package purchases and deduct stock for upsell
                    $this->completePackagePurchases($order, $paymentIntent->id);
                    $this->deductStockForProducts($order);

                    // Create FunnelOrder for upsell
                    $funnelOrder = FunnelOrder::create([
                        'funnel_id' => $session->funnel_id,
                        'session_id' => $session->id,
                        'product_order_id' => $order->id,
                        'step_id' => $upsellStep->id,
                        'order_type' => 'upsell',
                        'funnel_revenue' => $upsellProduct->funnel_price,
                    ]);

                    // Track upsell accepted
                    $originalOrder->recordUpsellAccepted();

                    // Track event
                    $session->trackEvent('upsell_accepted', [
                        'upsell_order_id' => $order->id,
                        'product_name' => $upsellProduct->name,
                        'amount' => $upsellProduct->funnel_price,
                    ], $upsellStep);

                    return [
                        'success' => true,
                        'order' => $order,
                        'funnel_order' => $funnelOrder,
                    ];
                }

                throw new \Exception('Payment failed');
            });

        } catch (ApiErrorException $e) {
            Log::error('One-click upsell failed', [
                'session_id' => $session->id,
                'product_id' => $upsellProduct->id,
                'error' => $e->getMessage(),
            ]);

            // Track upsell declined
            $originalOrder->recordUpsellOffered();
            $session->trackEvent('upsell_failed', [
                'product_name' => $upsellProduct->name,
                'error' => $e->getMessage(),
            ], $upsellStep);

            return [
                'success' => false,
                'message' => 'Payment failed: '.$e->getMessage(),
                'requires_payment' => true,
            ];
        }
    }

    /**
     * Decline an upsell offer.
     */
    public function declineUpsell(
        FunnelSession $session,
        FunnelStep $upsellStep,
        FunnelStepProduct $upsellProduct,
        FunnelOrder $originalOrder
    ): void {
        $originalOrder->recordUpsellOffered();

        $session->trackEvent('upsell_declined', [
            'step_id' => $upsellStep->id,
            'product_name' => $upsellProduct->name,
            'product_price' => $upsellProduct->funnel_price,
        ], $upsellStep);
    }

    /**
     * Update conversion analytics.
     */
    protected function updateConversionAnalytics(?FunnelOrder $funnelOrder): void
    {
        if (! $funnelOrder) {
            return;
        }

        // Update analytics - step level
        $stepAnalytics = \App\Models\FunnelAnalytics::getOrCreateForToday(
            $funnelOrder->funnel_id,
            $funnelOrder->step_id
        );
        $stepAnalytics->incrementConversions($funnelOrder->funnel_revenue);

        // Update analytics - funnel level (for summary stats)
        $funnelAnalytics = \App\Models\FunnelAnalytics::getOrCreateForToday($funnelOrder->funnel_id);
        $funnelAnalytics->incrementConversions($funnelOrder->funnel_revenue);
    }

    /**
     * Get the Stripe publishable key for frontend.
     */
    public function getPublishableKey(): string
    {
        return $this->stripeService->getPublishableKey();
    }

    /**
     * Check if Stripe is configured.
     */
    public function isConfigured(): bool
    {
        return $this->stripeService->isConfigured();
    }

    /**
     * Reserve stock for packages and trackable products during checkout.
     *
     * @return \Illuminate\Support\Collection<int, PackagePurchase>
     */
    protected function reserveStockForProducts($products, array $customerData, FunnelSession $session): \Illuminate\Support\Collection
    {
        $packagePurchases = collect();

        foreach ($products as $product) {
            if ($product->package_id) {
                $package = $product->package;
                if (! $package) {
                    continue;
                }

                // Create a pending PackagePurchase to track stock
                $purchase = PackagePurchase::create([
                    'package_id' => $package->id,
                    'user_id' => auth()->id(),
                    'guest_email' => $customerData['email'] ?? null,
                    'amount_paid' => $product->funnel_price,
                    'original_amount' => $package->calculateOriginalPrice(),
                    'discount_amount' => max(0, $package->calculateOriginalPrice() - $product->funnel_price),
                    'currency' => $this->stripeService->getCurrency(),
                    'status' => 'pending',
                    'purchased_at' => now(),
                    'package_snapshot' => $package->load('items')->toArray(),
                    'metadata' => [
                        'source' => 'funnel',
                        'funnel_id' => $session->funnel_id,
                        'funnel_product_id' => $product->id,
                        'session_uuid' => $session->uuid,
                    ],
                ]);

                // Allocate stock (reserve)
                $purchase->allocateStock();

                $packagePurchases->push($purchase);
            } elseif ($product->product_id && $product->product?->track_quantity) {
                // Reserve stock for regular products
                $this->reserveProductStock($product->product, 1);
            }
        }

        return $packagePurchases;
    }

    /**
     * Reserve stock for a single product.
     */
    protected function reserveProductStock(\App\Models\Product $product, int $quantity): void
    {
        $stockLevel = $product->stockLevels()->first();

        if ($stockLevel && $stockLevel->available_quantity >= $quantity) {
            $stockLevel->increment('reserved_quantity', $quantity);
            $stockLevel->decrement('available_quantity', $quantity);
        }
    }

    /**
     * Complete package purchases after payment confirmation.
     */
    protected function completePackagePurchases(ProductOrder $order, string $paymentIntentId): void
    {
        $packagePurchaseIds = $order->metadata['package_purchase_ids'] ?? [];

        if (empty($packagePurchaseIds)) {
            return;
        }

        foreach ($packagePurchaseIds as $purchaseId) {
            $purchase = PackagePurchase::find($purchaseId);
            if (! $purchase || $purchase->isCompleted()) {
                continue;
            }

            $purchase->update([
                'stripe_payment_intent_id' => $paymentIntentId,
                'product_order_id' => $order->id,
            ]);

            $purchase->markAsCompleted();

            Log::info('Package purchase completed via funnel', [
                'purchase_id' => $purchase->id,
                'package_id' => $purchase->package_id,
                'order_id' => $order->id,
            ]);
        }
    }

    /**
     * Deduct stock for regular (non-package) products after payment.
     */
    protected function deductStockForProducts(ProductOrder $order): void
    {
        foreach ($order->items as $item) {
            // Skip package items (handled by PackagePurchase)
            if (! empty($item->metadata['package_id'])) {
                continue;
            }

            if (! $item->product_id) {
                continue;
            }

            $product = \App\Models\Product::find($item->product_id);
            if (! $product || ! $product->track_quantity) {
                continue;
            }

            $stockLevel = $product->stockLevels()->first();
            if (! $stockLevel) {
                continue;
            }

            $quantity = $item->quantity ?? 1;

            // Deduct from quantity and reserved
            $stockLevel->decrement('quantity', $quantity);
            $stockLevel->decrement('reserved_quantity', $quantity);

            // Create stock movement record
            StockMovement::create([
                'product_id' => $product->id,
                'warehouse_id' => $stockLevel->warehouse_id,
                'type' => 'sale',
                'quantity_change' => -$quantity,
                'quantity_after' => $stockLevel->quantity,
                'reference_type' => ProductOrder::class,
                'reference_id' => $order->id,
                'notes' => "Funnel sale: Order #{$order->order_number}",
                'metadata' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'source' => 'funnel',
                ],
            ]);
        }
    }

    /**
     * Release reserved stock when a funnel order fails or is cancelled.
     */
    public function releaseOrderStock(ProductOrder $order): void
    {
        // Release package purchase stock
        $packagePurchaseIds = $order->metadata['package_purchase_ids'] ?? [];
        foreach ($packagePurchaseIds as $purchaseId) {
            $purchase = PackagePurchase::find($purchaseId);
            if ($purchase && ! $purchase->isCompleted()) {
                $purchase->markAsFailed('Funnel payment failed');
            }
        }

        // Release regular product stock
        foreach ($order->items as $item) {
            if (! empty($item->metadata['package_id'])) {
                continue;
            }

            if (! $item->product_id) {
                continue;
            }

            $product = \App\Models\Product::find($item->product_id);
            if (! $product || ! $product->track_quantity) {
                continue;
            }

            $stockLevel = $product->stockLevels()->first();
            if ($stockLevel) {
                $quantity = $item->quantity ?? 1;
                $stockLevel->decrement('reserved_quantity', $quantity);
                $stockLevel->increment('available_quantity', $quantity);
            }
        }
    }
}
