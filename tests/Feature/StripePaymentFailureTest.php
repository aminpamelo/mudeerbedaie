<?php

declare(strict_types=1);

use App\Jobs\ProcessStripeInvoicePaymentFailed;
use App\Models\Course;
use App\Models\CourseFeeSettings;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Student;
use App\Models\User;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('failed payment webhook creates order and marks as failed', function () {
    // Mock settings for Stripe configuration
    $settingsService = Mockery::mock(\App\Services\SettingsService::class);
    $settingsService->shouldReceive('get')
        ->with('stripe_secret_key')
        ->andReturn('sk_test_123');
    $settingsService->shouldReceive('get')
        ->with('currency', 'MYR')
        ->andReturn('MYR');
    $settingsService->shouldReceive('get')
        ->with('payment_mode', 'test')
        ->andReturn('test');

    $this->app->instance(\App\Services\SettingsService::class, $settingsService);

    // Create test data
    $user = User::factory()->create();
    $student = Student::factory()->create(['user_id' => $user->id]);
    $course = Course::factory()->create();
    $feeSettings = CourseFeeSettings::factory()->create(['course_id' => $course->id]);
    $enrollment = Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'stripe_subscription_id' => 'sub_test123',
        'subscription_status' => 'active',
    ]);

    // Create webhook event for failed payment
    $webhookEvent = WebhookEvent::create([
        'stripe_event_id' => 'evt_failed_payment',
        'type' => 'invoice.payment_failed',
        'data' => ['object' => ['subscription' => 'sub_test123']],
        'processed' => false,
    ]);

    // Create Stripe invoice data with payment failure
    $stripeInvoice = [
        'id' => 'in_failed_test123',
        'customer' => 'cus_test123',
        'subscription' => 'sub_test123',
        'amount_paid' => 0, // Payment failed - amount paid is 0
        'amount_due' => 500, // 5.00 MYR was due
        'currency' => 'myr',
        'status' => 'open', // Failed invoices remain open
        'period_start' => now()->timestamp,
        'period_end' => now()->addMonth()->timestamp,
        'billing_reason' => 'subscription_cycle',
        'hosted_invoice_url' => 'https://invoice.stripe.com/test_failed',
        'last_finalization_error' => [
            'code' => 'card_declined',
            'message' => 'Your card was declined.',
            'type' => 'card_error',
        ],
        'payment_intent' => 'pi_failed_test123',
        'charge' => null, // No charge for failed payment
    ];

    // Process the failed payment job
    $job = new ProcessStripeInvoicePaymentFailed($webhookEvent, $stripeInvoice);
    $job->handle(app(\App\Services\StripeService::class));

    // Verify order was created and marked as failed
    $order = Order::where('stripe_invoice_id', 'in_failed_test123')->first();
    expect($order)->not->toBeNull();
    expect($order->status)->toBe('failed');
    expect($order->enrollment_id)->toBe($enrollment->id);
    expect((float) $order->amount)->toBe(0.0); // Failed payment has no amount paid
    expect($order->failed_at)->not->toBeNull();

    // Verify failure reason is stored correctly
    expect($order->failure_reason)->toBeArray();
    expect($order->failure_reason['failure_code'])->toBe('card_declined');
    expect($order->failure_reason['failure_message'])->toBe('Your card was declined.');

    // Verify webhook event was marked as processed
    $webhookEvent->refresh();
    expect($webhookEvent->processed)->toBeTrue();
});

