<?php

declare(strict_types=1);

use App\Jobs\ProcessStripeInvoicePaymentFailed;
use App\Jobs\ProcessStripeSubscriptionUpdated;
use App\Models\Course;
use App\Models\CourseFeeSettings;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Student;
use App\Models\User;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('subscription renewal failure due to insufficient funds creates failed order and updates subscription status', function () {
    // Mock settings for Stripe configuration
    $settingsService = Mockery::mock(\App\Services\SettingsService::class);
    $settingsService->shouldReceive('get')
        ->with('stripe_secret_key')
        ->andReturn('sk_test_123');
    $settingsService->shouldReceive('get')
        ->with('stripe_publishable_key')
        ->andReturn('pk_test_123');
    $settingsService->shouldReceive('get')
        ->with('currency', 'MYR')
        ->andReturn('MYR');
    $settingsService->shouldReceive('get')
        ->with('payment_mode', 'test')
        ->andReturn('test');

    $this->app->instance(\App\Services\SettingsService::class, $settingsService);

    // Create test data - active subscription
    $user = User::factory()->create();
    $student = Student::factory()->create(['user_id' => $user->id]);
    $course = Course::factory()->create();
    $feeSettings = CourseFeeSettings::factory()->create(['course_id' => $course->id]);
    $enrollment = Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'stripe_subscription_id' => 'sub_test_renewal_fail',
        'subscription_status' => 'active', // Currently active
        'status' => 'enrolled',
    ]);

    // Create existing successful order to establish billing history
    $lastOrder = Order::factory()->create([
        'enrollment_id' => $enrollment->id,
        'student_id' => $student->id,
        'course_id' => $course->id,
        'status' => 'paid',
        'amount' => 50.00,
        'period_start' => now()->subMonth(),
        'period_end' => now(),
        'billing_reason' => 'subscription_cycle',
        'metadata' => ['subscription_id' => 'sub_test_renewal_fail'],
    ]);

    // Verify initial state
    expect($enrollment->subscription_status)->toBe('active');
    expect($enrollment->hasActiveSubscription())->toBeTrue();

    // STEP 1: Failed invoice payment webhook
    $failedInvoiceWebhook = WebhookEvent::create([
        'stripe_event_id' => 'evt_invoice_failed_123',
        'type' => 'invoice.payment_failed',
        'data' => [
            'object' => [
                'subscription' => 'sub_test_renewal_fail',
            ],
        ],
        'processed' => false,
    ]);

    $failedInvoiceData = [
        'id' => 'in_failed_renewal_123',
        'customer' => 'cus_test123',
        'subscription' => 'sub_test_renewal_fail',
        'amount_due' => 5000, // 50.00 MYR
        'amount_paid' => 0,
        'currency' => 'myr',
        'status' => 'open',
        'period_start' => now()->timestamp,
        'period_end' => now()->addMonth()->timestamp,
        'billing_reason' => 'subscription_cycle',
        'attempt_count' => 1,
        'next_payment_attempt' => now()->addDays(3)->timestamp,
        'last_finalization_error' => [
            'code' => 'card_declined',
            'message' => 'Your card has insufficient funds.',
        ],
    ];

    // Process failed invoice job
    $failedJob = new ProcessStripeInvoicePaymentFailed($failedInvoiceWebhook, $failedInvoiceData);
    $failedJob->handle(app(\App\Services\StripeService::class));

    // Verify failed order was created
    $failedOrder = Order::where('stripe_invoice_id', 'in_failed_renewal_123')->first();
    expect($failedOrder)->not->toBeNull();
    expect($failedOrder->status)->toBe('failed');
    expect((float) $failedOrder->amount)->toBe(0.00); // Failed orders have zero amount
    expect($failedOrder->failure_reason)->toEqual([
        'failure_code' => 'card_declined',
        'failure_message' => 'Your card has insufficient funds.',
    ]);

    // Verify webhook was processed
    $failedInvoiceWebhook->refresh();
    expect($failedInvoiceWebhook->processed)->toBeTrue();

    // STEP 2: Subscription status update webhook (subscription goes to past_due)
    $subscriptionUpdateWebhook = WebhookEvent::create([
        'stripe_event_id' => 'evt_subscription_updated_123',
        'type' => 'customer.subscription.updated',
        'data' => [
            'object' => [
                'id' => 'sub_test_renewal_fail',
            ],
        ],
        'processed' => false,
    ]);

    $subscriptionData = [
        'id' => 'sub_test_renewal_fail',
        'customer' => 'cus_test123',
        'status' => 'past_due', // Stripe changed status to past_due
        'current_period_start' => now()->timestamp,
        'current_period_end' => now()->addMonth()->timestamp,
        'cancel_at_period_end' => false,
        'billing_cycle_anchor' => now()->timestamp,
        'latest_invoice' => 'in_failed_renewal_123',
    ];

    // Process subscription update job
    $updateJob = new ProcessStripeSubscriptionUpdated($subscriptionUpdateWebhook, $subscriptionData);
    $updateJob->handle(app(\App\Services\StripeService::class));

    // Verify enrollment subscription status was updated
    $enrollment->refresh();
    expect($enrollment->subscription_status)->toBe('past_due');
    expect($enrollment->hasActiveSubscription())->toBeFalse(); // No longer active
    expect($enrollment->isSubscriptionPastDue())->toBeTrue();
    expect($enrollment->getSubscriptionStatusLabel())->toBe('Past Due');

    // Verify webhook was processed
    $subscriptionUpdateWebhook->refresh();
    expect($subscriptionUpdateWebhook->processed)->toBeTrue();

    // STEP 3: Verify next payment date calculation
    // With past_due status, hasActiveSubscription() returns false, so getNextPaymentDate() returns null
    expect($enrollment->getNextPaymentDate())->toBeNull();
    expect($enrollment->getFormattedNextPaymentDate())->toBeNull();

    // STEP 4: Verify order counts and amounts
    expect($enrollment->orders()->count())->toBe(2); // 1 successful + 1 failed
    expect($enrollment->paidOrders()->count())->toBe(1);
    expect($enrollment->failedOrders()->count())->toBe(1);
    expect($enrollment->getTotalPaidAmountAttribute())->toBe(50.00);
    expect($enrollment->getTotalFailedAmountAttribute())->toBe(0.00); // Failed orders have zero amount
});

