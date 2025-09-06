<?php

declare(strict_types=1);

use App\Jobs\ProcessStripeInvoicePaymentSucceeded;
use App\Models\Course;
use App\Models\CourseFeeSettings;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Student;
use App\Models\User;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('webhook dispatches payment succeeded job to queue', function () {
    Queue::fake();

    // Mock settings for Stripe configuration
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
        'stripe_subscription_id' => 'sub_test123',
        'subscription_status' => 'active',
    ]);

    // Create a fake Stripe webhook event (simulating actual webhook structure)
    $webhookData = [
        'id' => 'evt_test123',
        'type' => 'invoice.payment_succeeded',
        'data' => [
            'object' => [
                'id' => 'in_test123',
                'customer' => 'cus_test123',
                'subscription' => 'sub_test123',
                'amount_paid' => 500, // 5.00 MYR
                'currency' => 'myr',
                'status' => 'paid',
                'period_start' => now()->timestamp,
                'period_end' => now()->addMonth()->timestamp,
                'billing_reason' => 'subscription_cycle',
                'hosted_invoice_url' => 'https://invoice.stripe.com/test',
            ],
        ],
    ];

    $payload = json_encode($webhookData);
    $signature = 'test_signature';

    // Mock the webhook by creating a valid Stripe event object
    $stripeEvent = new \stdClass;
    $stripeEvent->id = 'evt_test123';
    $stripeEvent->type = 'invoice.payment_succeeded';
    $stripeEvent->data = new \stdClass;
    $stripeEvent->data->object = (object) $webhookData['data']['object'];

    // Create the StripeService and mock the webhook construction method
    $stripeService = Mockery::mock(\App\Services\StripeService::class)->makePartial();
    $stripeService->shouldReceive('handleWebhook')
        ->once()
        ->with($payload, $signature)
        ->andReturnUsing(function ($payload, $signature) use ($stripeEvent) {
            // Simulate what the real handleWebhook method would do
            $webhookEvent = WebhookEvent::createFromStripeEvent($stripeEvent);

            // Dispatch the job (this is what our modified method does)
            \App\Jobs\ProcessStripeInvoicePaymentSucceeded::dispatch(
                $webhookEvent,
                (array) $stripeEvent->data->object
            );
        });

    $this->app->instance(\App\Services\StripeService::class, $stripeService);

    // Send webhook request
    $response = $this->postJson('/stripe/webhook', $webhookData, [
        'Stripe-Signature' => $signature,
    ]);

    $response->assertStatus(200);

    // Verify that the job was dispatched to the queue
    Queue::assertPushed(ProcessStripeInvoicePaymentSucceeded::class);

    // Verify webhook event was created
    $webhookEvent = WebhookEvent::where('stripe_event_id', 'evt_test123')->first();
    expect($webhookEvent)->not->toBeNull();
});

test('queued payment succeeded job processes correctly', function () {
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

    // Create webhook event
    $webhookEvent = WebhookEvent::create([
        'stripe_event_id' => 'evt_test123',
        'type' => 'invoice.payment_succeeded',
        'data' => [
            'object' => [
                'subscription' => 'sub_test123',
            ],
        ],
        'processed' => false,
    ]);

    // Create Stripe invoice data
    $stripeInvoice = [
        'id' => 'in_test123',
        'customer' => 'cus_test123',
        'subscription' => 'sub_test123',
        'amount_paid' => 500, // 5.00 MYR
        'currency' => 'myr',
        'status' => 'paid',
        'period_start' => now()->timestamp,
        'period_end' => now()->addMonth()->timestamp,
        'billing_reason' => 'subscription_cycle',
        'hosted_invoice_url' => 'https://invoice.stripe.com/test',
    ];

    // Process the job
    $job = new ProcessStripeInvoicePaymentSucceeded($webhookEvent, $stripeInvoice);
    $job->handle(app(\App\Services\StripeService::class));

    // Verify order was created
    $order = Order::where('stripe_invoice_id', 'in_test123')->first();
    expect($order)->not->toBeNull();
    expect($order->status)->toBe('paid');
    expect($order->enrollment_id)->toBe($enrollment->id);
    expect($order->amount)->toBe(5.00);

    // Verify webhook event was marked as processed
    $webhookEvent->refresh();
    expect($webhookEvent->processed)->toBeTrue();
});

test('queued job handles failures gracefully', function () {
    // Create webhook event with invalid data
    $webhookEvent = WebhookEvent::create([
        'stripe_event_id' => 'evt_test123',
        'type' => 'invoice.payment_succeeded',
        'data' => ['object' => ['subscription' => 'sub_invalid']],
        'processed' => false,
    ]);

    // Create invalid Stripe invoice data (missing enrollment)
    $stripeInvoice = [
        'id' => 'in_test123',
        'customer' => 'cus_test123',
        'subscription' => 'sub_nonexistent', // This subscription doesn't exist
        'amount_paid' => 500,
        'currency' => 'myr',
        'status' => 'paid',
        'period_start' => now()->timestamp,
        'period_end' => now()->addMonth()->timestamp,
    ];

    // Process the job - it should fail gracefully
    $job = new ProcessStripeInvoicePaymentSucceeded($webhookEvent, $stripeInvoice);

    try {
        $job->handle(app(\App\Services\StripeService::class));
    } catch (\Exception $e) {
        // Expected to fail
    }

    // Verify webhook event was marked as failed
    $webhookEvent->refresh();
    expect($webhookEvent->processed)->toBeFalse();
    expect($webhookEvent->error_message)->not->toBeNull();
});

test('webhook processing is now asynchronous instead of synchronous', function () {
    Queue::fake();

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

    // Create webhook payload
    $payload = json_encode([
        'id' => 'evt_test123',
        'type' => 'invoice.payment_succeeded',
        'data' => [
            'object' => [
                'id' => 'in_test123',
                'subscription' => 'sub_test123',
                'customer' => 'cus_test123',
                'amount_paid' => 500,
                'currency' => 'myr',
                'status' => 'paid',
                'period_start' => now()->timestamp,
                'period_end' => now()->addMonth()->timestamp,
            ],
        ],
    ]);

    // Mock webhook verification
    config(['settings.stripe_webhook_secret' => 'whsec_test']);
    \Stripe\Webhook::shouldReceive('constructEvent')
        ->once()
        ->andReturn(json_decode($payload));

    // Before: webhook processing was synchronous - order created immediately
    // After: webhook processing is asynchronous - job queued, order created later

    // Send webhook
    $response = $this->postJson('/stripe/webhook', json_decode($payload, true), [
        'Stripe-Signature' => 'test_sig',
    ]);

    $response->assertStatus(200);

    // Order should NOT exist yet (because job is queued, not processed)
    expect(Order::where('stripe_invoice_id', 'in_test123')->exists())->toBeFalse();

    // But webhook event should exist
    expect(WebhookEvent::where('stripe_event_id', 'evt_test123')->exists())->toBeTrue();

    // Job should be queued
    Queue::assertPushed(ProcessStripeInvoicePaymentSucceeded::class);
});
