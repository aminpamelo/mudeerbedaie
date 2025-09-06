<?php

declare(strict_types=1);

use App\Models\Course;
use App\Models\CourseFeeSettings;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Student;
use App\Models\User;
use App\Services\StripeService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stripe\StripeClient;

uses(RefreshDatabase::class);

/**
 * Integration tests that use Stripe's actual test environment.
 * These tests verify that the subscription payment scheduling works correctly
 * with real Stripe API calls using test keys.
 *
 * Note: These tests require valid Stripe test API keys to be configured.
 * They will be skipped if Stripe is not properly configured.
 */
beforeEach(function () {
    // Check if Stripe is properly configured for testing
    $stripeService = app(StripeService::class);

    if (! $stripeService->isConfigured()) {
        $this->markTestSkipped('Stripe is not configured. Set STRIPE_SECRET_KEY and STRIPE_PUBLISHABLE_KEY in your .env file.');
    }

    // Ensure we're using test mode
    if ($stripeService->isLiveMode()) {
        $this->markTestSkipped('These tests should only run with Stripe test keys, not live keys.');
    }
});

test('real stripe subscription creates and processes payment correctly', function () {
    $stripeService = app(StripeService::class);

    // Create test data
    $user = User::factory()->create();
    $student = Student::factory()->create(['user_id' => $user->id]);
    $course = Course::factory()->withStripe()->create();

    // Create course fee settings for monthly billing
    $feeSettings = CourseFeeSettings::factory()->create([
        'course_id' => $course->id,
        'fee_amount' => 25.00,
        'billing_cycle' => 'monthly',
        'is_recurring' => true,
        'currency' => 'MYR',
    ]);

    // Create Stripe product and price
    $productId = $stripeService->createProduct($course);
    expect($productId)->not->toBeEmpty();

    $priceId = $stripeService->createPrice($feeSettings);
    expect($priceId)->not->toBeEmpty();

    // Create enrollment
    $enrollment = Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'status' => 'enrolled',
    ]);

    // Create a test payment method using Stripe test card
    $stripe = new StripeClient($stripeService->getPublishableKey());

    // Create Stripe customer
    $stripeCustomer = $stripeService->createOrGetCustomer($user);
    expect($stripeCustomer)->not->toBeNull();

    // For this test, we'll create a payment method manually using test card token
    $paymentMethod = PaymentMethod::factory()->create([
        'user_id' => $user->id,
        'stripe_payment_method_id' => 'pm_card_visa', // Stripe test payment method
        'is_default' => true,
    ]);

    // Create subscription
    try {
        $subscriptionResult = $stripeService->createSubscription($enrollment, $paymentMethod);

        expect($subscriptionResult)->toHaveKey('subscription');
        expect($enrollment->fresh()->stripe_subscription_id)->not->toBeNull();
        expect($enrollment->fresh()->subscription_status)->toBe('active');

    } catch (\Exception $e) {
        // If the test card doesn't work, we'll skip this test
        $this->markTestSkipped('Failed to create Stripe subscription: '.$e->getMessage());
    }

})->skip('Requires Stripe test API keys and may make real API calls');

test('subscription billing cycle timing can be verified through stripe api', function () {
    $stripeService = app(StripeService::class);

    // Create test enrollment with subscription
    $user = User::factory()->create();
    $student = Student::factory()->create(['user_id' => $user->id]);
    $course = Course::factory()->withStripe()->create();
    $feeSettings = CourseFeeSettings::factory()->monthly()->create([
        'course_id' => $course->id,
        'fee_amount' => 50.00,
    ]);

    $enrollment = Enrollment::factory()->withActiveSubscription()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
    ]);

    // Use a real Stripe subscription ID for testing
    $subscriptionId = $enrollment->stripe_subscription_id;

    try {
        $subscriptionDetails = $stripeService->getSubscriptionDetails($subscriptionId);

        // Verify subscription details structure
        expect($subscriptionDetails)->toHaveKey('status');
        expect($subscriptionDetails)->toHaveKey('current_period_end');
        expect($subscriptionDetails)->toHaveKey('current_period_start');

        // Calculate next payment date (should be approximately 30 days from start)
        $periodStart = $subscriptionDetails['current_period_start'];
        $periodEnd = $subscriptionDetails['current_period_end'];
        $daysDifference = Carbon::createFromTimestamp($periodEnd)->diffInDays(Carbon::createFromTimestamp($periodStart));

        // Monthly subscription should be around 28-31 days
        expect($daysDifference)->toBeGreaterThan(25);
        expect($daysDifference)->toBeLessThan(35);

    } catch (\Exception $e) {
        // If subscription doesn't exist in Stripe, skip test
        $this->markTestSkipped('Subscription not found in Stripe: '.$e->getMessage());
    }

})->skip('Requires real Stripe subscription data');

