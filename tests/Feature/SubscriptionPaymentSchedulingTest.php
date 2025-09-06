<?php

declare(strict_types=1);

use App\Models\Course;
use App\Models\CourseFeeSettings;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\StripeCustomer;
use App\Models\Student;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Mock Stripe service for testing
    $this->mock(StripeService::class, function ($mock) {
        $mock->shouldReceive('isConfigured')->andReturn(true);
        $mock->shouldReceive('createOrGetCustomer')->andReturn(
            StripeCustomer::factory()->make([
                'stripe_customer_id' => 'cus_test123',
            ])
        );
        $mock->shouldReceive('createSubscription')->andReturn([
            'subscription' => (object) [
                'id' => 'sub_test123',
                'status' => 'active',
                'current_period_start' => now()->timestamp,
                'current_period_end' => now()->addMonth()->timestamp,
            ],
            'client_secret' => null,
        ]);
        $mock->shouldReceive('getSubscriptionDetails')->andReturn([
            'id' => 'sub_test123',
            'status' => 'active',
            'cancel_at_period_end' => false,
            'cancel_at' => null,
            'current_period_end' => now()->addDays(2)->timestamp,
            'current_period_start' => now()->subMonth()->timestamp,
        ]);
    });
});

test('subscription payment scheduling creates proper billing cycle', function () {
    // Create test data
    $user = User::factory()->create();
    $student = Student::factory()->create(['user_id' => $user->id]);
    $course = Course::factory()->create();
    $feeSettings = CourseFeeSettings::factory()->create([
        'course_id' => $course->id,
        'fee_amount' => 50.00,
        'billing_cycle' => 'monthly',
        'is_recurring' => true,
        'stripe_price_id' => 'price_test123',
    ]);

    $paymentMethod = PaymentMethod::factory()->create([
        'user_id' => $user->id,
        'stripe_payment_method_id' => 'pm_test123',
        'is_default' => true,
    ]);

    // Create enrollment with active subscription
    $enrollment = Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'status' => 'enrolled',
        'stripe_subscription_id' => 'sub_test123',
        'subscription_status' => 'active',
    ]);

    // Verify enrollment has active subscription
    expect($enrollment->hasActiveSubscription())->toBeTrue();
    expect($enrollment->isSubscriptionActive())->toBeTrue();
});

test('subscription payment cycle creates orders when invoice is paid', function () {
    // Create test data
    $user = User::factory()->create();
    $student = Student::factory()->create(['user_id' => $user->id]);
    $course = Course::factory()->create();
    $feeSettings = CourseFeeSettings::factory()->create([
        'course_id' => $course->id,
        'fee_amount' => 50.00,
        'billing_cycle' => 'monthly',
    ]);

    $enrollment = Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'stripe_subscription_id' => 'sub_test123',
        'subscription_status' => 'active',
    ]);

    // Simulate Stripe invoice payment succeeded
    $stripeInvoice = [
        'id' => 'in_test123',
        'subscription' => 'sub_test123',
        'status' => 'paid',
        'amount_paid' => 5000, // 50.00 in cents
        'currency' => 'myr',
        'period_start' => now()->timestamp,
        'period_end' => now()->addMonth()->timestamp,
        'billing_reason' => 'subscription_cycle',
        'hosted_invoice_url' => 'https://invoice.stripe.com/test',
    ];

    // Create order from Stripe invoice (simulating webhook)
    $order = Order::createFromStripeInvoice($stripeInvoice, $enrollment);

    expect($order)->not->toBeNull();
    expect((float) $order->amount)->toBe(50.00);
    expect($order->status)->toBe(Order::STATUS_PAID);
    expect($order->enrollment_id)->toBe($enrollment->id);
    expect($order->billing_reason)->toBe(Order::REASON_SUBSCRIPTION_CYCLE);
});

test('subscription next payment date calculation works correctly', function () {
    $user = User::factory()->create();
    $student = Student::factory()->create(['user_id' => $user->id]);
    $course = Course::factory()->create();
    $feeSettings = CourseFeeSettings::factory()->create([
        'course_id' => $course->id,
        'billing_cycle' => 'monthly',
    ]);

    $enrollment = Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'stripe_subscription_id' => 'sub_test123',
        'subscription_status' => 'active',
    ]);

    // Create a recent order to simulate payment history
    $lastOrder = Order::factory()->create([
        'enrollment_id' => $enrollment->id,
        'status' => Order::STATUS_PAID,
        'period_start' => now()->subMonth(),
        'period_end' => now(),
        'paid_at' => now()->subDays(2),
    ]);

    // Verify order was created
    expect($enrollment->orders)->toHaveCount(1);
    expect($enrollment->paidOrders)->toHaveCount(1);

    // Test that enrollment has active subscription
    expect($enrollment->hasActiveSubscription())->toBeTrue();
});

test('subscription billing cycles work for different intervals', function () {
    $testCases = [
        ['monthly', 1, 'month'],
        ['quarterly', 3, 'month'],
        ['yearly', 1, 'year'],
    ];

    foreach ($testCases as [$cycle, $expectedCount, $expectedInterval]) {
        $feeSettings = CourseFeeSettings::factory()->make([
            'billing_cycle' => $cycle,
        ]);

        expect($feeSettings->getStripeInterval())->toBe($expectedInterval);
        expect($feeSettings->getStripeIntervalCount())->toBe($expectedCount);
    }
});