test('subscription goes to unpaid status after multiple failed attempts', function () {
    // Mock settings for Stripe configuration
    $settingsService = Mockery::mock(\App\Services\SettingsService::class);
    $settingsService->shouldReceive('get')
        ->with('stripe_secret_key')
        ->andReturn('sk_test_123');
    $settingsService->shouldReceive('get')
        ->with('stripe_publishable_key')
        ->andReturn('pk_test_123');

    $this->app->instance(\App\Services\SettingsService::class, $settingsService);

    // Create enrollment with past_due status (after first failure)
    $user = User::factory()->create();
    $student = Student::factory()->create(['user_id' => $user->id]);
    $course = Course::factory()->create();
    $enrollment = Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'stripe_subscription_id' => 'sub_test_unpaid',
        'subscription_status' => 'past_due', // Already past due from first failure
        'status' => 'enrolled',
    ]);

    // Create webhook for subscription going to unpaid status
    $webhookEvent = WebhookEvent::create([
        'stripe_event_id' => 'evt_subscription_unpaid_123',
        'type' => 'customer.subscription.updated',
        'data' => ['object' => ['id' => 'sub_test_unpaid']],
        'processed' => false,
    ]);

    $subscriptionData = [
        'id' => 'sub_test_unpaid',
        'customer' => 'cus_test123',
        'status' => 'unpaid', // Final failure state
        'current_period_start' => now()->timestamp,
        'current_period_end' => now()->addMonth()->timestamp,
    ];

    // Process the job
    $job = new ProcessStripeSubscriptionUpdated($webhookEvent, $subscriptionData);
    $job->handle(app(\App\Services\StripeService::class));

    // Verify subscription status updated to unpaid
    $enrollment->refresh();
    expect($enrollment->subscription_status)->toBe('unpaid');
    expect($enrollment->hasActiveSubscription())->toBeFalse();
    expect($enrollment->getSubscriptionStatusLabel())->toBe('Unpaid');
    expect($enrollment->getNextPaymentDate())->toBeNull(); // No next payment for inactive subscription
});