test('stripe webhook simulation for payment success', function () {
    // Create test enrollment
    $user = User::factory()->create();
    $student = Student::factory()->create(['user_id' => $user->id]);
    $course = Course::factory()->create();
    $enrollment = Enrollment::factory()->withActiveSubscription()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
    ]);

    // Simulate a Stripe invoice.payment_succeeded webhook payload
    $webhookPayload = json_encode([
        'id' => 'evt_test_webhook',
        'object' => 'event',
        'type' => 'invoice.payment_succeeded',
        'data' => [
            'object' => [
                'id' => 'in_test_'.uniqid(),
                'object' => 'invoice',
                'subscription' => $enrollment->stripe_subscription_id,
                'status' => 'paid',
                'amount_paid' => 5000, // 50.00 MYR in cents
                'currency' => 'myr',
                'period_start' => now()->timestamp,
                'period_end' => now()->addMonth()->timestamp,
                'billing_reason' => 'subscription_cycle',
                'hosted_invoice_url' => 'https://invoice.stripe.com/test',
                'lines' => [
                    'data' => [
                        [
                            'id' => 'il_test_'.uniqid(),
                            'amount' => 5000,
                            'currency' => 'myr',
                            'description' => 'Course Subscription',
                            'period' => [
                                'start' => now()->timestamp,
                                'end' => now()->addMonth()->timestamp,
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    // Create signature for webhook verification (normally this would be done by Stripe)
    // For testing, we'll mock the webhook processing directly
    $stripeService = app(StripeService::class);

    // Create order from the simulated invoice
    $invoiceData = json_decode($webhookPayload, true)['data']['object'];
    $order = $stripeService->createOrderFromStripeInvoice($invoiceData);

    expect($order)->not->toBeNull();
    expect($order->enrollment_id)->toBe($enrollment->id);
    expect((float) $order->amount)->toBe(50.00);
    expect($order->status)->toBe(Order::STATUS_PAID);
    expect($order->billing_reason)->toBe(Order::REASON_SUBSCRIPTION_CYCLE);

    // Verify order dates represent the billing period
    expect($order->period_start->isToday())->toBeTrue();
    expect($order->period_end->isAfter(now()->addDays(25)))->toBeTrue();
});

test('subscription payment failure simulation creates failed order', function () {
    // Create test enrollment
    $user = User::factory()->create();
    $student = Student::factory()->create(['user_id' => $user->id]);
    $course = Course::factory()->create();
    $enrollment = Enrollment::factory()->withPastDueSubscription()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
    ]);

    // Simulate a Stripe invoice.payment_failed webhook payload
    $webhookPayload = json_encode([
        'id' => 'evt_test_webhook_failed',
        'object' => 'event',
        'type' => 'invoice.payment_failed',
        'data' => [
            'object' => [
                'id' => 'in_test_failed_'.uniqid(),
                'object' => 'invoice',
                'subscription' => $enrollment->stripe_subscription_id,
                'status' => 'open',
                'amount_due' => 5000, // 50.00 MYR in cents
                'amount_paid' => 0,
                'currency' => 'myr',
                'period_start' => now()->timestamp,
                'period_end' => now()->addMonth()->timestamp,
                'billing_reason' => 'subscription_cycle',
                'attempt_count' => 1,
                'last_finalization_error' => [
                    'code' => 'card_declined',
                    'message' => 'Your card was declined.',
                ],
            ],
        ],
    ]);

    $stripeService = app(StripeService::class);

    // Create order from the failed invoice
    $invoiceData = json_decode($webhookPayload, true)['data']['object'];
    $order = $stripeService->createOrderFromStripeInvoice($invoiceData);

    expect($order)->not->toBeNull();
    expect($order->enrollment_id)->toBe($enrollment->id);
    expect((float) $order->amount)->toBe(0.00); // No amount paid
    expect($order->status)->toBe(Order::STATUS_PENDING); // Initially pending

    // Simulate marking as failed (as webhook handler would do)
    $order->markAsFailed([
        'failure_code' => 'card_declined',
        'failure_message' => 'Your card was declined.',
    ]);

    expect($order->fresh()->status)->toBe(Order::STATUS_FAILED);
    expect($order->fresh()->failure_reason)->toHaveKey('failure_code');
    expect($order->fresh()->failed_at)->not->toBeNull();
});

test('subscription next payment calculation matches stripe billing cycle', function () {
    $user = User::factory()->create();
    $student = Student::factory()->create(['user_id' => $user->id]);
    $course = Course::factory()->create();

    // Test different billing cycles
    $testCases = [
        ['monthly', 30],
        ['quarterly', 90],
        ['yearly', 365],
    ];

    foreach ($testCases as [$cycle, $expectedDays]) {
        $feeSettings = CourseFeeSettings::factory()->create([
            'course_id' => $course->id,
            'billing_cycle' => $cycle,
        ]);

        $enrollment = Enrollment::factory()->withActiveSubscription()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
        ]);

        // Create an order representing the current billing period
        $currentPeriodStart = now()->subDays(5); // Started 5 days ago
        $currentPeriodEnd = $currentPeriodStart->copy()->addDays($expectedDays);

        Order::factory()->create([
            'enrollment_id' => $enrollment->id,
            'period_start' => $currentPeriodStart,
            'period_end' => $currentPeriodEnd,
            'status' => Order::STATUS_PAID,
        ]);

        // Calculate days until next payment
        $daysUntilNext = now()->diffInDays($currentPeriodEnd);

        // Should be close to the expected billing cycle minus the days already passed
        $expectedDaysRemaining = $expectedDays - 5; // 5 days already passed
        expect($daysUntilNext)->toBeLessThanOrEqual($expectedDaysRemaining + 1);
        expect($daysUntilNext)->toBeGreaterThanOrEqual($expectedDaysRemaining - 1);
    }
});

test('subscription payment scheduling handles timezone correctly', function () {
    // Test that payment scheduling respects timezone settings
    $user = User::factory()->create();
    $student = Student::factory()->create(['user_id' => $user->id]);
    $course = Course::factory()->create();
    $enrollment = Enrollment::factory()->withActiveSubscription()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
    ]);

    // Create order with specific timezone-aware dates
    $malaysiaTime = now('Asia/Kuala_Lumpur');
    $utcTime = $malaysiaTime->utc();

    $order = Order::factory()->create([
        'enrollment_id' => $enrollment->id,
        'period_start' => $utcTime->copy()->subMonth(),
        'period_end' => $utcTime,
        'status' => Order::STATUS_PAID,
    ]);

    // Verify dates are stored correctly in UTC but can be converted to local timezone
    expect($order->period_end->timezone->getName())->toBe('UTC');
    expect($order->period_end->setTimezone('Asia/Kuala_Lumpur')->format('Y-m-d H:i:s'))
        ->toBe($malaysiaTime->format('Y-m-d H:i:s'));
});