test('failed payment job handles missing error information gracefully', function () {
    // Mock settings for Stripe configuration
    $settingsService = Mockery::mock(\App\Services\SettingsService::class);
    $settingsService->shouldReceive('get')
        ->with('stripe_secret_key')
        ->andReturn('sk_test_123');
    $settingsService->shouldReceive('get')
        ->with('currency', 'MYR')
        ->andReturn('MYR');
    $settingsService->shouldReceive('get')
        ->with('payment_mode', 'test')
        ->andReturn('test');

    $this->app->instance(\App\Services\SettingsService::class, $settingsService);

    // Create test data
    $user = User::factory()->create();
    $student = Student::factory()->create(['user_id' => $user->id]);
    $course = Course::factory()->create();
    $feeSettings = CourseFeeSettings::factory()->create(['course_id' => $course->id]);
    $enrollment = Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'stripe_subscription_id' => 'sub_test456',
        'subscription_status' => 'active',
    ]);

    // Create webhook event for failed payment
    $webhookEvent = WebhookEvent::create([
        'stripe_event_id' => 'evt_failed_no_details',
        'type' => 'invoice.payment_failed',
        'data' => ['object' => ['subscription' => 'sub_test456']],
        'processed' => false,
    ]);

    // Create Stripe invoice data without detailed error information
    $stripeInvoice = [
        'id' => 'in_failed_no_details',
        'customer' => 'cus_test456',
        'subscription' => 'sub_test456',
        'amount_paid' => 0,
        'amount_due' => 500,
        'currency' => 'myr',
        'status' => 'open',
        'period_start' => now()->timestamp,
        'period_end' => now()->addMonth()->timestamp,
        'billing_reason' => 'subscription_cycle',
        'hosted_invoice_url' => 'https://invoice.stripe.com/test_failed',
        // No last_finalization_error provided
    ];

    // Process the failed payment job
    $job = new ProcessStripeInvoicePaymentFailed($webhookEvent, $stripeInvoice);
    $job->handle(app(\App\Services\StripeService::class));

    // Verify order was created and marked as failed
    $order = Order::where('stripe_invoice_id', 'in_failed_no_details')->first();
    expect($order)->not->toBeNull();
    expect($order->status)->toBe('failed');

    // Verify default failure reason when no specific error is provided
    expect($order->failure_reason)->toBeArray();
    expect($order->failure_reason['failure_code'])->toBeNull();
    expect($order->failure_reason['failure_message'])->toBe('Payment failed');
});

test('failed payment job queues correctly when webhook is received', function () {
    Queue::fake();

    // Mock settings for webhook verification
    $settingsService = Mockery::mock(\App\Services\SettingsService::class);
    $settingsService->shouldReceive('get')
        ->with('stripe_webhook_secret')
        ->andReturn('whsec_test123');
    $settingsService->shouldReceive('get')
        ->with('stripe_secret_key')
        ->andReturn('sk_test_123');
    $settingsService->shouldReceive('get')
        ->with('stripe_publishable_key')
        ->andReturn('pk_test_123');

    $this->app->instance(\App\Services\SettingsService::class, $settingsService);

    // Create test data
    $user = User::factory()->create();
    $student = Student::factory()->create(['user_id' => $user->id]);
    $course = Course::factory()->create();
    $feeSettings = CourseFeeSettings::factory()->create(['course_id' => $course->id]);
    $enrollment = Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'stripe_subscription_id' => 'sub_test789',
        'subscription_status' => 'active',
    ]);

    // Create failed payment webhook payload
    $webhookData = [
        'id' => 'evt_failed_queue_test',
        'type' => 'invoice.payment_failed',
        'data' => [
            'object' => [
                'id' => 'in_failed_queue_test',
                'customer' => 'cus_test789',
                'subscription' => 'sub_test789',
                'amount_paid' => 0,
                'amount_due' => 500,
                'currency' => 'myr',
                'status' => 'open',
                'period_start' => now()->timestamp,
                'period_end' => now()->addMonth()->timestamp,
                'billing_reason' => 'subscription_cycle',
                'hosted_invoice_url' => 'https://invoice.stripe.com/test_failed',
                'last_finalization_error' => [
                    'code' => 'insufficient_funds',
                    'message' => 'Your card has insufficient funds.',
                    'type' => 'card_error',
                ],
            ],
        ],
    ];

    $payload = json_encode($webhookData);
    $signature = 'test_signature';

    // Create the StripeService and mock the webhook construction method
    $stripeService = Mockery::mock(\App\Services\StripeService::class)->makePartial();
    $stripeService->shouldReceive('handleWebhook')
        ->once()
        ->with($payload, $signature)
        ->andReturnUsing(function ($payload, $signature) use ($webhookData) {
            // Simulate what the real handleWebhook method would do
            $webhookEvent = WebhookEvent::create([
                'stripe_event_id' => $webhookData['id'],
                'type' => $webhookData['type'],
                'data' => $webhookData,
                'processed' => false,
            ]);

            // Dispatch the failed payment job (this is what our modified method does)
            \App\Jobs\ProcessStripeInvoicePaymentFailed::dispatch(
                $webhookEvent,
                $webhookData['data']['object']
            );
        });

    $this->app->instance(\App\Services\StripeService::class, $stripeService);

    // Send webhook request
    $response = $this->postJson('/stripe/webhook', $webhookData, [
        'Stripe-Signature' => $signature,
    ]);

    $response->assertStatus(200);

    // Verify that the failed payment job was dispatched to the queue
    Queue::assertPushed(ProcessStripeInvoicePaymentFailed::class);

    // Verify webhook event was created
    $webhookEvent = WebhookEvent::where('stripe_event_id', 'evt_failed_queue_test')->first();
    expect($webhookEvent)->not->toBeNull();
    expect($webhookEvent->type)->toBe('invoice.payment_failed');
});