test('subscription gets canceled after extended unpaid period', function () {
    // Mock settings for Stripe configuration
    $settingsService = Mockery::mock(\App\Services\SettingsService::class);
    $settingsService->shouldReceive('get')
        ->with('stripe_secret_key')
        ->andReturn('sk_test_123');
    $settingsService->shouldReceive('get')
        ->with('stripe_publishable_key')
        ->andReturn('pk_test_123');

    $this->app->instance(\App\Services\SettingsService::class, $settingsService);

    // Create enrollment with unpaid status
    $user = User::factory()->create();
    $student = Student::factory()->create(['user_id' => $user->id]);
    $course = Course::factory()->create();
    $enrollment = Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'stripe_subscription_id' => 'sub_test_canceled',
        'subscription_status' => 'unpaid',
        'status' => 'enrolled',
    ]);

    // Create webhook for subscription being canceled
    $webhookEvent = WebhookEvent::create([
        'stripe_event_id' => 'evt_subscription_canceled_123',
        'type' => 'customer.subscription.updated',
        'data' => ['object' => ['id' => 'sub_test_canceled']],
        'processed' => false,
    ]);

    $subscriptionData = [
        'id' => 'sub_test_canceled',
        'customer' => 'cus_test123',
        'status' => 'canceled', // Subscription canceled by Stripe
        'canceled_at' => now()->timestamp,
        'cancel_at' => null,
        'cancel_at_period_end' => false,
    ];

    // Process the job
    $job = new ProcessStripeSubscriptionUpdated($webhookEvent, $subscriptionData);
    $job->handle(app(\App\Services\StripeService::class));

    // Verify subscription status updated to canceled
    $enrollment->refresh();
    expect($enrollment->subscription_status)->toBe('canceled');
    expect($enrollment->hasActiveSubscription())->toBeFalse();
    expect($enrollment->isSubscriptionCanceled())->toBeTrue();
    expect($enrollment->getSubscriptionStatusLabel())->toBe('Canceled');
    expect($enrollment->subscription_cancel_at)->not->toBeNull();
    expect($enrollment->getNextPaymentDate())->toBeNull(); // No next payment for canceled subscription
});

test('webhook processing handles missing enrollment gracefully', function () {
    // Mock settings for Stripe configuration
    $settingsService = Mockery::mock(\App\Services\SettingsService::class);
    $settingsService->shouldReceive('get')
        ->with('stripe_secret_key')
        ->andReturn('sk_test_123');
    $settingsService->shouldReceive('get')
        ->with('stripe_publishable_key')
        ->andReturn('pk_test_123');

    $this->app->instance(\App\Services\SettingsService::class, $settingsService);

    // Create webhook for non-existent subscription
    $webhookEvent = WebhookEvent::create([
        'stripe_event_id' => 'evt_missing_sub_123',
        'type' => 'customer.subscription.updated',
        'data' => ['object' => ['id' => 'sub_nonexistent']],
        'processed' => false,
    ]);

    $subscriptionData = [
        'id' => 'sub_nonexistent',
        'status' => 'past_due',
    ];

    // Process the job - should handle gracefully
    $job = new ProcessStripeSubscriptionUpdated($webhookEvent, $subscriptionData);
    $job->handle(app(\App\Services\StripeService::class));

    // Verify webhook was still marked as processed
    $webhookEvent->refresh();
    expect($webhookEvent->processed)->toBeTrue();
    expect($webhookEvent->error_message)->toBeNull();
});