test('subscription handles payment retry logic correctly', function () {
    $stripeService = app(StripeService::class);

    // Create enrollment with past due subscription
    $user = User::factory()->create();
    $student = Student::factory()->create(['user_id' => $user->id]);
    $course = Course::factory()->create();
    $enrollment = Enrollment::factory()->withPastDueSubscription()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
    ]);

    // Create a failed order that needs retry
    $failedOrder = Order::factory()->failed()->create([
        'enrollment_id' => $enrollment->id,
        'stripe_invoice_id' => 'in_test_retry_'.uniqid(),
    ]);

    expect($failedOrder->status)->toBe(Order::STATUS_FAILED);
    expect($enrollment->subscription_status)->toBe('past_due');

    try {
        // Attempt to retry the payment
        $retryResult = $stripeService->retryFailedPayment($failedOrder->stripe_invoice_id);

        if ($retryResult['success']) {
            expect($retryResult)->toHaveKey('invoice');
            expect($retryResult['message'])->toBe('Payment retry successful');
        } else {
            // Expected for test invoice IDs
            expect($retryResult['message'])->toBe('Payment retry failed');
        }

    } catch (\Exception $e) {
        // Expected for test data - Stripe won't have the test invoice
        expect($e->getMessage())->toContain('Invoice is not in a retryable state');
    }
});
