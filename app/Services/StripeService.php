<?php

namespace App\Services;

use App\Mail\PaymentConfirmation;
use App\Mail\PaymentFailed;
use App\Models\Course;
use App\Models\CourseFeeSettings;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentMethod;
use App\Models\StripeCustomer;
use App\Models\User;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class StripeService
{
    private StripeClient $stripe;

    private SettingsService $settingsService;

    public function __construct(SettingsService $settingsService)
    {
        $this->settingsService = $settingsService;
        $this->initializeStripe();
    }

    private function initializeStripe(): void
    {
        $secretKey = $this->settingsService->get('stripe_secret_key');

        if (! $secretKey) {
            throw new \Exception('Stripe secret key not configured. Please configure Stripe settings first.');
        }

        $this->stripe = new StripeClient($secretKey);
    }

    public function isConfigured(): bool
    {
        $publishableKey = $this->settingsService->get('stripe_publishable_key');
        $secretKey = $this->settingsService->get('stripe_secret_key');

        return ! empty($publishableKey) && ! empty($secretKey);
    }

    public function getPublishableKey(): string
    {
        return $this->settingsService->get('stripe_publishable_key', '');
    }

    public function getCurrency(): string
    {
        return $this->settingsService->get('currency', 'MYR');
    }

    public function isLiveMode(): bool
    {
        return $this->settingsService->get('payment_mode', 'test') === 'live';
    }

    // Customer Management
    public function createOrGetCustomer(User $user): StripeCustomer
    {
        // Check if user already has a Stripe customer
        $stripeCustomer = StripeCustomer::forUser($user->id)->first();

        if ($stripeCustomer) {
            return $stripeCustomer;
        }

        try {
            // Create customer in Stripe
            $customer = $this->stripe->customers->create([
                'email' => $user->email,
                'name' => $user->name,
                'metadata' => [
                    'user_id' => $user->id,
                    'created_by' => 'mudeer_bedaie_system',
                ],
            ]);

            // Store customer in our database
            return StripeCustomer::createForUser($user, $customer->id, [
                'email' => $customer->email,
                'name' => $customer->name,
                'created' => $customer->created,
            ]);

        } catch (ApiErrorException $e) {
            Log::error('Failed to create Stripe customer', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('Failed to create customer: '.$e->getMessage());
        }
    }

    // Product Management
    public function createProduct(Course $course): string
    {
        if (! $this->isConfigured()) {
            throw new \Exception('Stripe is not properly configured.');
        }

        try {
            $product = $this->stripe->products->create([
                'name' => $course->name,
                'description' => $course->description,
                'active' => $course->isActive(),
                'metadata' => [
                    'course_id' => $course->id,
                    'system' => 'mudeer_bedaie',
                ],
            ]);

            $course->markStripeSyncAsCompleted($product->id);

            Log::info('Stripe product created successfully', [
                'course_id' => $course->id,
                'stripe_product_id' => $product->id,
            ]);

            return $product->id;

        } catch (ApiErrorException $e) {
            $course->markStripeSyncAsFailed();
            Log::error('Failed to create Stripe product', [
                'course_id' => $course->id,
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('Failed to create product: '.$e->getMessage());
        }
    }

    public function updateProduct(Course $course): void
    {
        if (! $course->stripe_product_id) {
            throw new \Exception('Course does not have a Stripe product ID');
        }

        if (! $this->isConfigured()) {
            throw new \Exception('Stripe is not properly configured.');
        }

        try {
            Log::info('Attempting to update Stripe product', [
                'course_id' => $course->id,
                'stripe_product_id' => $course->stripe_product_id,
                'course_name' => $course->name,
                'course_active' => $course->isActive(),
            ]);

            $updateData = [
                'name' => $course->name,
                'description' => $course->description,
                'active' => $course->isActive(),
            ];

            $updatedProduct = $this->stripe->products->update($course->stripe_product_id, $updateData);

            // Verify the update was successful
            if ($updatedProduct->id !== $course->stripe_product_id) {
                throw new \Exception('Product update returned unexpected ID');
            }

            $course->update(['stripe_last_synced_at' => now()]);

            Log::info('Stripe product updated successfully', [
                'course_id' => $course->id,
                'stripe_product_id' => $course->stripe_product_id,
                'updated_name' => $updatedProduct->name,
                'updated_active' => $updatedProduct->active,
                'last_synced_at' => $course->fresh()->stripe_last_synced_at,
            ]);

        } catch (ApiErrorException $e) {
            $errorDetails = [
                'course_id' => $course->id,
                'stripe_product_id' => $course->stripe_product_id,
                'error_type' => $e->getStripeCode(),
                'error_message' => $e->getMessage(),
                'http_status' => $e->getHttpStatus(),
            ];

            // Add specific error details based on error type
            if ($e->getStripeCode() === 'resource_missing') {
                $errorDetails['suggestion'] = 'The Stripe product may have been deleted. Try creating a new product.';
            } elseif ($e->getStripeCode() === 'api_key_expired') {
                $errorDetails['suggestion'] = 'Check Stripe API key configuration in settings.';
            }

            Log::error('Failed to update Stripe product', $errorDetails);
            throw new \Exception('Failed to update product: '.$e->getMessage().' (Error: '.$e->getStripeCode().')');
        }
    }

    // Price Management
    public function createPrice(CourseFeeSettings $feeSettings): string
    {
        if (! $feeSettings->course->stripe_product_id) {
            throw new \Exception('Course must have a Stripe product ID before creating prices');
        }

        if (! $this->isConfigured()) {
            throw new \Exception('Stripe is not properly configured.');
        }

        try {
            $currency = $feeSettings->currency ?? $this->getCurrency();
            $unitAmount = $this->convertToStripeAmount($feeSettings->fee_amount);

            Log::info('Attempting to create Stripe price', [
                'course_fee_settings_id' => $feeSettings->id,
                'course_id' => $feeSettings->course_id,
                'stripe_product_id' => $feeSettings->course->stripe_product_id,
                'unit_amount' => $unitAmount,
                'original_fee_amount' => $feeSettings->fee_amount,
                'currency' => $currency,
                'billing_cycle' => $feeSettings->billing_cycle,
                'interval' => $feeSettings->getStripeInterval(),
                'interval_count' => $feeSettings->getStripeIntervalCount(),
                'trial_period_days' => $feeSettings->trial_period_days,
            ]);

            $priceData = [
                'product' => $feeSettings->course->stripe_product_id,
                'unit_amount' => $unitAmount,
                'currency' => strtolower($currency),
                'recurring' => [
                    'interval' => $feeSettings->getStripeInterval(),
                    'interval_count' => $feeSettings->getStripeIntervalCount(),
                ],
                'metadata' => [
                    'course_fee_settings_id' => $feeSettings->id,
                    'course_id' => $feeSettings->course_id,
                    'billing_cycle' => $feeSettings->billing_cycle,
                    'system' => 'mudeer_bedaie',
                    'created_at' => now()->toISOString(),
                ],
            ];

            // Add trial period if specified
            if ($feeSettings->hasTrialPeriod()) {
                $priceData['recurring']['trial_period_days'] = $feeSettings->trial_period_days;
            }

            $price = $this->stripe->prices->create($priceData);

            // Verify the price was created correctly
            if ($price->unit_amount !== $unitAmount) {
                throw new \Exception('Price created with incorrect amount');
            }

            $feeSettings->update(['stripe_price_id' => $price->id]);

            Log::info('Stripe price created successfully', [
                'course_fee_settings_id' => $feeSettings->id,
                'stripe_price_id' => $price->id,
                'created_amount' => $price->unit_amount,
                'created_currency' => $price->currency,
                'created_interval' => $price->recurring->interval,
                'created_interval_count' => $price->recurring->interval_count,
            ]);

            return $price->id;

        } catch (ApiErrorException $e) {
            $errorDetails = [
                'course_fee_settings_id' => $feeSettings->id,
                'course_id' => $feeSettings->course_id,
                'stripe_product_id' => $feeSettings->course->stripe_product_id,
                'error_type' => $e->getStripeCode(),
                'error_message' => $e->getMessage(),
                'http_status' => $e->getHttpStatus(),
                'attempted_unit_amount' => $this->convertToStripeAmount($feeSettings->fee_amount),
                'attempted_currency' => $feeSettings->currency ?? $this->getCurrency(),
            ];

            // Add specific error suggestions
            if ($e->getStripeCode() === 'invalid_request_error') {
                $errorDetails['suggestion'] = 'Check that the product exists and currency is supported.';
            } elseif (strpos($e->getMessage(), 'currency') !== false) {
                $errorDetails['suggestion'] = 'Verify that the currency code is valid and supported by Stripe.';
            }

            Log::error('Failed to create Stripe price', $errorDetails);
            throw new \Exception('Failed to create price: '.$e->getMessage().' (Error: '.$e->getStripeCode().')');
        }
    }

    // Subscription Management
    public function createSubscription(Enrollment $enrollment, PaymentMethod $paymentMethod): array
    {
        if (! $this->isConfigured()) {
            throw new \Exception('Stripe is not properly configured.');
        }

        if (! $enrollment->course->feeSettings->stripe_price_id) {
            throw new \Exception('Course must have a Stripe price ID before creating subscription');
        }

        $stripeCustomer = $this->createOrGetCustomer($enrollment->student->user);

        try {
            $subscriptionData = [
                'customer' => $stripeCustomer->stripe_customer_id,
                'items' => [
                    [
                        'price' => $enrollment->course->feeSettings->stripe_price_id,
                    ],
                ],
                'payment_behavior' => 'allow_incomplete',
                'off_session' => true, // Try to charge automatically
                'payment_settings' => [
                    'payment_method_types' => ['card'],
                    'save_default_payment_method' => 'on_subscription',
                    'payment_method_options' => [
                        'card' => [
                            'request_three_d_secure' => 'automatic',
                        ],
                    ],
                ],
                'expand' => ['latest_invoice.payment_intent'],
                'metadata' => [
                    'enrollment_id' => $enrollment->id,
                    'student_id' => $enrollment->student_id,
                    'course_id' => $enrollment->course_id,
                    'system' => 'mudeer_bedaie',
                ],
            ];

            // Attach payment method if provided
            if ($paymentMethod->stripe_payment_method_id) {
                $subscriptionData['default_payment_method'] = $paymentMethod->stripe_payment_method_id;
            }

            $subscription = $this->stripe->subscriptions->create($subscriptionData);

            // Update enrollment with subscription details
            $enrollment->update([
                'stripe_subscription_id' => $subscription->id,
                'subscription_status' => $subscription->status,
            ]);

            Log::info('Stripe subscription created successfully', [
                'enrollment_id' => $enrollment->id,
                'stripe_subscription_id' => $subscription->id,
            ]);

            return [
                'subscription' => $subscription,
                'client_secret' => $subscription->latest_invoice?->payment_intent?->client_secret,
            ];

        } catch (ApiErrorException $e) {
            Log::error('Failed to create Stripe subscription', [
                'enrollment_id' => $enrollment->id,
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('Failed to create subscription: '.$e->getMessage());
        }
    }

    public function cancelSubscription(string $subscriptionId, bool $immediately = false): array
    {
        try {
            // First retrieve the subscription to check its status
            $subscription = $this->stripe->subscriptions->retrieve($subscriptionId);

            // Handle incomplete subscriptions differently - they must be deleted
            if ($subscription->status === 'incomplete' || $subscription->status === 'incomplete_expired') {
                $this->stripe->subscriptions->cancel($subscriptionId);
                Log::info('Stripe incomplete subscription deleted', [
                    'subscription_id' => $subscriptionId,
                    'status' => $subscription->status,
                ]);

                return [
                    'success' => true,
                    'immediately' => true,
                    'message' => 'Incomplete subscription canceled immediately.',
                ];
            } else {
                // For other statuses, use regular cancellation
                if ($immediately) {
                    $this->stripe->subscriptions->cancel($subscriptionId);

                    Log::info('Stripe subscription canceled immediately', [
                        'subscription_id' => $subscriptionId,
                        'status' => $subscription->status,
                        'immediately' => true,
                    ]);

                    return [
                        'success' => true,
                        'immediately' => true,
                        'message' => 'Subscription canceled immediately.',
                    ];
                } else {
                    $this->stripe->subscriptions->update($subscriptionId, [
                        'cancel_at_period_end' => true,
                    ]);

                    Log::info('Stripe subscription scheduled for cancellation', [
                        'subscription_id' => $subscriptionId,
                        'status' => $subscription->status,
                        'cancel_at_period_end' => true,
                    ]);

                    // Get the updated subscription to retrieve cancel_at timestamp
                    $updatedSubscription = $this->stripe->subscriptions->retrieve($subscriptionId);

                    return [
                        'success' => true,
                        'immediately' => false,
                        'cancel_at' => $updatedSubscription->cancel_at,
                        'message' => 'Subscription scheduled for cancellation at the end of the current billing period. It will remain active until then.',
                    ];
                }
            }

        } catch (ApiErrorException $e) {
            // If subscription doesn't exist, it's already canceled/expired
            if ($e->getStripeCode() === 'resource_missing') {
                // Update enrollment status to reflect the expired subscription
                $enrollment = Enrollment::where('stripe_subscription_id', $subscriptionId)->first();
                if ($enrollment) {
                    $enrollment->updateSubscriptionStatus('incomplete_expired');
                }

                Log::info('Subscription already deleted/expired in Stripe', [
                    'subscription_id' => $subscriptionId,
                ]);

                return [
                    'success' => true,
                    'immediately' => true,
                    'message' => 'Subscription was already canceled or expired.',
                ];
            }

            Log::error('Failed to cancel Stripe subscription', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('Failed to cancel subscription: '.$e->getMessage());
        }
    }

    public function undoCancellation(string $subscriptionId): array
    {
        try {
            // Remove the cancel_at_period_end flag to reactivate the subscription
            $subscription = $this->stripe->subscriptions->update($subscriptionId, [
                'cancel_at_period_end' => false,
            ]);

            Log::info('Stripe subscription cancellation undone', [
                'subscription_id' => $subscriptionId,
                'status' => $subscription->status,
                'cancel_at_period_end' => false,
            ]);

            return [
                'success' => true,
                'message' => 'Subscription cancellation has been undone. The subscription will continue normally.',
            ];

        } catch (ApiErrorException $e) {
            Log::error('Failed to undo Stripe subscription cancellation', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('Failed to undo subscription cancellation: '.$e->getMessage());
        }
    }

    public function getSubscriptionDetails(string $subscriptionId): array
    {
        try {
            $subscription = $this->stripe->subscriptions->retrieve($subscriptionId);

            return [
                'id' => $subscription->id,
                'status' => $subscription->status,
                'cancel_at_period_end' => $subscription->cancel_at_period_end,
                'cancel_at' => $subscription->cancel_at,
                'current_period_end' => $subscription->current_period_end,
                'current_period_start' => $subscription->current_period_start,
            ];

        } catch (ApiErrorException $e) {
            if ($e->getStripeCode() === 'resource_missing') {
                return [
                    'status' => 'not_found',
                    'cancel_at_period_end' => false,
                    'cancel_at' => null,
                ];
            }

            throw new \Exception('Failed to retrieve subscription: '.$e->getMessage());
        }
    }

    public function confirmSubscriptionPayment(string $subscriptionId): array
    {
        try {
            // Retrieve the subscription
            $subscription = $this->stripe->subscriptions->retrieve($subscriptionId, [
                'expand' => ['latest_invoice.payment_intent'],
            ]);

            if ($subscription->status !== 'incomplete') {
                return [
                    'success' => false,
                    'error' => 'Subscription is not in incomplete status',
                    'status' => $subscription->status,
                ];
            }

            $latestInvoice = $subscription->latest_invoice;
            if (! $latestInvoice) {
                return [
                    'success' => false,
                    'error' => 'No invoice found for subscription',
                ];
            }

            $paymentIntent = $latestInvoice->payment_intent;
            if (! $paymentIntent) {
                return [
                    'success' => false,
                    'error' => 'No payment intent found for invoice',
                ];
            }

            // If payment intent is already succeeded, subscription should be active
            if ($paymentIntent->status === 'succeeded') {
                return [
                    'success' => true,
                    'message' => 'Payment already succeeded',
                    'subscription' => $subscription,
                ];
            }

            // If payment intent requires confirmation, try to confirm it
            if ($paymentIntent->status === 'requires_confirmation') {
                $confirmedPaymentIntent = $this->stripe->paymentIntents->confirm($paymentIntent->id);

                Log::info('Payment intent confirmed', [
                    'payment_intent_id' => $paymentIntent->id,
                    'subscription_id' => $subscriptionId,
                    'status' => $confirmedPaymentIntent->status,
                ]);

                return [
                    'success' => true,
                    'message' => 'Payment confirmed successfully',
                    'payment_intent' => $confirmedPaymentIntent,
                ];
            }

            // If payment intent requires payment method, we can't auto-confirm
            if ($paymentIntent->status === 'requires_payment_method') {
                return [
                    'success' => false,
                    'error' => 'Payment method required - customer must complete payment setup',
                    'requires_action' => true,
                ];
            }

            // If payment intent requires action (3D Secure), we can't auto-confirm
            if ($paymentIntent->status === 'requires_action') {
                return [
                    'success' => false,
                    'error' => 'Customer authentication required - 3D Secure or similar',
                    'requires_action' => true,
                    'client_secret' => $paymentIntent->client_secret,
                ];
            }

            return [
                'success' => false,
                'error' => 'Payment intent in unexpected status: '.$paymentIntent->status,
                'status' => $paymentIntent->status,
            ];

        } catch (ApiErrorException $e) {
            Log::error('Failed to confirm subscription payment', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
                'stripe_code' => $e->getStripeCode(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'stripe_code' => $e->getStripeCode(),
            ];
        }
    }

    public function retryFailedPayment(string $invoiceId): array
    {
        try {
            // Retrieve the invoice
            $invoice = $this->stripe->invoices->retrieve($invoiceId);

            if ($invoice->status !== 'open') {
                throw new \Exception('Invoice is not in a retryable state');
            }

            // Attempt to pay the invoice
            $paidInvoice = $this->stripe->invoices->pay($invoiceId);

            Log::info('Failed payment retried successfully', [
                'invoice_id' => $invoiceId,
                'status' => $paidInvoice->status,
            ]);

            return [
                'success' => true,
                'invoice' => $paidInvoice,
                'message' => 'Payment retry successful',
            ];

        } catch (ApiErrorException $e) {
            Log::error('Failed to retry payment', [
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Payment retry failed',
            ];
        }
    }

    public function updateSubscriptionPaymentMethod(string $subscriptionId, string $paymentMethodId): void
    {
        try {
            // First, attach the payment method to the customer if not already attached
            $subscription = $this->stripe->subscriptions->retrieve($subscriptionId);

            $this->stripe->paymentMethods->attach($paymentMethodId, [
                'customer' => $subscription->customer,
            ]);

            // Update the subscription's default payment method
            $this->stripe->subscriptions->update($subscriptionId, [
                'default_payment_method' => $paymentMethodId,
            ]);

            // Set as customer's default payment method for invoices
            $this->stripe->customers->update($subscription->customer, [
                'invoice_settings' => [
                    'default_payment_method' => $paymentMethodId,
                ],
            ]);

            Log::info('Subscription payment method updated', [
                'subscription_id' => $subscriptionId,
                'payment_method_id' => $paymentMethodId,
            ]);

        } catch (ApiErrorException $e) {
            Log::error('Failed to update subscription payment method', [
                'subscription_id' => $subscriptionId,
                'payment_method_id' => $paymentMethodId,
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('Failed to update payment method: '.$e->getMessage());
        }
    }

    // Order Management (from Stripe invoices)
    public function createOrderFromStripeInvoice(array $stripeInvoice): ?Order
    {
        // Find the enrollment from subscription metadata
        if (! isset($stripeInvoice['subscription'])) {
            Log::warning('Stripe invoice without subscription', ['invoice_id' => $stripeInvoice['id']]);

            return null;
        }

        $enrollment = Enrollment::where('stripe_subscription_id', $stripeInvoice['subscription'])->first();
        if (! $enrollment) {
            Log::warning('Enrollment not found for Stripe subscription', [
                'subscription_id' => $stripeInvoice['subscription'],
                'invoice_id' => $stripeInvoice['id'],
            ]);

            return null;
        }

        // Check if order already exists
        $existingOrder = Order::where('stripe_invoice_id', $stripeInvoice['id'])->first();
        if ($existingOrder) {
            return $existingOrder;
        }

        try {
            $order = Order::createFromStripeInvoice($stripeInvoice, $enrollment);

            // Create order items from invoice line items
            if (isset($stripeInvoice['lines']['data'])) {
                foreach ($stripeInvoice['lines']['data'] as $lineItem) {
                    OrderItem::createFromStripeLineItem($order, $lineItem);
                }
            }

            Log::info('Order created from Stripe invoice', [
                'order_id' => $order->id,
                'stripe_invoice_id' => $stripeInvoice['id'],
            ]);

            return $order;

        } catch (\Exception $e) {
            Log::error('Failed to create order from Stripe invoice', [
                'stripe_invoice_id' => $stripeInvoice['id'],
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    // Payment Method Management
    public function createPaymentMethodFromToken(User $user, string $token): PaymentMethod
    {
        $stripeCustomer = $this->createOrGetCustomer($user);

        try {
            // Create payment method from token
            $paymentMethod = $this->stripe->paymentMethods->create([
                'type' => 'card',
                'card' => ['token' => $token],
            ]);

            // Attach to customer
            $this->stripe->paymentMethods->attach($paymentMethod->id, [
                'customer' => $stripeCustomer->stripe_customer_id,
            ]);

            // Store in our database
            return PaymentMethod::create([
                'user_id' => $user->id,
                'stripe_customer_id' => $stripeCustomer->id,
                'type' => PaymentMethod::TYPE_STRIPE_CARD,
                'stripe_payment_method_id' => $paymentMethod->id,
                'card_details' => [
                    'brand' => $paymentMethod->card->brand,
                    'last4' => $paymentMethod->card->last4,
                    'exp_month' => $paymentMethod->card->exp_month,
                    'exp_year' => $paymentMethod->card->exp_year,
                    'funding' => $paymentMethod->card->funding,
                    'country' => $paymentMethod->card->country,
                ],
                'is_default' => $user->paymentMethods()->count() === 0, // First method is default
            ]);

        } catch (ApiErrorException $e) {
            Log::error('Failed to create payment method from token', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('Failed to create payment method: '.$e->getMessage());
        }
    }

    public function createPaymentMethod(User $user, array $cardDetails): PaymentMethod
    {
        $stripeCustomer = $this->createOrGetCustomer($user);

        try {
            // Create payment method in Stripe
            $paymentMethod = $this->stripe->paymentMethods->create([
                'type' => 'card',
                'card' => $cardDetails,
            ]);

            // Attach to customer
            $this->stripe->paymentMethods->attach($paymentMethod->id, [
                'customer' => $stripeCustomer->stripe_customer_id,
            ]);

            // Store in our database
            return PaymentMethod::create([
                'user_id' => $user->id,
                'stripe_customer_id' => $stripeCustomer->id,
                'type' => PaymentMethod::TYPE_STRIPE_CARD,
                'stripe_payment_method_id' => $paymentMethod->id,
                'card_details' => [
                    'brand' => $paymentMethod->card->brand,
                    'last4' => $paymentMethod->card->last4,
                    'exp_month' => $paymentMethod->card->exp_month,
                    'exp_year' => $paymentMethod->card->exp_year,
                    'funding' => $paymentMethod->card->funding,
                    'country' => $paymentMethod->card->country,
                ],
                'is_default' => $user->paymentMethods()->count() === 0, // First method is default
            ]);

        } catch (ApiErrorException $e) {
            Log::error('Failed to create payment method', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('Failed to create payment method: '.$e->getMessage());
        }
    }

    public function deletePaymentMethod(PaymentMethod $paymentMethod): bool
    {
        try {
            if ($paymentMethod->stripe_payment_method_id) {
                $this->stripe->paymentMethods->detach($paymentMethod->stripe_payment_method_id);
            }

            $paymentMethod->delete();

            return true;

        } catch (ApiErrorException $e) {
            Log::error('Failed to delete payment method', [
                'payment_method_id' => $paymentMethod->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    // Webhook Handling
    public function handleWebhook(string $payload, string $signature): void
    {
        $webhookSecret = $this->settingsService->get('stripe_webhook_secret');

        if (! $webhookSecret) {
            throw new \Exception('Webhook secret not configured.');
        }

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $signature,
                $webhookSecret
            );

            // Check if we already processed this event
            $webhookEvent = WebhookEvent::where('stripe_event_id', $event->id)->first();

            if ($webhookEvent && $webhookEvent->processed) {
                Log::info('Webhook event already processed', ['event_id' => $event->id]);

                return;
            }

            // Create or update webhook event record
            if (! $webhookEvent) {
                $webhookEvent = WebhookEvent::createFromStripeEvent($event);
            }

            switch ($event->type) {
                case 'customer.updated':
                    $this->handleCustomerUpdated($event->data->object);
                    break;

                case 'invoice.payment_succeeded':
                    $this->handleInvoicePaymentSucceeded($event->data->object);
                    break;

                case 'invoice.payment_failed':
                    $this->handleInvoicePaymentFailed($event->data->object);
                    break;

                case 'customer.subscription.created':
                    $this->handleSubscriptionCreated($event->data->object);
                    break;

                case 'customer.subscription.updated':
                    $this->handleSubscriptionUpdated($event->data->object);
                    break;

                case 'customer.subscription.deleted':
                    $this->handleSubscriptionDeleted($event->data->object);
                    break;

                case 'customer.subscription.trial_will_end':
                    $this->handleSubscriptionTrialWillEnd($event->data->object);
                    break;

                default:
                    Log::info('Unhandled webhook event', [
                        'type' => $event->type,
                        'id' => $event->id,
                    ]);
            }

            // Mark webhook event as processed
            $webhookEvent->markAsProcessed();

            Log::info('Webhook processed successfully', [
                'event_id' => $event->id,
                'type' => $event->type,
            ]);

        } catch (\Exception $e) {
            // Mark webhook event as failed if we have the webhook event record
            if (isset($webhookEvent)) {
                $webhookEvent->markAsFailed($e->getMessage());
            }

            Log::error('Webhook handling failed', [
                'event_id' => $event->id ?? null,
                'type' => $event->type ?? null,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function handlePaymentIntentSucceeded($paymentIntent): void
    {
        $payment = Payment::where('stripe_payment_intent_id', $paymentIntent->id)->first();

        if (! $payment) {
            Log::warning('Payment not found for succeeded payment intent', [
                'payment_intent_id' => $paymentIntent->id,
            ]);

            return;
        }

        $payment->update([
            'status' => Payment::STATUS_SUCCEEDED,
            'stripe_charge_id' => $paymentIntent->latest_charge,
            'stripe_fee' => $this->calculateStripeFee($paymentIntent),
            'net_amount' => $payment->amount - $this->calculateStripeFee($paymentIntent),
            'paid_at' => now(),
            'receipt_url' => $paymentIntent->charges->data[0]->receipt_url ?? null,
        ]);

        // Mark invoice as paid
        $payment->invoice->markAsPaid();

        // Send payment confirmation email
        try {
            Mail::to($payment->user->email)->send(new PaymentConfirmation($payment));
        } catch (\Exception $e) {
            Log::error('Failed to send payment confirmation email', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
        }

        Log::info('Payment succeeded', ['payment_id' => $payment->id]);
    }

    private function handlePaymentIntentFailed($paymentIntent): void
    {
        $payment = Payment::where('stripe_payment_intent_id', $paymentIntent->id)->first();

        if (! $payment) {
            return;
        }

        $payment->update([
            'status' => Payment::STATUS_FAILED,
            'failure_reason' => $paymentIntent->last_payment_error,
            'failed_at' => now(),
        ]);

        // Send payment failed email
        try {
            Mail::to($payment->user->email)->send(new PaymentFailed($payment));
        } catch (\Exception $e) {
            Log::error('Failed to send payment failed email', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
        }

        Log::info('Payment failed', ['payment_id' => $payment->id]);
    }

    private function handlePaymentIntentRequiresAction($paymentIntent): void
    {
        $payment = Payment::where('stripe_payment_intent_id', $paymentIntent->id)->first();

        if (! $payment) {
            return;
        }

        $payment->update([
            'status' => Payment::STATUS_REQUIRES_ACTION,
        ]);
    }

    private function handlePaymentIntentCanceled($paymentIntent): void
    {
        $payment = Payment::where('stripe_payment_intent_id', $paymentIntent->id)->first();

        if (! $payment) {
            return;
        }

        $payment->update([
            'status' => Payment::STATUS_CANCELLED,
            'failed_at' => now(),
            'failure_reason' => ['reason' => 'Payment canceled by customer or due to timeout'],
        ]);

        Log::info('Payment intent canceled', ['payment_id' => $payment->id]);
    }

    private function handleChargeSucceeded($charge): void
    {
        $payment = Payment::where('stripe_payment_intent_id', $charge->payment_intent)->first();

        if (! $payment) {
            return;
        }

        // Update payment with charge details
        $payment->update([
            'stripe_charge_id' => $charge->id,
            'receipt_url' => $charge->receipt_url,
            'stripe_fee' => isset($charge->application_fee_amount) ? $charge->application_fee_amount / 100 : 0,
        ]);

        Log::info('Charge succeeded, payment updated with receipt', ['payment_id' => $payment->id]);
    }

    private function handleChargeFailed($charge): void
    {
        $payment = Payment::where('stripe_payment_intent_id', $charge->payment_intent)->first();

        if (! $payment) {
            return;
        }

        $payment->update([
            'stripe_charge_id' => $charge->id,
            'failure_reason' => [
                'code' => $charge->failure_code,
                'message' => $charge->failure_message,
                'reason' => $charge->outcome->reason ?? null,
            ],
        ]);

        Log::info('Charge failed', [
            'payment_id' => $payment->id,
            'failure_code' => $charge->failure_code,
        ]);
    }

    private function handlePaymentMethodAttached($paymentMethod): void
    {
        // Find the customer and sync payment methods
        $stripeCustomer = StripeCustomer::where('stripe_customer_id', $paymentMethod->customer)->first();

        if (! $stripeCustomer) {
            return;
        }

        // Update or create payment method record
        PaymentMethod::updateOrCreate(
            ['stripe_payment_method_id' => $paymentMethod->id],
            [
                'user_id' => $stripeCustomer->user_id,
                'stripe_customer_id' => $stripeCustomer->id,
                'type' => PaymentMethod::TYPE_STRIPE_CARD,
                'card_details' => [
                    'brand' => $paymentMethod->card->brand,
                    'last4' => $paymentMethod->card->last4,
                    'exp_month' => $paymentMethod->card->exp_month,
                    'exp_year' => $paymentMethod->card->exp_year,
                    'funding' => $paymentMethod->card->funding,
                    'country' => $paymentMethod->card->country,
                ],
            ]
        );

        Log::info('Payment method attached via webhook', [
            'user_id' => $stripeCustomer->user_id,
            'payment_method_id' => $paymentMethod->id,
        ]);
    }

    private function handleCustomerUpdated($customer): void
    {
        $stripeCustomer = StripeCustomer::where('stripe_customer_id', $customer->id)->first();

        if (! $stripeCustomer) {
            return;
        }

        // Sync customer data
        $stripeCustomer->syncFromStripeData([
            'email' => $customer->email,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'default_source' => $customer->default_source,
            'default_payment_method' => $customer->invoice_settings->default_payment_method ?? null,
        ]);

        Log::info('Customer updated via webhook', ['user_id' => $stripeCustomer->user_id]);
    }

    private function handleInvoicePaymentSucceeded($stripeInvoice): void
    {
        Log::info('Stripe invoice payment succeeded', [
            'stripe_invoice_id' => $stripeInvoice->id,
            'customer' => $stripeInvoice->customer,
        ]);

        // Create order from Stripe invoice
        $order = $this->createOrderFromStripeInvoice((array) $stripeInvoice);

        if ($order) {
            $order->markAsPaid();
            Log::info('Order created and marked as paid', ['order_id' => $order->id]);
        }
    }

    private function handleInvoicePaymentFailed($stripeInvoice): void
    {
        Log::info('Stripe invoice payment failed', [
            'stripe_invoice_id' => $stripeInvoice->id,
            'customer' => $stripeInvoice->customer,
        ]);

        // Create order from Stripe invoice and mark as failed
        $order = $this->createOrderFromStripeInvoice((array) $stripeInvoice);

        if ($order) {
            $failureReason = [
                'failure_code' => $stripeInvoice->last_finalization_error->code ?? null,
                'failure_message' => $stripeInvoice->last_finalization_error->message ?? 'Payment failed',
            ];
            $order->markAsFailed($failureReason);
            Log::info('Order created and marked as failed', ['order_id' => $order->id]);
        }
    }

    private function handleSubscriptionCreated($subscription): void
    {
        $enrollment = Enrollment::where('stripe_subscription_id', $subscription->id)->first();

        if ($enrollment) {
            $enrollment->updateSubscriptionStatus($subscription->status);
            Log::info('Subscription created, enrollment updated', [
                'subscription_id' => $subscription->id,
                'enrollment_id' => $enrollment->id,
                'status' => $subscription->status,
            ]);
        }
    }

    private function handleSubscriptionUpdated($subscription): void
    {
        $enrollment = Enrollment::where('stripe_subscription_id', $subscription->id)->first();

        if ($enrollment) {
            $enrollment->updateSubscriptionStatus($subscription->status);
            Log::info('Subscription updated, enrollment status synced', [
                'subscription_id' => $subscription->id,
                'enrollment_id' => $enrollment->id,
                'status' => $subscription->status,
            ]);
        }
    }

    private function handleSubscriptionDeleted($subscription): void
    {
        $enrollment = Enrollment::where('stripe_subscription_id', $subscription->id)->first();

        if ($enrollment) {
            $enrollment->updateSubscriptionStatus('canceled');
            Log::info('Subscription deleted, enrollment canceled', [
                'subscription_id' => $subscription->id,
                'enrollment_id' => $enrollment->id,
            ]);
        }
    }

    private function handleSubscriptionTrialWillEnd($subscription): void
    {
        $enrollment = Enrollment::where('stripe_subscription_id', $subscription->id)->first();

        if ($enrollment) {
            Log::info('Subscription trial will end soon', [
                'subscription_id' => $subscription->id,
                'enrollment_id' => $enrollment->id,
                'trial_end' => $subscription->trial_end,
            ]);

            // Could send email notification here
            // Mail::to($enrollment->student->user)->send(new TrialEndingNotification($enrollment));
        }
    }

    // Helper Methods
    private function convertToStripeAmount(float $amount): int
    {
        // Convert to smallest currency unit (cents for most currencies)
        return (int) round($amount * 100);
    }

    private function mapStripeStatus(string $stripeStatus): string
    {
        return match ($stripeStatus) {
            'requires_payment_method' => Payment::STATUS_REQUIRES_PAYMENT_METHOD,
            'requires_confirmation' => Payment::STATUS_PENDING,
            'requires_action' => Payment::STATUS_REQUIRES_ACTION,
            'processing' => Payment::STATUS_PROCESSING,
            'succeeded' => Payment::STATUS_SUCCEEDED,
            'canceled' => Payment::STATUS_CANCELLED,
            default => Payment::STATUS_PENDING,
        };
    }

    private function calculateStripeFee($paymentIntent): float
    {
        if (! isset($paymentIntent->charges->data[0])) {
            return 0;
        }

        $charge = $paymentIntent->charges->data[0];

        return $charge->application_fee_amount ? $charge->application_fee_amount / 100 : 0;
    }

    public function testConnection(): array
    {
        try {
            $account = $this->stripe->accounts->retrieve();

            return [
                'success' => true,
                'account_id' => $account->id,
                'business_profile' => $account->business_profile,
                'country' => $account->country,
                'default_currency' => $account->default_currency,
                'charges_enabled' => $account->charges_enabled,
                'payouts_enabled' => $account->payouts_enabled,
            ];
        } catch (ApiErrorException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
