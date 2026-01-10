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
use Carbon\Carbon;
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

    public function getStripe(): StripeClient
    {
        return $this->stripe;
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

    /**
     * Get or create a Stripe price for an enrollment.
     * If the enrollment has a custom fee that differs from course settings,
     * a new price will be created specifically for this enrollment.
     */
    public function getOrCreateEnrollmentPrice(Enrollment $enrollment): string
    {
        // If enrollment already has its own stripe_price_id, use it
        if ($enrollment->stripe_price_id) {
            Log::info('Using existing enrollment-specific price', [
                'enrollment_id' => $enrollment->id,
                'stripe_price_id' => $enrollment->stripe_price_id,
            ]);

            return $enrollment->stripe_price_id;
        }

        $feeSettings = $enrollment->course->feeSettings;

        if (! $feeSettings) {
            throw new \Exception('Course must have fee settings configured');
        }

        if (! $feeSettings->stripe_price_id) {
            throw new \Exception('Course must have a Stripe price ID before creating subscription');
        }

        // Check if enrollment fee differs from course fee settings
        $enrollmentFee = $enrollment->enrollment_fee;
        $courseFee = $feeSettings->fee_amount;

        // If fees are the same or enrollment fee is not set, use course price
        if (! $enrollmentFee || abs($enrollmentFee - $courseFee) < 0.01) {
            Log::info('Using course fee settings price (enrollment fee matches)', [
                'enrollment_id' => $enrollment->id,
                'enrollment_fee' => $enrollmentFee,
                'course_fee' => $courseFee,
                'stripe_price_id' => $feeSettings->stripe_price_id,
            ]);

            return $feeSettings->stripe_price_id;
        }

        // Enrollment has a custom fee - create a new price
        Log::info('Creating enrollment-specific price (custom fee)', [
            'enrollment_id' => $enrollment->id,
            'enrollment_fee' => $enrollmentFee,
            'course_fee' => $courseFee,
        ]);

        if (! $enrollment->course->stripe_product_id) {
            throw new \Exception('Course must have a Stripe product ID before creating prices');
        }

        if (! $this->isConfigured()) {
            throw new \Exception('Stripe is not properly configured.');
        }

        try {
            $currency = $feeSettings->currency ?? $this->getCurrency();
            $unitAmount = $this->convertToStripeAmount($enrollmentFee);

            $priceData = [
                'product' => $enrollment->course->stripe_product_id,
                'unit_amount' => $unitAmount,
                'currency' => strtolower($currency),
                'recurring' => [
                    'interval' => $feeSettings->getStripeInterval(),
                    'interval_count' => $feeSettings->getStripeIntervalCount(),
                ],
                'metadata' => [
                    'enrollment_id' => $enrollment->id,
                    'student_id' => $enrollment->student_id,
                    'course_id' => $enrollment->course_id,
                    'custom_enrollment_fee' => true,
                    'original_course_fee' => $courseFee,
                    'system' => 'mudeer_bedaie',
                    'created_at' => now()->toISOString(),
                ],
            ];

            $price = $this->stripe->prices->create($priceData);

            // Store the price ID on the enrollment
            $enrollment->update(['stripe_price_id' => $price->id]);

            Log::info('Enrollment-specific Stripe price created successfully', [
                'enrollment_id' => $enrollment->id,
                'stripe_price_id' => $price->id,
                'unit_amount' => $unitAmount,
                'enrollment_fee' => $enrollmentFee,
            ]);

            return $price->id;

        } catch (ApiErrorException $e) {
            Log::error('Failed to create enrollment-specific Stripe price', [
                'enrollment_id' => $enrollment->id,
                'enrollment_fee' => $enrollmentFee,
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('Failed to create enrollment price: '.$e->getMessage());
        }
    }

    // Subscription Management
    public function createSubscription(Enrollment $enrollment, PaymentMethod $paymentMethod): array
    {
        if (! $this->isConfigured()) {
            throw new \Exception('Stripe is not properly configured.');
        }

        // Get or create enrollment-specific price (uses enrollment_fee if different from course fee)
        $stripePriceId = $this->getOrCreateEnrollmentPrice($enrollment);

        // Validate payment method before creating subscription
        $validation = $this->validatePaymentMethod($paymentMethod);
        if (! $validation['valid']) {
            throw new \Exception('Payment method validation failed: '.$validation['error']);
        }

        Log::info('Payment method validated successfully', [
            'enrollment_id' => $enrollment->id,
            'payment_method_id' => $paymentMethod->id,
        ]);

        $stripeCustomer = $this->createOrGetCustomer($enrollment->student->user);

        try {
            $subscriptionData = [
                'customer' => $stripeCustomer->stripe_customer_id,
                'items' => [
                    [
                        'price' => $stripePriceId,
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

            // Add billing cycle anchor if a specific billing day is set
            if ($enrollment->course->feeSettings->hasBillingDay()) {
                $billingAnchor = $this->calculateBillingCycleAnchor($enrollment->course->feeSettings);
                if ($billingAnchor) {
                    $subscriptionData['billing_cycle_anchor'] = $billingAnchor;

                    Log::info('Setting billing cycle anchor for subscription', [
                        'enrollment_id' => $enrollment->id,
                        'billing_day' => $enrollment->course->feeSettings->billing_day,
                        'billing_cycle_anchor' => $billingAnchor,
                        'billing_cycle_anchor_date' => date('Y-m-d H:i:s', $billingAnchor),
                    ]);
                }
            }

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

    /**
     * Create a subscription with advanced options including start date, trial periods, and custom scheduling
     */
    public function createSubscriptionWithOptions(
        Enrollment $enrollment,
        PaymentMethod $paymentMethod,
        array $options = []
    ): array {
        if (! $this->isConfigured()) {
            throw new \Exception('Stripe is not properly configured.');
        }

        // Get or create enrollment-specific price (uses enrollment_fee if different from course fee)
        $stripePriceId = $this->getOrCreateEnrollmentPrice($enrollment);

        // Validate payment method before creating subscription
        $validation = $this->validatePaymentMethod($paymentMethod);
        if (! $validation['valid']) {
            throw new \Exception('Payment method validation failed: '.$validation['error']);
        }

        Log::info('Creating subscription with options', [
            'enrollment_id' => $enrollment->id,
            'payment_method_id' => $paymentMethod->id,
            'options' => $options,
        ]);

        $stripeCustomer = $this->createOrGetCustomer($enrollment->student->user);

        try {
            $subscriptionData = [
                'customer' => $stripeCustomer->stripe_customer_id,
                'items' => [
                    [
                        'price' => $stripePriceId,
                    ],
                ],
                'payment_behavior' => 'allow_incomplete',
                'off_session' => true,
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

            // Handle start date - this is the key fix for the user's issue
            $startDateTime = null;
            if (isset($options['start_date'])) {
                $startTime = $options['start_time'] ?? '07:23';
                $timezone = $options['timezone'] ?? 'Asia/Kuala_Lumpur';

                $startDateTime = \Carbon\Carbon::parse($options['start_date'].' '.$startTime, $timezone);
                $startTimestamp = $startDateTime->timestamp;

                // Determine if we should use trial or billing cycle anchor
                $now = now($timezone);

                if ($startDateTime->isAfter($now)) {
                    // Future start date - use trial to delay billing until start date
                    $subscriptionData['trial_end'] = $startTimestamp;

                    Log::info('Setting trial end for future start date', [
                        'enrollment_id' => $enrollment->id,
                        'start_date' => $startDateTime->toDateTimeString(),
                        'trial_end_timestamp' => $startTimestamp,
                    ]);
                } else {
                    // Start date is today or in the past - use billing cycle anchor to start immediately
                    $subscriptionData['billing_cycle_anchor'] = 'now';

                    Log::info('Starting subscription immediately (start date is not in future)', [
                        'enrollment_id' => $enrollment->id,
                        'start_date' => $startDateTime->toDateTimeString(),
                    ]);
                }
            } else {
                // No specific start date provided, use course billing day if available
                if ($enrollment->course->feeSettings->hasBillingDay()) {
                    $billingAnchor = $this->calculateBillingCycleAnchor($enrollment->course->feeSettings);
                    if ($billingAnchor) {
                        $subscriptionData['billing_cycle_anchor'] = $billingAnchor;

                        Log::info('Setting billing cycle anchor from course settings', [
                            'enrollment_id' => $enrollment->id,
                            'billing_day' => $enrollment->course->feeSettings->billing_day,
                            'billing_cycle_anchor' => $billingAnchor,
                            'billing_cycle_anchor_date' => date('Y-m-d H:i:s', $billingAnchor),
                        ]);
                    }
                }
            }

            // Handle explicit trial end date if provided (overrides start date trial)
            if (isset($options['trial_end_at'])) {
                $trialDateTime = \Carbon\Carbon::parse($options['trial_end_at'], $options['timezone'] ?? 'Asia/Kuala_Lumpur');
                $subscriptionData['trial_end'] = $trialDateTime->timestamp;

                Log::info('Setting explicit trial end date', [
                    'enrollment_id' => $enrollment->id,
                    'trial_end_at' => $trialDateTime->toDateTimeString(),
                ]);
            }

            // Handle proration behavior
            if (isset($options['proration_behavior'])) {
                $subscriptionData['proration_behavior'] = $options['proration_behavior'];
            }

            // Handle subscription end date
            if (isset($options['end_date'])) {
                $endTime = $options['end_time'] ?? '23:59';
                $timezone = $options['timezone'] ?? 'Asia/Kuala_Lumpur';
                $endDateTime = \Carbon\Carbon::parse($options['end_date'].' '.$endTime, $timezone);
                $subscriptionData['cancel_at'] = $endDateTime->timestamp;

                Log::info('Setting subscription end date', [
                    'enrollment_id' => $enrollment->id,
                    'end_date' => $endDateTime->toDateTimeString(),
                ]);
            }

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

            // Handle next payment date scheduling if provided and different from start date
            if (isset($options['next_payment_date']) &&
                $options['next_payment_date'] !== $options['start_date']) {
                try {
                    $nextPaymentTime = $options['next_payment_time'] ?? '07:23';
                    $timezone = $options['timezone'] ?? 'Asia/Kuala_Lumpur';
                    $nextPaymentDateTime = \Carbon\Carbon::parse($options['next_payment_date'].' '.$nextPaymentTime, $timezone);
                    $nextPaymentTimestamp = $nextPaymentDateTime->timestamp;

                    // Update subscription schedule for next payment
                    $scheduleResult = $this->updateSubscriptionSchedule(
                        $subscription->id,
                        ['next_payment_date' => $nextPaymentTimestamp]
                    );

                    if ($scheduleResult['success']) {
                        Log::info('Next payment date set successfully', [
                            'enrollment_id' => $enrollment->id,
                            'subscription_id' => $subscription->id,
                            'next_payment_date' => $nextPaymentDateTime->toDateTimeString(),
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to set next payment date', [
                        'enrollment_id' => $enrollment->id,
                        'subscription_id' => $subscription->id,
                        'error' => $e->getMessage(),
                    ]);
                    // Don't fail the entire process
                }
            }

            Log::info('Stripe subscription created successfully with options', [
                'enrollment_id' => $enrollment->id,
                'stripe_subscription_id' => $subscription->id,
                'subscription_status' => $subscription->status,
                'start_date' => $startDateTime?->toDateTimeString(),
            ]);

            return [
                'subscription' => $subscription,
                'client_secret' => $subscription->latest_invoice?->payment_intent?->client_secret,
                'start_date' => $startDateTime,
            ];

        } catch (ApiErrorException $e) {
            Log::error('Failed to create Stripe subscription with options', [
                'enrollment_id' => $enrollment->id,
                'options' => $options,
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

            // Handle subscriptions where current_period_* fields are null
            // This commonly happens with subscriptions that haven't had their first recurring billing cycle yet
            $currentPeriodEnd = $subscription->current_period_end;
            $currentPeriodStart = $subscription->current_period_start;

            // Fall back to billing_cycle_anchor if period fields are null
            if (is_null($currentPeriodEnd) && ! is_null($subscription->billing_cycle_anchor)) {
                $currentPeriodEnd = $subscription->billing_cycle_anchor;
                // For subscriptions using billing cycle anchor, period start is typically the anchor date
                $currentPeriodStart = $subscription->billing_cycle_anchor;
            }

            return [
                'id' => $subscription->id,
                'status' => $subscription->status,
                'cancel_at_period_end' => $subscription->cancel_at_period_end,
                'cancel_at' => $subscription->cancel_at,
                'current_period_end' => $currentPeriodEnd,
                'current_period_start' => $currentPeriodStart,
                'pause_collection' => $subscription->pause_collection ?? null,
            ];

        } catch (ApiErrorException $e) {
            if ($e->getStripeCode() === 'resource_missing') {
                return [
                    'status' => 'not_found',
                    'cancel_at_period_end' => false,
                    'cancel_at' => null,
                    'pause_collection' => null,
                ];
            }

            throw new \Exception('Failed to retrieve subscription: '.$e->getMessage());
        }
    }

    public function pauseSubscriptionCollection(string $subscriptionId): array
    {
        try {
            $subscription = $this->stripe->subscriptions->update($subscriptionId, [
                'pause_collection' => [
                    'behavior' => 'void',
                ],
            ]);

            Log::info('Stripe subscription collection paused', [
                'subscription_id' => $subscriptionId,
                'pause_collection' => $subscription->pause_collection,
            ]);

            return [
                'success' => true,
                'subscription' => $subscription,
                'message' => 'Collection has been paused successfully.',
            ];

        } catch (ApiErrorException $e) {
            Log::error('Failed to pause Stripe subscription collection', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('Failed to pause collection: '.$e->getMessage());
        }
    }

    public function resumeSubscriptionCollection(string $subscriptionId): array
    {
        try {
            $subscription = $this->stripe->subscriptions->update($subscriptionId, [
                'pause_collection' => null,
            ]);

            Log::info('Stripe subscription collection resumed', [
                'subscription_id' => $subscriptionId,
                'pause_collection' => $subscription->pause_collection,
            ]);

            return [
                'success' => true,
                'subscription' => $subscription,
                'message' => 'Collection has been resumed successfully.',
            ];

        } catch (ApiErrorException $e) {
            Log::error('Failed to resume Stripe subscription collection', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('Failed to resume collection: '.$e->getMessage());
        }
    }

    public function syncSubscriptionCollectionStatus(Enrollment $enrollment): bool
    {
        if (! $enrollment->stripe_subscription_id) {
            return false;
        }

        try {
            $subscriptionDetails = $this->getSubscriptionDetails($enrollment->stripe_subscription_id);

            if (isset($subscriptionDetails['pause_collection'])) {
                $pauseCollection = $subscriptionDetails['pause_collection'];

                if ($pauseCollection && isset($pauseCollection['behavior']) && $pauseCollection['behavior'] === 'void') {
                    // Collection is paused
                    if (! $enrollment->isCollectionPaused()) {
                        $enrollment->pauseCollection();
                        Log::info('Collection status synced to paused', [
                            'enrollment_id' => $enrollment->id,
                            'subscription_id' => $enrollment->stripe_subscription_id,
                        ]);
                    }
                } else {
                    // Collection is active
                    if ($enrollment->isCollectionPaused()) {
                        $enrollment->resumeCollection();
                        Log::info('Collection status synced to active', [
                            'enrollment_id' => $enrollment->id,
                            'subscription_id' => $enrollment->stripe_subscription_id,
                        ]);
                    }
                }

                return true;
            }
        } catch (\Exception $e) {
            Log::error('Failed to sync collection status', [
                'enrollment_id' => $enrollment->id,
                'subscription_id' => $enrollment->stripe_subscription_id,
                'error' => $e->getMessage(),
            ]);
        }

        return false;
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
                // Attempt to recover by creating a new payment intent for the invoice
                try {
                    Log::info('No payment intent found, attempting to create one for invoice', [
                        'subscription_id' => $subscriptionId,
                        'invoice_id' => $latestInvoice->id,
                    ]);

                    // Try to finalize the invoice, which should create a payment intent
                    $finalizedInvoice = $this->stripe->invoices->finalizeInvoice($latestInvoice->id);

                    // Re-retrieve the subscription with the updated invoice
                    $subscription = $this->stripe->subscriptions->retrieve($subscriptionId, [
                        'expand' => ['latest_invoice.payment_intent'],
                    ]);

                    $paymentIntent = $subscription->latest_invoice->payment_intent;

                    if ($paymentIntent) {
                        Log::info('Payment intent created successfully during recovery', [
                            'subscription_id' => $subscriptionId,
                            'payment_intent_id' => $paymentIntent->id,
                            'status' => $paymentIntent->status,
                        ]);
                    } else {
                        // If still no payment intent, try to pay the invoice directly
                        $paidInvoice = $this->stripe->invoices->pay($latestInvoice->id);

                        return [
                            'success' => true,
                            'message' => 'Invoice paid successfully without payment intent',
                            'invoice' => $paidInvoice,
                        ];
                    }
                } catch (ApiErrorException $e) {
                    Log::error('Failed to recover missing payment intent', [
                        'subscription_id' => $subscriptionId,
                        'invoice_id' => $latestInvoice->id,
                        'error' => $e->getMessage(),
                        'stripe_code' => $e->getStripeCode(),
                    ]);

                    return [
                        'success' => false,
                        'error' => 'No payment intent found for invoice and unable to create one. '.
                                 'This subscription may need to be canceled and recreated, or the student should complete payment setup directly.',
                        'requires_manual_action' => true,
                        'suggested_actions' => [
                            'Cancel this subscription and create a new one',
                            'Have the student complete payment setup in their account',
                            'Check the payment method is still valid',
                            'Contact Stripe support if the issue persists',
                        ],
                    ];
                }
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

    public function validatePaymentMethod(PaymentMethod $paymentMethod): array
    {
        try {
            if (! $paymentMethod->stripe_payment_method_id) {
                return [
                    'valid' => false,
                    'error' => 'Payment method has no Stripe payment method ID',
                ];
            }

            // Retrieve the payment method from Stripe to check its status
            $stripePaymentMethod = $this->stripe->paymentMethods->retrieve($paymentMethod->stripe_payment_method_id);

            if (! $stripePaymentMethod) {
                return [
                    'valid' => false,
                    'error' => 'Payment method not found in Stripe',
                ];
            }

            // Check if payment method is attached to a customer
            if (! $stripePaymentMethod->customer) {
                return [
                    'valid' => false,
                    'error' => 'Payment method is not attached to a customer',
                ];
            }

            // For card payment methods, check if they're not expired
            if ($stripePaymentMethod->type === 'card' && $stripePaymentMethod->card) {
                $currentYear = (int) date('Y');
                $currentMonth = (int) date('n');
                $cardYear = (int) $stripePaymentMethod->card->exp_year;
                $cardMonth = (int) $stripePaymentMethod->card->exp_month;

                if ($cardYear < $currentYear || ($cardYear === $currentYear && $cardMonth < $currentMonth)) {
                    return [
                        'valid' => false,
                        'error' => 'Card has expired',
                    ];
                }
            }

            return [
                'valid' => true,
                'payment_method' => $stripePaymentMethod,
            ];

        } catch (ApiErrorException $e) {
            Log::error('Failed to validate payment method', [
                'payment_method_id' => $paymentMethod->id,
                'stripe_payment_method_id' => $paymentMethod->stripe_payment_method_id,
                'error' => $e->getMessage(),
            ]);

            return [
                'valid' => false,
                'error' => 'Unable to validate payment method: '.$e->getMessage(),
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

    // Subscription Scheduling Management
    public function updateSubscriptionSchedule(string $subscriptionId, array $scheduleData): array
    {
        try {
            $updateData = [];
            $changes = [];

            // Handle billing cycle anchor - Stripe only allows 'now', 'unchanged', or unset for existing subscriptions
            // Skip this if next_payment_date is provided as it takes precedence
            if (isset($scheduleData['billing_cycle_anchor']) && ! isset($scheduleData['next_payment_date'])) {
                $billingAnchor = $scheduleData['billing_cycle_anchor'];
                $today = now()->startOfDay()->timestamp;

                if ($billingAnchor <= $today + 3600) { // Within 1 hour of today
                    $updateData['billing_cycle_anchor'] = 'now';
                    $changes[] = 'billing cycle reset to now';

                    // If resetting billing cycle to now, end any existing trial to avoid conflicts
                    if (! isset($scheduleData['trial_end_at'])) {
                        $updateData['trial_end'] = 'now';
                        $changes[] = 'trial ended (required when resetting billing cycle)';
                    }
                } else {
                    // For future dates, we can use 'unchanged' to maintain current cycle
                    // This limitation exists in Stripe's API for existing subscriptions
                    $updateData['billing_cycle_anchor'] = 'unchanged';
                    $changes[] = 'billing cycle maintained (future dates not supported for existing subscriptions)';
                }
            }

            // Handle next payment date - this takes precedence over billing cycle anchor
            if (isset($scheduleData['next_payment_date'])) {
                $nextPaymentTimestamp = $scheduleData['next_payment_date'];
                $today = now()->timestamp;
                $tomorrow = now()->addDay()->timestamp;

                if ($nextPaymentTimestamp >= $today) {
                    if ($nextPaymentTimestamp <= $tomorrow) {
                        // If next payment is today or tomorrow, reset billing cycle to now
                        $updateData['billing_cycle_anchor'] = 'now';
                        $updateData['trial_end'] = 'now'; // End any existing trial immediately
                        $changes[] = 'billing cycle reset to now for immediate payment';
                    } else {
                        // For future dates on existing subscriptions, use trial_end to delay next payment
                        // This is the correct approach as billing_cycle_anchor cannot be set to future dates
                        $updateData['trial_end'] = $nextPaymentTimestamp;
                        $changes[] = 'next payment rescheduled for '.date('M d, Y H:i', $nextPaymentTimestamp).' using trial extension';
                    }
                } else {
                    $changes[] = 'next payment date ignored (cannot be in the past)';
                }
            }

            // Handle trial end date (both setting and removal)
            // Only process if next_payment_date is not being used for rescheduling
            if (array_key_exists('trial_end_at', $scheduleData) && ! isset($scheduleData['next_payment_date'])) {
                if ($scheduleData['trial_end_at'] !== null) {
                    $updateData['trial_end'] = $scheduleData['trial_end_at'];
                    $changes[] = 'trial end date updated';
                } else {
                    // Remove trial end date (set to 'now' to end immediately)
                    $updateData['trial_end'] = 'now';
                    $changes[] = 'trial ended immediately';
                }
            }

            // Handle proration behavior
            if (isset($scheduleData['proration_behavior'])) {
                $updateData['proration_behavior'] = $scheduleData['proration_behavior'];
                $changes[] = 'proration behavior updated';
            }

            // Handle subscription end date
            if (isset($scheduleData['cancel_at'])) {
                $updateData['cancel_at'] = $scheduleData['cancel_at'];
                $changes[] = 'subscription end date set';
            }

            // Only update if we have changes
            if (empty($updateData)) {
                return [
                    'success' => true,
                    'subscription' => null,
                    'message' => 'No changes to apply to subscription schedule',
                ];
            }

            $subscription = $this->stripe->subscriptions->update($subscriptionId, $updateData);

            Log::info('Subscription schedule updated', [
                'subscription_id' => $subscriptionId,
                'update_data' => $updateData,
                'changes' => $changes,
                'new_status' => $subscription->status,
            ]);

            $message = 'Subscription schedule updated: '.implode(', ', $changes);

            return [
                'success' => true,
                'subscription' => $subscription,
                'message' => $message,
            ];

        } catch (ApiErrorException $e) {
            Log::error('Failed to update subscription schedule', [
                'subscription_id' => $subscriptionId,
                'schedule_data' => $scheduleData,
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('Failed to update subscription schedule: '.$e->getMessage());
        }
    }

    public function rescheduleNextPayment(string $subscriptionId, int $nextPaymentTimestamp): array
    {
        try {
            // Get current subscription
            $subscription = $this->stripe->subscriptions->retrieve($subscriptionId);

            // Update billing cycle anchor to reschedule next payment
            $updatedSubscription = $this->stripe->subscriptions->update($subscriptionId, [
                'billing_cycle_anchor' => $nextPaymentTimestamp,
                'proration_behavior' => 'create_prorations',
            ]);

            Log::info('Next payment rescheduled', [
                'subscription_id' => $subscriptionId,
                'old_current_period_end' => $subscription->current_period_end,
                'new_billing_anchor' => $nextPaymentTimestamp,
                'new_current_period_end' => $updatedSubscription->current_period_end,
            ]);

            return [
                'success' => true,
                'subscription' => $updatedSubscription,
                'message' => 'Next payment date rescheduled successfully',
                'old_period_end' => $subscription->current_period_end,
                'new_period_end' => $updatedSubscription->current_period_end,
            ];

        } catch (ApiErrorException $e) {
            Log::error('Failed to reschedule next payment', [
                'subscription_id' => $subscriptionId,
                'next_payment_timestamp' => $nextPaymentTimestamp,
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('Failed to reschedule next payment: '.$e->getMessage());
        }
    }

    public function updateTrialEnd(string $subscriptionId, ?int $trialEndTimestamp): array
    {
        try {
            $updateData = [];

            if ($trialEndTimestamp) {
                $updateData['trial_end'] = $trialEndTimestamp;
            } else {
                $updateData['trial_end'] = 'now'; // End trial immediately
            }

            $subscription = $this->stripe->subscriptions->update($subscriptionId, $updateData);

            Log::info('Subscription trial period updated', [
                'subscription_id' => $subscriptionId,
                'trial_end' => $trialEndTimestamp,
                'new_status' => $subscription->status,
            ]);

            return [
                'success' => true,
                'subscription' => $subscription,
                'message' => $trialEndTimestamp ? 'Trial end date updated successfully' : 'Trial ended immediately',
            ];

        } catch (ApiErrorException $e) {
            Log::error('Failed to update trial end', [
                'subscription_id' => $subscriptionId,
                'trial_end_timestamp' => $trialEndTimestamp,
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('Failed to update trial end: '.$e->getMessage());
        }
    }

    public function changeBillingAnchor(string $subscriptionId, int $billingAnchorTimestamp): array
    {
        try {
            $subscription = $this->stripe->subscriptions->update($subscriptionId, [
                'billing_cycle_anchor' => $billingAnchorTimestamp,
                'proration_behavior' => 'create_prorations',
            ]);

            Log::info('Billing cycle anchor changed', [
                'subscription_id' => $subscriptionId,
                'billing_cycle_anchor' => $billingAnchorTimestamp,
                'billing_anchor_date' => date('Y-m-d H:i:s', $billingAnchorTimestamp),
            ]);

            return [
                'success' => true,
                'subscription' => $subscription,
                'message' => 'Billing cycle anchor updated successfully',
            ];

        } catch (ApiErrorException $e) {
            Log::error('Failed to change billing anchor', [
                'subscription_id' => $subscriptionId,
                'billing_anchor_timestamp' => $billingAnchorTimestamp,
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('Failed to change billing anchor: '.$e->getMessage());
        }
    }

    public function getDetailedSubscriptionSchedule(string $subscriptionId): array
    {
        try {
            $subscription = $this->stripe->subscriptions->retrieve($subscriptionId);

            return [
                'id' => $subscription->id,
                'status' => $subscription->status,
                'current_period_start' => $subscription->current_period_start,
                'current_period_end' => $subscription->current_period_end,
                'billing_cycle_anchor' => $subscription->billing_cycle_anchor,
                'trial_start' => $subscription->trial_start,
                'trial_end' => $subscription->trial_end,
                'cancel_at_period_end' => $subscription->cancel_at_period_end,
                'cancel_at' => $subscription->cancel_at,
                'canceled_at' => $subscription->canceled_at,
                'created' => $subscription->created,
                'items' => $subscription->items->data,
            ];

        } catch (ApiErrorException $e) {
            Log::error('Failed to get detailed subscription schedule', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('Failed to retrieve subscription schedule: '.$e->getMessage());
        }
    }

    public function updateSubscriptionEndDate(string $subscriptionId, ?int $endTimestamp): array
    {
        try {
            $updateData = [];

            if ($endTimestamp) {
                $updateData['cancel_at'] = $endTimestamp;
                $updateData['cancel_at_period_end'] = false;
            } else {
                // Remove end date
                $updateData['cancel_at'] = null;
                $updateData['cancel_at_period_end'] = false;
            }

            $subscription = $this->stripe->subscriptions->update($subscriptionId, $updateData);

            Log::info('Subscription end date updated', [
                'subscription_id' => $subscriptionId,
                'end_timestamp' => $endTimestamp,
                'cancel_at' => $subscription->cancel_at,
            ]);

            return [
                'success' => true,
                'subscription' => $subscription,
                'message' => $endTimestamp ? 'Subscription end date set successfully' : 'Subscription end date removed',
            ];

        } catch (ApiErrorException $e) {
            Log::error('Failed to update subscription end date', [
                'subscription_id' => $subscriptionId,
                'end_timestamp' => $endTimestamp,
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('Failed to update subscription end date: '.$e->getMessage());
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

    public function getCustomerPaymentMethods(string $stripeCustomerId): array
    {
        try {
            $paymentMethods = $this->stripe->paymentMethods->all([
                'customer' => $stripeCustomerId,
                'type' => 'card',
            ]);

            return $paymentMethods->data;

        } catch (ApiErrorException $e) {
            Log::error('Failed to retrieve customer payment methods', [
                'stripe_customer_id' => $stripeCustomerId,
                'error' => $e->getMessage(),
            ]);

            return [];
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

            // Dispatch appropriate job based on event type
            // Convert Stripe objects to arrays using toArray() method when available, otherwise use array casting
            $getArrayData = fn ($obj) => method_exists($obj, 'toArray') ? $obj->toArray() : (array) $obj;

            match ($event->type) {
                'customer.updated' => \App\Jobs\ProcessStripeCustomerUpdated::dispatch($webhookEvent, $getArrayData($event->data->object)),
                'invoice.payment_succeeded' => \App\Jobs\ProcessStripeInvoicePaymentSucceeded::dispatch($webhookEvent, $getArrayData($event->data->object)),
                'invoice.payment_failed' => \App\Jobs\ProcessStripeInvoicePaymentFailed::dispatch($webhookEvent, $getArrayData($event->data->object)),
                'customer.subscription.created' => \App\Jobs\ProcessStripeSubscriptionCreated::dispatch($webhookEvent, $getArrayData($event->data->object)),
                'customer.subscription.updated' => \App\Jobs\ProcessStripeSubscriptionUpdated::dispatch($webhookEvent, $getArrayData($event->data->object)),
                'customer.subscription.deleted' => \App\Jobs\ProcessStripeSubscriptionDeleted::dispatch($webhookEvent, $getArrayData($event->data->object)),
                'customer.subscription.trial_will_end' => \App\Jobs\ProcessStripeSubscriptionTrialWillEnd::dispatch($webhookEvent, $getArrayData($event->data->object)),
                default => Log::info('Unhandled webhook event', [
                    'type' => $event->type,
                    'id' => $event->id,
                ]),
            };

            // Don't mark as processed here - the job will handle that

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

    /**
     * Calculate the billing cycle anchor timestamp for a subscription
     * based on the course fee settings billing day.
     */
    private function calculateBillingCycleAnchor(CourseFeeSettings $feeSettings): ?int
    {
        if (! $feeSettings->hasBillingDay()) {
            return null;
        }

        $billingDay = $feeSettings->getValidatedBillingDay();
        if (! $billingDay) {
            return null;
        }

        $now = now();
        $currentDay = $now->day;
        $currentMonth = $now->month;
        $currentYear = $now->year;

        try {
            // Determine the target month and year
            if ($currentDay < $billingDay) {
                // If the billing day hasn't occurred this month, use this month
                $targetDate = $now->copy();
            } else {
                // If the billing day has passed this month, use next month
                $targetDate = $now->copy()->addMonth();
            }

            // Check days in target month before setting the day
            $daysInTargetMonth = $targetDate->daysInMonth;

            if ($billingDay > $daysInTargetMonth) {
                // Use the last day of the month if the billing day exceeds the month's days
                $anchorDate = $targetDate->endOfMonth()->startOfDay();
            } else {
                // Set the specific billing day
                $anchorDate = $targetDate->day($billingDay)->startOfDay();
            }

            Log::info('Calculated billing cycle anchor', [
                'original_billing_day' => $billingDay,
                'current_date' => $now->toDateString(),
                'anchor_date' => $anchorDate->toDateString(),
                'anchor_timestamp' => $anchorDate->timestamp,
                'days_in_target_month' => $daysInTargetMonth,
            ]);

            return $anchorDate->timestamp;

        } catch (\Exception $e) {
            Log::error('Failed to calculate billing cycle anchor', [
                'billing_day' => $billingDay,
                'current_date' => $now->toDateString(),
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Create a custom price for a specific amount and billing cycle
     */
    public function createCustomPrice(string $productId, float $amount, string $currency = 'MYR', string $interval = 'month', int $intervalCount = 1): string
    {
        if (! $this->isConfigured()) {
            throw new \Exception('Stripe is not properly configured.');
        }

        try {
            $unitAmount = $this->convertToStripeAmount($amount);

            Log::info('Creating custom Stripe price', [
                'product_id' => $productId,
                'amount' => $amount,
                'unit_amount' => $unitAmount,
                'currency' => $currency,
                'interval' => $interval,
                'interval_count' => $intervalCount,
            ]);

            $priceData = [
                'product' => $productId,
                'unit_amount' => $unitAmount,
                'currency' => strtolower($currency),
                'recurring' => [
                    'interval' => $interval,
                    'interval_count' => $intervalCount,
                ],
                'metadata' => [
                    'custom_price' => 'true',
                    'original_amount' => $amount,
                    'system' => 'mudeer_bedaie',
                    'created_at' => now()->toISOString(),
                ],
            ];

            $price = $this->stripe->prices->create($priceData);

            Log::info('Custom Stripe price created successfully', [
                'price_id' => $price->id,
                'product_id' => $productId,
                'created_amount' => $price->unit_amount,
                'created_currency' => $price->currency,
            ]);

            return $price->id;

        } catch (ApiErrorException $e) {
            Log::error('Failed to create custom Stripe price', [
                'product_id' => $productId,
                'amount' => $amount,
                'error_type' => $e->getStripeCode(),
                'error_message' => $e->getMessage(),
            ]);
            throw new \Exception('Failed to create custom price: '.$e->getMessage());
        }
    }

    /**
     * Update subscription to use a new price (change subscription fee)
     */
    public function updateSubscriptionPrice(string $subscriptionId, string $newPriceId): array
    {
        try {
            // First get the current subscription
            $subscription = $this->stripe->subscriptions->retrieve($subscriptionId, [
                'expand' => ['items'],
            ]);

            if (empty($subscription->items->data)) {
                throw new \Exception('Subscription has no items to update');
            }

            // Get the first (and typically only) subscription item
            $subscriptionItem = $subscription->items->data[0];

            // Update the subscription item to use the new price
            $updatedSubscription = $this->stripe->subscriptions->update($subscriptionId, [
                'items' => [
                    [
                        'id' => $subscriptionItem->id,
                        'price' => $newPriceId,
                    ],
                ],
                'proration_behavior' => 'create_prorations', // Generate prorated charges
            ]);

            Log::info('Subscription price updated successfully', [
                'subscription_id' => $subscriptionId,
                'old_price_id' => $subscriptionItem->price->id,
                'new_price_id' => $newPriceId,
                'subscription_status' => $updatedSubscription->status,
            ]);

            return [
                'success' => true,
                'subscription' => $updatedSubscription,
                'message' => 'Subscription fee updated successfully',
                'old_price_id' => $subscriptionItem->price->id,
                'new_price_id' => $newPriceId,
            ];

        } catch (ApiErrorException $e) {
            Log::error('Failed to update subscription price', [
                'subscription_id' => $subscriptionId,
                'new_price_id' => $newPriceId,
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('Failed to update subscription fee: '.$e->getMessage());
        }
    }

    /**
     * Update subscription fee by creating a new price and updating the subscription
     */
    public function updateSubscriptionFee(Enrollment $enrollment, float $newFeeAmount): array
    {
        try {
            if (! $enrollment->stripe_subscription_id) {
                throw new \Exception('No active subscription found');
            }

            if (! $enrollment->course->stripe_product_id) {
                throw new \Exception('Course must have a Stripe product ID');
            }

            // Create a new price for the updated fee
            $currency = $enrollment->course->feeSettings?->currency ?? $this->getCurrency();
            $newPriceId = $this->createCustomPrice(
                $enrollment->course->stripe_product_id,
                $newFeeAmount,
                $currency
            );

            // Update the subscription to use the new price
            $result = $this->updateSubscriptionPrice($enrollment->stripe_subscription_id, $newPriceId);

            // Update the enrollment fee and stripe_price_id in our database
            $enrollment->update([
                'enrollment_fee' => $newFeeAmount,
                'stripe_price_id' => $newPriceId,
            ]);

            Log::info('Subscription fee updated for enrollment', [
                'enrollment_id' => $enrollment->id,
                'old_fee' => $enrollment->getOriginal('enrollment_fee'),
                'new_fee' => $newFeeAmount,
                'new_price_id' => $newPriceId,
            ]);

            return [
                'success' => true,
                'message' => 'Subscription fee updated successfully to '.number_format($newFeeAmount, 2),
                'old_fee' => $enrollment->getOriginal('enrollment_fee'),
                'new_fee' => $newFeeAmount,
                'price_id' => $newPriceId,
            ];

        } catch (\Exception $e) {
            Log::error('Failed to update subscription fee for enrollment', [
                'enrollment_id' => $enrollment->id,
                'new_fee' => $newFeeAmount,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Create a manual subscription (paused collection for manual payments)
     */
    public function createManualSubscription(Enrollment $enrollment): array
    {
        // Check if student has an email - if not, use internal subscription system
        if (empty($enrollment->student->email)) {
            Log::info('Creating internal manual subscription (no email)', [
                'enrollment_id' => $enrollment->id,
                'student_id' => $enrollment->student_id,
            ]);

            return $this->createInternalManualSubscription($enrollment);
        }

        if (! $this->isConfigured()) {
            throw new \Exception('Stripe is not properly configured.');
        }

        // Get or create enrollment-specific price (uses enrollment_fee if different from course fee)
        $stripePriceId = $this->getOrCreateEnrollmentPrice($enrollment);

        $stripeCustomer = $this->createOrGetCustomer($enrollment->student->user);

        try {
            Log::info('Creating manual subscription', [
                'enrollment_id' => $enrollment->id,
                'customer_id' => $stripeCustomer->stripe_customer_id,
            ]);

            $subscriptionData = [
                'customer' => $stripeCustomer->stripe_customer_id,
                'items' => [
                    [
                        'price' => $stripePriceId,
                    ],
                ],
                'collection_method' => 'send_invoice', // Use invoice-based collection for manual payments
                'days_until_due' => 30, // Give 30 days to pay invoices
                'expand' => ['latest_invoice'],
                'metadata' => [
                    'enrollment_id' => $enrollment->id,
                    'student_id' => $enrollment->student_id,
                    'course_id' => $enrollment->course_id,
                    'payment_method_type' => 'manual',
                    'system' => 'mudeer_bedaie',
                ],
            ];

            // Add billing cycle anchor if a specific billing day is set
            if ($enrollment->course->feeSettings->hasBillingDay()) {
                $billingAnchor = $this->calculateBillingCycleAnchor($enrollment->course->feeSettings);
                if ($billingAnchor) {
                    $subscriptionData['billing_cycle_anchor'] = $billingAnchor;

                    Log::info('Setting billing cycle anchor for manual subscription', [
                        'enrollment_id' => $enrollment->id,
                        'billing_day' => $enrollment->course->feeSettings->billing_day,
                        'billing_cycle_anchor' => $billingAnchor,
                    ]);
                }
            }

            // Create the subscription with invoice collection method (no payment method required)
            $subscription = $this->stripe->subscriptions->create($subscriptionData);

            // Immediately pause collection to prevent automatic invoice generation
            $subscription = $this->stripe->subscriptions->update($subscription->id, [
                'pause_collection' => [
                    'behavior' => 'keep_as_draft', // Keep invoices as drafts
                ],
            ]);

            Log::info('Subscription created and collection paused', [
                'enrollment_id' => $enrollment->id,
                'subscription_id' => $subscription->id,
                'pause_collection' => $subscription->pause_collection ?? null,
            ]);

            // Update enrollment with subscription details
            $enrollment->update([
                'stripe_subscription_id' => $subscription->id,
                'subscription_status' => $subscription->status,
                'collection_status' => 'paused',
                'collection_paused_at' => now(),
            ]);

            Log::info('Manual subscription created successfully', [
                'enrollment_id' => $enrollment->id,
                'stripe_subscription_id' => $subscription->id,
                'status' => $subscription->status,
            ]);

            return [
                'subscription' => $subscription,
                'success' => true,
                'message' => 'Manual subscription created successfully. Collection is paused until payment is received.',
            ];

        } catch (ApiErrorException $e) {
            Log::error('Failed to create manual subscription', [
                'enrollment_id' => $enrollment->id,
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('Failed to create manual subscription: '.$e->getMessage());
        }
    }

    /**
     * Create internal manual subscription (without Stripe, for students without email)
     */
    private function createInternalManualSubscription(Enrollment $enrollment): array
    {
        try {
            // Calculate first billing date
            $nextPaymentDate = $this->calculateNextBillingDate(
                $enrollment->course->feeSettings,
                now()
            );

            // Generate internal subscription ID
            $internalSubId = 'INTERNAL-'.$enrollment->id.'-'.time();

            // Update enrollment with internal subscription details
            $enrollment->update([
                'stripe_subscription_id' => $internalSubId,
                'subscription_status' => 'active',
                'payment_method_type' => 'manual',
                'collection_status' => 'paused',
                'collection_paused_at' => now(),
                'next_payment_date' => $nextPaymentDate,
                'manual_payment_required' => false, // Will be set to true when order is generated
            ]);

            Log::info('Internal manual subscription created', [
                'enrollment_id' => $enrollment->id,
                'internal_subscription_id' => $internalSubId,
                'next_payment_date' => $nextPaymentDate,
            ]);

            // Generate first order immediately
            $firstOrder = $this->generateInternalSubscriptionOrder($enrollment);

            Log::info('First internal subscription order generated', [
                'enrollment_id' => $enrollment->id,
                'order_id' => $firstOrder->id,
                'order_number' => $firstOrder->order_number,
                'amount' => $firstOrder->amount,
            ]);

            return [
                'subscription' => null, // No Stripe subscription
                'success' => true,
                'message' => 'Internal subscription created successfully. Payment order has been generated.',
                'internal_subscription_id' => $internalSubId,
                'first_order' => $firstOrder,
            ];

        } catch (\Exception $e) {
            Log::error('Failed to create internal manual subscription', [
                'enrollment_id' => $enrollment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new \Exception('Failed to create internal subscription: '.$e->getMessage());
        }
    }

    /**
     * Calculate next billing date based on fee settings and current date
     */
    private function calculateNextBillingDate(CourseFeeSettings $feeSettings, Carbon $fromDate): Carbon
    {
        $nextDate = $fromDate->copy();

        // If a specific billing day is set, use it
        if ($feeSettings->hasBillingDay()) {
            $billingDay = $feeSettings->getValidatedBillingDay();

            // Set to the billing day of current month
            $nextDate->day($billingDay);

            // If we've passed the billing day this month, move to next billing cycle
            if ($nextDate->lte($fromDate)) {
                $nextDate = $this->addBillingCycle($nextDate, $feeSettings->billing_cycle);
                $nextDate->day($billingDay);
            }
        } else {
            // No specific billing day - add one billing cycle from today
            $nextDate = $this->addBillingCycle($fromDate, $feeSettings->billing_cycle);
        }

        return $nextDate;
    }

    /**
     * Add billing cycle to a date
     */
    private function addBillingCycle(Carbon $date, string $billingCycle): Carbon
    {
        return match ($billingCycle) {
            'monthly' => $date->copy()->addMonth(),
            'quarterly' => $date->copy()->addMonths(3),
            'yearly' => $date->copy()->addYear(),
            default => $date->copy()->addMonth(),
        };
    }

    /**
     * Generate order for internal subscription
     */
    public function generateInternalSubscriptionOrder(Enrollment $enrollment): Order
    {
        // Check if this is an internal subscription
        if (! $enrollment->stripe_subscription_id || ! str_starts_with($enrollment->stripe_subscription_id, 'INTERNAL-')) {
            throw new \Exception('This is not an internal subscription');
        }

        // Calculate period dates
        $lastOrder = $enrollment->orders()
            ->where('status', Order::STATUS_PAID)
            ->orderBy('period_end', 'desc')
            ->first();

        if ($lastOrder) {
            // Start from day after last period ended
            $periodStart = $lastOrder->period_end->copy()->addDay();
        } else {
            // First order - start from now
            $periodStart = now()->startOfDay();
        }

        // Calculate period end based on billing cycle
        $periodEnd = $this->addBillingCycle($periodStart, $enrollment->course->feeSettings->billing_cycle)
            ->subDay(); // End day before next cycle starts

        // Get the fee amount - prioritize course fee settings
        // Use course fee if available and > 0, otherwise use enrollment fee
        $feeAmount = 0;

        if ($enrollment->course && $enrollment->course->feeSettings && $enrollment->course->feeSettings->fee_amount > 0) {
            $feeAmount = $enrollment->course->feeSettings->fee_amount;
        } elseif ($enrollment->enrollment_fee && $enrollment->enrollment_fee > 0) {
            $feeAmount = $enrollment->enrollment_fee;
        } else {
            // Reload the enrollment with course fee settings to ensure it's loaded
            $enrollment->load('course.feeSettings');
            $feeAmount = $enrollment->course->feeSettings->fee_amount ?? 0;
        }

        if ($feeAmount <= 0) {
            throw new \Exception('Cannot generate order: No valid fee amount found for enrollment #'.$enrollment->id);
        }

        // Create the order
        $order = Order::create([
            'enrollment_id' => $enrollment->id,
            'student_id' => $enrollment->student_id,
            'course_id' => $enrollment->course_id,
            'amount' => $feeAmount,
            'currency' => $enrollment->course->feeSettings->currency ?? 'MYR',
            'status' => Order::STATUS_PENDING,
            'billing_reason' => Order::REASON_MANUAL,
            'payment_method' => Order::PAYMENT_METHOD_MANUAL,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'metadata' => [
                'payment_method_type' => 'manual',
                'subscription_type' => 'internal',
                'internal_subscription_id' => $enrollment->stripe_subscription_id,
                'generated_at' => now()->toISOString(),
                'billing_cycle' => $enrollment->course->feeSettings->billing_cycle,
            ],
        ]);

        // Create order item for the course fee
        $order->items()->create([
            'description' => "Course Fee - {$enrollment->course->name} ({$enrollment->course->feeSettings->billing_cycle_label})",
            'quantity' => 1,
            'unit_price' => $feeAmount,
            'total_price' => $feeAmount,
            'metadata' => [
                'course_id' => $enrollment->course->id,
                'course_name' => $enrollment->course->name,
                'billing_cycle' => $enrollment->course->feeSettings->billing_cycle,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
            ],
        ]);

        // Update enrollment's next payment date
        $nextPaymentDate = $this->calculateNextBillingDate(
            $enrollment->course->feeSettings,
            $periodEnd
        );

        $enrollment->update([
            'next_payment_date' => $nextPaymentDate,
            'manual_payment_required' => true,
        ]);

        Log::info('Generated internal subscription order', [
            'enrollment_id' => $enrollment->id,
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'amount' => $order->amount,
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'next_payment_date' => $nextPaymentDate->toDateString(),
        ]);

        return $order;
    }

    /**
     * Process manual payment for a subscription
     */
    public function processManualSubscriptionPayment(Enrollment $enrollment, Order $order): array
    {
        try {
            if (! $enrollment->stripe_subscription_id) {
                throw new \Exception('No subscription found for this enrollment');
            }

            // Mark the order as paid first
            $order->markAsPaid();

            // Get the subscription details
            $subscription = $this->stripe->subscriptions->retrieve($enrollment->stripe_subscription_id, [
                'expand' => ['latest_invoice'],
            ]);

            Log::info('Processing manual payment for subscription', [
                'enrollment_id' => $enrollment->id,
                'subscription_id' => $subscription->id,
                'order_id' => $order->id,
                'subscription_status' => $subscription->status,
            ]);

            // If this is the first payment and subscription is incomplete
            if ($subscription->status === 'incomplete' && $subscription->latest_invoice) {
                // Manually mark the invoice as paid
                $this->stripe->invoices->pay($subscription->latest_invoice->id, [
                    'paid_out_of_band' => true,
                ]);

                Log::info('Marked invoice as paid out of band', [
                    'invoice_id' => $subscription->latest_invoice->id,
                    'subscription_id' => $subscription->id,
                ]);
            }

            // Resume collection for future payments (optional - can be kept paused for continued manual payments)
            // Uncomment the next lines if you want to switch to automatic collection after first manual payment
            /*
            $this->resumeSubscriptionCollection($subscription->id);
            $enrollment->update(['collection_status' => 'active', 'collection_paused_at' => null]);
            */

            // Update enrollment payment status
            $enrollment->markManualPaymentCompleted();

            Log::info('Manual payment processed successfully', [
                'enrollment_id' => $enrollment->id,
                'order_id' => $order->id,
            ]);

            return [
                'success' => true,
                'message' => 'Manual payment processed successfully',
                'subscription' => $subscription,
            ];

        } catch (\Exception $e) {
            Log::error('Failed to process manual subscription payment', [
                'enrollment_id' => $enrollment->id,
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Generate next manual payment order for subscription
     */
    public function generateNextManualPaymentOrder(Enrollment $enrollment): Order
    {
        if (! $enrollment->stripe_subscription_id) {
            throw new \Exception('No subscription found for this enrollment');
        }

        try {
            $subscription = $this->stripe->subscriptions->retrieve($enrollment->stripe_subscription_id);

            // Calculate next period dates
            $lastOrder = $enrollment->orders()
                ->where('status', Order::STATUS_PAID)
                ->orderBy('period_end', 'desc')
                ->first();

            $periodStart = $lastOrder ? $lastOrder->period_end->addDay() : now();
            $periodEnd = $enrollment->course->feeSettings->billing_cycle === 'monthly'
                ? $periodStart->copy()->addMonth()
                : $periodStart->copy()->addYear();

            $order = Order::create([
                'enrollment_id' => $enrollment->id,
                'student_id' => $enrollment->student_id,
                'course_id' => $enrollment->course_id,
                'amount' => $enrollment->enrollment_fee,
                'currency' => 'MYR',
                'status' => Order::STATUS_PENDING,
                'billing_reason' => Order::REASON_MANUAL,
                'payment_method' => Order::PAYMENT_METHOD_MANUAL,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'metadata' => [
                    'payment_method_type' => 'manual',
                    'stripe_subscription_id' => $subscription->id,
                    'generated_at' => now()->toISOString(),
                ],
            ]);

            // Create order item for the course fee
            $order->items()->create([
                'description' => "Course Fee - {$enrollment->course->name}",
                'quantity' => 1,
                'unit_price' => $enrollment->enrollment_fee,
                'total_price' => $enrollment->enrollment_fee,
                'metadata' => [
                    'course_id' => $enrollment->course->id,
                    'course_name' => $enrollment->course->name,
                    'billing_cycle' => $enrollment->course->feeSettings->billing_cycle ?? 'monthly',
                    'period_start' => $periodStart->toDateString(),
                    'period_end' => $periodEnd->toDateString(),
                ],
            ]);

            Log::info('Generated next manual payment order', [
                'enrollment_id' => $enrollment->id,
                'order_id' => $order->id,
                'amount' => $order->amount,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
            ]);

            return $order;

        } catch (\Exception $e) {
            Log::error('Failed to generate next manual payment order', [
                'enrollment_id' => $enrollment->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Switch enrollment from manual to automatic payments
     */
    public function switchToAutomaticPayments(Enrollment $enrollment, PaymentMethod $paymentMethod): array
    {
        try {
            if (! $enrollment->stripe_subscription_id) {
                throw new \Exception('No subscription found for this enrollment');
            }

            // Validate payment method
            $validation = $this->validatePaymentMethod($paymentMethod);
            if (! $validation['valid']) {
                throw new \Exception('Payment method validation failed: '.$validation['error']);
            }

            // Update subscription payment method and resume collection safely
            $this->updateSubscriptionPaymentMethod($enrollment->stripe_subscription_id, $paymentMethod->stripe_payment_method_id);

            // Get current subscription to check trial status
            $subscription = $this->stripe->subscriptions->retrieve($enrollment->stripe_subscription_id);

            // If trial has expired and collection is paused, we need to resume safely
            // to prevent immediate charging for "missed" periods
            if ($subscription->pause_collection && $subscription->status !== 'trialing') {
                // Resume collection but set billing cycle anchor to prevent immediate charge
                $this->stripe->subscriptions->update($enrollment->stripe_subscription_id, [
                    'pause_collection' => null,
                    'billing_cycle_anchor' => 'now', // Start fresh billing cycle from now
                    'proration_behavior' => 'none', // Don't charge for missed periods
                ]);

                Log::info('Resumed collection safely after trial expiration', [
                    'subscription_id' => $enrollment->stripe_subscription_id,
                    'enrollment_id' => $enrollment->id,
                ]);
            } else {
                // Trial is still active or collection not paused, resume normally
                $this->resumeSubscriptionCollection($enrollment->stripe_subscription_id);
            }

            // Update enrollment
            $enrollment->update([
                'payment_method_type' => 'automatic',
                'manual_payment_required' => false,
                'collection_status' => 'active',
                'collection_paused_at' => null,
            ]);

            Log::info('Switched enrollment to automatic payments', [
                'enrollment_id' => $enrollment->id,
                'subscription_id' => $enrollment->stripe_subscription_id,
                'payment_method_id' => $paymentMethod->id,
            ]);

            return [
                'success' => true,
                'message' => 'Successfully switched to automatic payments',
            ];

        } catch (\Exception $e) {
            Log::error('Failed to switch to automatic payments', [
                'enrollment_id' => $enrollment->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Switch enrollment from automatic to manual payments
     */
    public function switchToManualPayments(Enrollment $enrollment): array
    {
        try {
            if (! $enrollment->stripe_subscription_id) {
                throw new \Exception('No subscription found for this enrollment');
            }

            // Pause collection to switch to manual payments
            $this->pauseSubscriptionCollection($enrollment->stripe_subscription_id);

            // Update enrollment
            $enrollment->update([
                'payment_method_type' => 'manual',
                'manual_payment_required' => true,
                'collection_status' => 'paused',
                'collection_paused_at' => now(),
            ]);

            Log::info('Switched enrollment to manual payments', [
                'enrollment_id' => $enrollment->id,
                'subscription_id' => $enrollment->stripe_subscription_id,
            ]);

            return [
                'success' => true,
                'message' => 'Successfully switched to manual payments. Collection has been paused.',
            ];

        } catch (\Exception $e) {
            Log::error('Failed to switch to manual payments', [
                'enrollment_id' => $enrollment->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Find existing Stripe customer by email
     */
    public function findCustomerByEmail(string $email): ?\Stripe\Customer
    {
        try {
            $customers = $this->stripe->customers->all([
                'email' => $email,
                'limit' => 1,
            ]);

            if ($customers->data && count($customers->data) > 0) {
                $customer = $customers->data[0];
                Log::info('Found existing Stripe customer', [
                    'customer_id' => $customer->id,
                    'email' => $email,
                ]);

                return $customer;
            }

            return null;

        } catch (ApiErrorException $e) {
            Log::error('Failed to search for Stripe customer', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Create a new Stripe customer
     */
    public function createCustomer(string $email, string $name): \Stripe\Customer
    {
        try {
            $customer = $this->stripe->customers->create([
                'email' => $email,
                'name' => $name,
            ]);

            Log::info('Created Stripe customer', [
                'customer_id' => $customer->id,
                'email' => $email,
                'name' => $name,
            ]);

            return $customer;

        } catch (ApiErrorException $e) {
            Log::error('Failed to create Stripe customer', [
                'email' => $email,
                'name' => $name,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
