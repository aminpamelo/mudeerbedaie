<?php

declare(strict_types=1);

use App\Jobs\ProcessStripePaymentIntentSucceeded;
use App\Models\ProductOrder;
use App\Models\WebhookEvent;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * Covers the payment_intent.succeeded webhook job — the safety net that marks
 * funnel credit_card orders paid even when the synchronous Livewire callback
 * never fires (browser closed mid-payment, network blip, 3DS bounce).
 *
 * Direct Stripe.js confirmation is exercised by manual browser testing with
 * the test card 4242 4242 4242 4242 (see CLAUDE.md).
 */
beforeEach(function () {
    $this->paymentIntentId = 'pi_test_'.uniqid();

    $this->webhookEvent = WebhookEvent::create([
        'stripe_event_id' => 'evt_test_'.uniqid(),
        'type' => 'payment_intent.succeeded',
        'data' => ['id' => 'evt_test'],
    ]);

    $this->payload = [
        'id' => $this->paymentIntentId,
        'status' => 'succeeded',
        'amount' => 9900,
        'currency' => 'myr',
        'metadata' => ['funnel_id' => 1],
    ];
});

it('marks an unpaid funnel order as paid when the webhook fires', function () {
    $order = ProductOrder::factory()->create([
        'source' => 'funnel',
        'payment_status' => 'pending',
        'status' => 'pending',
        'metadata' => ['stripe_payment_intent_id' => $this->paymentIntentId],
    ]);

    (new ProcessStripePaymentIntentSucceeded($this->webhookEvent, $this->payload))->handle();

    $order->refresh();
    expect($order->payment_status)->toBe('paid');
    expect($order->status)->toBe('confirmed');
    expect($order->paid_time)->not->toBeNull();
    expect($this->webhookEvent->fresh()->processed)->toBeTrue();
});

it('is idempotent — re-running on an already-paid order leaves it untouched', function () {
    // Simulate the happy path: Livewire callback already marked the order paid.
    $paidTime = now()->subMinutes(5);
    $order = ProductOrder::factory()->create([
        'source' => 'funnel',
        'payment_status' => 'paid',
        'status' => 'confirmed',
        'paid_time' => $paidTime,
        'metadata' => ['stripe_payment_intent_id' => $this->paymentIntentId],
    ]);

    (new ProcessStripePaymentIntentSucceeded($this->webhookEvent, $this->payload))->handle();

    $order->refresh();
    expect($order->payment_status)->toBe('paid');
    // paid_time should not be overwritten — the original timestamp wins.
    // Compare with second-precision since SQLite truncates sub-second components.
    expect($order->paid_time->format('Y-m-d H:i:s'))->toBe($paidTime->format('Y-m-d H:i:s'));
    expect($this->webhookEvent->fresh()->processed)->toBeTrue();
});

it('skips when no funnel order matches the payment_intent_id', function () {
    // Order with the same payment_intent_id but source!='funnel' must be ignored
    // to avoid touching subscription/manual orders that have their own handlers.
    ProductOrder::factory()->create([
        'source' => 'stripe_subscription',
        'payment_status' => 'pending',
        'metadata' => ['stripe_payment_intent_id' => $this->paymentIntentId],
    ]);

    (new ProcessStripePaymentIntentSucceeded($this->webhookEvent, $this->payload))->handle();

    expect($this->webhookEvent->fresh()->processed)->toBeTrue();
    // The non-funnel order is unaffected.
    expect(ProductOrder::where('source', 'stripe_subscription')->first()->payment_status)
        ->toBe('pending');
});

it('marks the webhook as failed when payload has no payment_intent id', function () {
    (new ProcessStripePaymentIntentSucceeded($this->webhookEvent, ['status' => 'succeeded']))->handle();

    $this->webhookEvent->refresh();
    expect($this->webhookEvent->processed)->toBeFalse();
    expect($this->webhookEvent->error_message)->toBe('Payment intent ID missing from webhook payload');
});
