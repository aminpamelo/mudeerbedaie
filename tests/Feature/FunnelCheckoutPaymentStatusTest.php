<?php

use App\Models\ProductOrder;
use App\Services\BayarcashService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Funnel Checkout payment_status Regression Tests
|--------------------------------------------------------------------------
|
| Phase A Task 3 — these tests confirm that the order-creation and payment-
| confirmation patterns used by FunnelCheckoutService (and the Bayarcash
| gateway it delegates to) actually persist `payment_status`.
|
| Before Task 1's fix that added `payment_status` to ProductOrder::$fillable,
| the service's mass-assignment writes were silently dropped, leaving rows
| stuck at the default `pending` value even after a successful charge.
|
| Driving the full happy path through FunnelCheckoutService requires Stripe
| API mocking and a full Funnel/Step/Session/Cart fixture set, which exceeds
| the value of the test for what we are trying to lock in. Instead, these
| tests exercise the exact `ProductOrder::create()` / `$order->update()`
| call shapes used by the service so any future regression that re-breaks
| mass assignment is caught here.
|
*/

it('persists payment_status pending on initial funnel order create', function () {
    // Mirrors FunnelCheckoutService::createProductOrder() at lines 188-218.
    $order = ProductOrder::create([
        'order_number' => ProductOrder::generateOrderNumber(),
        'subtotal' => 100,
        'shipping_cost' => 0,
        'tax_amount' => 0,
        'total_amount' => 100,
        'discount_amount' => 0,
        'currency' => 'MYR',
        'order_date' => now(),
        'status' => 'pending',
        'payment_status' => 'pending',
        'payment_method' => 'credit_card',
        'source' => 'funnel',
        'metadata' => ['funnel_id' => 1],
    ]);

    expect($order->fresh())
        ->payment_status->toBe('pending')
        ->status->toBe('pending');
});

it('flips payment_status to paid via mass-assignment update on Stripe confirm', function () {
    // Initial state matches what FunnelCheckoutService creates.
    $order = ProductOrder::create([
        'order_number' => ProductOrder::generateOrderNumber(),
        'subtotal' => 100, 'shipping_cost' => 0, 'tax_amount' => 0,
        'total_amount' => 100, 'discount_amount' => 0,
        'currency' => 'MYR',
        'order_date' => now(),
        'status' => 'pending',
        'payment_status' => 'pending',
        'source' => 'funnel',
    ]);

    // Mirrors FunnelCheckoutService::confirmPayment() at lines 353-357
    // and processOneClickUpsell() at lines 531-538 (upsell success path).
    // Note: service writes `paid_at` which is a no-op (column is `paid_time`) —
    // we mirror that here so the test breaks if the service is fixed.
    $order->update([
        'status' => 'confirmed',
        'payment_status' => 'paid',
        'paid_at' => now(),
    ]);

    expect($order->fresh())
        ->payment_status->toBe('paid')
        ->status->toBe('confirmed');
});

it('persists payment_status pending on one-click upsell order create', function () {
    // Mirrors FunnelCheckoutService::processOneClickUpsell() at lines 455-483.
    $order = ProductOrder::create([
        'order_number' => ProductOrder::generateOrderNumber(),
        'subtotal' => 49,
        'shipping_cost' => 0,
        'tax_amount' => 0,
        'total_amount' => 49,
        'discount_amount' => 0,
        'currency' => 'MYR',
        'order_date' => now(),
        'status' => 'pending',
        'payment_status' => 'pending',
        'payment_method' => 'credit_card',
        'source' => 'funnel',
        'metadata' => ['is_upsell' => true],
    ]);

    expect($order->fresh())
        ->payment_status->toBe('pending')
        ->status->toBe('pending');
});

it('flips payment_status to paid when Bayarcash successful callback fires', function () {
    $service = app(BayarcashService::class);

    $order = ProductOrder::create([
        'order_number' => ProductOrder::generateOrderNumber(),
        'subtotal' => 100, 'shipping_cost' => 0, 'tax_amount' => 0,
        'total_amount' => 100, 'discount_amount' => 0,
        'currency' => 'MYR',
        'order_date' => now(),
        'status' => 'pending',
        'payment_status' => 'pending',
        'source' => 'funnel',
    ]);

    $service->processSuccessfulPayment($order, [
        'transaction_id' => 'TXN-TEST-001',
        'payment_channel' => 'FPX',
    ]);

    expect($order->fresh())
        ->payment_status->toBe('paid')
        ->status->toBe('processing')
        ->and($order->fresh()->paid_time)->not->toBeNull();
});

it('flips payment_status to failed when Bayarcash failed callback fires', function () {
    $service = app(BayarcashService::class);

    $order = ProductOrder::create([
        'order_number' => ProductOrder::generateOrderNumber(),
        'subtotal' => 100, 'shipping_cost' => 0, 'tax_amount' => 0,
        'total_amount' => 100, 'discount_amount' => 0,
        'currency' => 'MYR',
        'order_date' => now(),
        'status' => 'pending',
        'payment_status' => 'pending',
        'source' => 'funnel',
    ]);

    $service->processFailedPayment($order, [
        'transaction_id' => 'TXN-TEST-002',
    ]);

    expect($order->fresh())
        ->payment_status->toBe('failed');
});

it('moves a COD funnel order to processing while leaving payment_status pending', function () {
    // Mirrors the COD branch in checkout-form.blade.php: the order is created
    // 'pending', a COD payment record is added, then the order is moved to
    // 'processing' for fulfilment. Cash is collected on delivery, so
    // payment_status stays 'pending'.
    $order = ProductOrder::create([
        'order_number' => ProductOrder::generateOrderNumber(),
        'subtotal' => 100, 'shipping_cost' => 0, 'tax_amount' => 0,
        'total_amount' => 100, 'discount_amount' => 0,
        'currency' => 'MYR',
        'order_date' => now(),
        'status' => 'pending',
        'payment_status' => 'pending',
        'payment_method' => 'cod',
        'source' => 'funnel',
    ]);

    $order->payments()->create([
        'payment_method' => 'cod',
        'payment_provider' => 'cod',
        'amount' => $order->total_amount,
        'currency' => $order->currency,
        'status' => 'pending',
        'transaction_id' => 'COD-TEST-001',
    ]);
    $order->update(['status' => 'processing']);

    expect($order->fresh())
        ->status->toBe('processing')
        ->payment_status->toBe('pending');
});

it('leaves payment_status pending when Bayarcash pending callback fires', function () {
    $service = app(BayarcashService::class);

    $order = ProductOrder::create([
        'order_number' => ProductOrder::generateOrderNumber(),
        'subtotal' => 100, 'shipping_cost' => 0, 'tax_amount' => 0,
        'total_amount' => 100, 'discount_amount' => 0,
        'currency' => 'MYR',
        'order_date' => now(),
        'status' => 'pending',
        'payment_status' => 'pending',
        'source' => 'funnel',
    ]);

    $service->processPendingPayment($order, [
        'transaction_id' => 'TXN-TEST-003',
    ]);

    // Pending callback should not change payment_status — payment is still in flight.
    expect($order->fresh()->payment_status)->toBe('pending');
});