test('failed payment job logs detailed error information', function () {
    Log::spy();

    // Mock settings for Stripe configuration
    $settingsService = Mockery::mock(\App\Services\SettingsService::class);
    $settingsService->shouldReceive('get')
        ->with('stripe_secret_key')
        ->andReturn('sk_test_123');
    $settingsService->shouldReceive('get')
        ->with('currency', 'MYR')
        ->andReturn('MYR');
    $settingsService->shouldReceive('get')
        ->with('payment_mode', 'test')
        ->andReturn('test');

    $this->app->instance(\App\Services\SettingsService::class, $settingsService);

    // Create test data
    $user = User::factory()->create();
    $student = Student::factory()->create(['user_id' => $user->id]);
    $course = Course::factory()->create();
    $feeSettings = CourseFeeSettings::factory()->create(['course_id' => $course->id]);
    $enrollment = Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'stripe_subscription_id' => 'sub_log_test',
        'subscription_status' => 'active',
    ]);

    // Create webhook event for failed payment
    $webhookEvent = WebhookEvent::create([
        'stripe_event_id' => 'evt_log_test',
        'type' => 'invoice.payment_failed',
        'data' => ['object' => ['subscription' => 'sub_log_test']],
        'processed' => false,
    ]);

    // Create Stripe invoice data with detailed failure information
    $stripeInvoice = [
        'id' => 'in_log_test',
        'customer' => 'cus_log_test',
        'subscription' => 'sub_log_test',
        'amount_paid' => 0,
        'amount_due' => 750, // 7.50 MYR
        'currency' => 'myr',
        'status' => 'open',
        'period_start' => now()->timestamp,
        'period_end' => now()->addMonth()->timestamp,
        'billing_reason' => 'subscription_cycle',
        'hosted_invoice_url' => 'https://invoice.stripe.com/test_log',
        'last_finalization_error' => [
            'code' => 'expired_card',
            'message' => 'Your card has expired.',
            'type' => 'card_error',
            'decline_code' => 'expired_card',
        ],
    ];

    // Process the failed payment job
    $job = new ProcessStripeInvoicePaymentFailed($webhookEvent, $stripeInvoice);
    $job->handle(app(\App\Services\StripeService::class));

    // Verify detailed logging occurred
    Log::shouldHaveReceived('info')
        ->with('Processing invoice payment failed webhook', [
            'webhook_event_id' => $webhookEvent->id,
            'stripe_invoice_id' => 'in_log_test',
            'customer' => 'cus_log_test',
        ]);

    // Verify order creation logging with failure details
    Log::shouldHaveReceived('info')
        ->with('Order created and marked as failed', Mockery::on(function ($data) use ($webhookEvent) {
            return $data['webhook_event_id'] === $webhookEvent->id &&
                   isset($data['order_id']) &&
                   $data['failure_reason']['failure_code'] === 'expired_card' &&
                   $data['failure_reason']['failure_message'] === 'Your card has expired.';
        }));
});