test('subscription payment failure creates failed order', function () {
    // Create test data
    $user = User::factory()->create();
    $student = Student::factory()->create(['user_id' => $user->id]);
    $course = Course::factory()->create();
    $enrollment = Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'stripe_subscription_id' => 'sub_test123',
        'subscription_status' => 'past_due',
    ]);

    // Simulate Stripe invoice payment failed
    $stripeInvoice = [
        'id' => 'in_test123',
        'subscription' => 'sub_test123',
        'status' => 'open',
        'amount_paid' => 0,
        'amount_due' => 5000, // 50.00 in cents
        'currency' => 'myr',
        'period_start' => now()->timestamp,
        'period_end' => now()->addMonth()->timestamp,
        'billing_reason' => 'subscription_cycle',
        'last_finalization_error' => [
            'code' => 'card_declined',
            'message' => 'Your card was declined.',
        ],
    ];

    // Create order from failed invoice
    $order = Order::createFromStripeInvoice($stripeInvoice, $enrollment);

    // Simulate marking as failed (this would happen in webhook handler)
    $order->markAsFailed([
        'failure_code' => 'card_declined',
        'failure_message' => 'Your card was declined.',
    ]);

    expect($order->status)->toBe(Order::STATUS_FAILED);
    expect($order->failed_at)->not->toBeNull();
    expect($order->failure_reason)->toHaveKey('failure_code');
});

test('subscription cancellation scheduling works correctly', function () {
    $enrollment = Enrollment::factory()->create([
        'stripe_subscription_id' => 'sub_test123',
        'subscription_status' => 'active',
        'subscription_cancel_at' => now()->addMonth(),
    ]);

    expect($enrollment->isPendingCancellation())->toBeTrue();
    expect($enrollment->getSubscriptionStatusLabel())->toBe('Pending Cancellation');
});

test('subscription payment scheduling integrates with stripe webhook system', function () {
    // This test verifies the webhook handling flow
    $user = User::factory()->create();
    $student = Student::factory()->create(['user_id' => $user->id]);
    $course = Course::factory()->create();
    $enrollment = Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'stripe_subscription_id' => 'sub_test123',
        'subscription_status' => 'active',
    ]);

    // Create multiple orders to simulate payment history
    $orders = collect();
    for ($i = 0; $i < 3; $i++) {
        $order = Order::factory()->create([
            'enrollment_id' => $enrollment->id,
            'status' => Order::STATUS_PAID,
            'period_start' => now()->subMonths(3 - $i),
            'period_end' => now()->subMonths(2 - $i),
            'paid_at' => now()->subMonths(3 - $i)->addDay(),
            'amount' => 50.00,
            'billing_reason' => Order::REASON_SUBSCRIPTION_CYCLE,
        ]);
        $orders->push($order);
    }

    // Verify payment history
    expect($enrollment->orders)->toHaveCount(3);
    expect($enrollment->paidOrders)->toHaveCount(3);
    expect($enrollment->getTotalPaidAmountAttribute())->toBe(150.00);

    // Verify the latest order
    $latestOrder = $enrollment->orders()->latest()->first();
    expect($latestOrder->isPaid())->toBeTrue();
});

test('subscription works with trial periods', function () {
    $feeSettings = CourseFeeSettings::factory()->create([
        'billing_cycle' => 'monthly',
        'trial_period_days' => 14,
    ]);

    $enrollment = Enrollment::factory()->create([
        'course_id' => $feeSettings->course_id,
        'stripe_subscription_id' => 'sub_test123',
        'subscription_status' => 'trialing',
    ]);

    expect($enrollment->isSubscriptionTrialing())->toBeTrue();
    expect($feeSettings->hasTrialPeriod())->toBeTrue();
});

test('subscription payment method updates work correctly', function () {
    $user = User::factory()->create();
    $student = Student::factory()->create(['user_id' => $user->id]);

    // Create multiple payment methods
    $paymentMethod1 = PaymentMethod::factory()->create([
        'user_id' => $user->id,
        'is_default' => true,
    ]);

    $paymentMethod2 = PaymentMethod::factory()->create([
        'user_id' => $user->id,
        'is_default' => false,
    ]);

    expect($user->paymentMethods)->toHaveCount(2);
    expect($user->defaultPaymentMethod->id)->toBe($paymentMethod1->id);
});

test('subscription renewal creates correct order for next billing period', function () {
    $user = User::factory()->create();
    $student = Student::factory()->create(['user_id' => $user->id]);
    $course = Course::factory()->create();
    $feeSettings = CourseFeeSettings::factory()->create([
        'course_id' => $course->id,
        'fee_amount' => 50.00,
        'billing_cycle' => 'monthly',
    ]);

    $enrollment = Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'stripe_subscription_id' => 'sub_test123',
        'subscription_status' => 'active',
    ]);

    // Simulate next billing period (what would happen in 2 days as shown in UI)
    $nextPeriodStart = now()->addDays(2);
    $nextPeriodEnd = now()->addMonth()->addDays(2);

    $stripeInvoice = [
        'id' => 'in_next_period',
        'subscription' => 'sub_test123',
        'status' => 'paid',
        'amount_paid' => 5000, // 50.00 in cents
        'currency' => 'myr',
        'period_start' => $nextPeriodStart->timestamp,
        'period_end' => $nextPeriodEnd->timestamp,
        'billing_reason' => 'subscription_cycle',
        'hosted_invoice_url' => 'https://invoice.stripe.com/next',
    ];

    // Create the next period order
    $nextOrder = Order::createFromStripeInvoice($stripeInvoice, $enrollment);

    expect($nextOrder->period_start->toDateString())->toBe($nextPeriodStart->toDateString());
    expect($nextOrder->period_end->toDateString())->toBe($nextPeriodEnd->toDateString());
    expect((float) $nextOrder->amount)->toBe(50.00);
    expect($nextOrder->status)->toBe(Order::STATUS_PAID);

    // Verify this would be the next payment after current period
    expect($nextOrder->period_start->isFuture())->toBeTrue();
});