test('failed payment system handles multiple failure scenarios correctly', function () {
    // Mock settings
    $settingsService = Mockery::mock(\App\Services\SettingsService::class);
    $settingsService->shouldReceive('get')->with('stripe_secret_key')->andReturn('sk_test_123');
    $settingsService->shouldReceive('get')->with('currency', 'MYR')->andReturn('MYR');
    $settingsService->shouldReceive('get')->with('payment_mode', 'test')->andReturn('test');
    $this->app->instance(\App\Services\SettingsService::class, $settingsService);

    // Create test data
    $user = User::factory()->create();
    $student = Student::factory()->create(['user_id' => $user->id]);
    $course = Course::factory()->create();
    $feeSettings = CourseFeeSettings::factory()->create(['course_id' => $course->id]);
    $enrollment = Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'stripe_subscription_id' => 'sub_multi_fail',
        'subscription_status' => 'active',
    ]);

    $failureScenarios = [
        [
            'code' => 'card_declined',
            'message' => 'Your card was declined.',
            'expected_status' => 'failed',
        ],
        [
            'code' => 'insufficient_funds',
            'message' => 'Your card has insufficient funds.',
            'expected_status' => 'failed',
        ],
        [
            'code' => 'expired_card',
            'message' => 'Your card has expired.',
            'expected_status' => 'failed',
        ],
        [
            'code' => 'processing_error',
            'message' => 'An error occurred while processing your card.',
            'expected_status' => 'failed',
        ],
    ];

    $orderCount = 0;
    foreach ($failureScenarios as $index => $scenario) {
        $orderCount++;

        // Create webhook event
        $webhookEvent = WebhookEvent::create([
            'stripe_event_id' => "evt_multi_fail_{$index}",
            'type' => 'invoice.payment_failed',
            'data' => ['object' => ['subscription' => 'sub_multi_fail']],
            'processed' => false,
        ]);

        // Create failed invoice data
        $stripeInvoice = [
            'id' => "in_multi_fail_{$index}",
            'customer' => 'cus_multi_fail',
            'subscription' => 'sub_multi_fail',
            'amount_paid' => 0,
            'amount_due' => 500,
            'currency' => 'myr',
            'status' => 'open',
            'period_start' => now()->timestamp,
            'period_end' => now()->addMonth()->timestamp,
            'billing_reason' => 'subscription_cycle',
            'hosted_invoice_url' => 'https://invoice.stripe.com/test_multi_fail',
            'last_finalization_error' => [
                'code' => $scenario['code'],
                'message' => $scenario['message'],
                'type' => 'card_error',
            ],
        ];

        // Process the job
        $job = new ProcessStripeInvoicePaymentFailed($webhookEvent, $stripeInvoice);
        $job->handle(app(\App\Services\StripeService::class));

        // Verify order creation and proper failure handling
        $order = Order::where('stripe_invoice_id', "in_multi_fail_{$index}")->first();
        expect($order)->not->toBeNull();
        expect($order->status)->toBe($scenario['expected_status']);
        expect($order->failure_reason['failure_code'])->toBe($scenario['code']);
        expect($order->failure_reason['failure_message'])->toBe($scenario['message']);
        expect($order->isFailed())->toBeTrue();
        expect($order->failed_at)->not->toBeNull();
    }

    // Verify we created the expected number of failed orders
    expect(Order::failed()->count())->toBe($orderCount);
    expect(Order::where('enrollment_id', $enrollment->id)->failed()->count())->toBe($orderCount);
});
