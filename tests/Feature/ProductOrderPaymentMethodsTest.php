<?php

use App\Models\ProductOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('marks payment as confirmed with receipt and user', function () {
    $accountant = User::factory()->create();
    $order = ProductOrder::factory()->create(['payment_status' => 'pending', 'status' => 'pending']);

    $order->markPaymentAsConfirmed($accountant->id, 'receipts/test.pdf');

    expect($order->fresh())
        ->payment_status->toBe('paid')
        ->payment_confirmed_by_user_id->toBe($accountant->id)
        ->receipt_attachment->toBe('receipts/test.pdf')
        ->status->toBe('confirmed')
        ->and($order->fresh()->payment_confirmed_at)->not->toBeNull()
        ->and($order->fresh()->paid_time)->not->toBeNull();
});

it('does not overwrite paid_time if already set', function () {
    $accountant = User::factory()->create();
    $existingPaidTime = now()->subDays(3);
    $order = ProductOrder::factory()->create([
        'payment_status' => 'pending',
        'paid_time' => $existingPaidTime,
    ]);

    $order->markPaymentAsConfirmed($accountant->id, 'receipts/test.pdf');

    expect($order->fresh()->paid_time->toDateTimeString())
        ->toBe($existingPaidTime->toDateTimeString());
});

it('does not override status if already past pending', function () {
    $accountant = User::factory()->create();
    $order = ProductOrder::factory()->create([
        'payment_status' => 'pending',
        'status' => 'shipped',
    ]);

    $order->markPaymentAsConfirmed($accountant->id, 'receipts/test.pdf');

    expect($order->fresh()->status)->toBe('shipped');
});

it('marks payment as rejected with reason', function () {
    $accountant = User::factory()->create();
    $order = ProductOrder::factory()->create(['payment_status' => 'pending']);

    $order->markPaymentAsRejected($accountant->id, 'No transfer received');

    expect($order->fresh())
        ->payment_status->toBe('failed')
        ->payment_rejection_reason->toBe('No transfer received')
        ->payment_confirmed_by_user_id->toBe($accountant->id);
});

it('paid scope returns only paid orders', function () {
    ProductOrder::factory()->create(['payment_status' => 'paid']);
    ProductOrder::factory()->create(['payment_status' => 'pending']);
    ProductOrder::factory()->create(['payment_status' => 'failed']);

    expect(ProductOrder::paid()->count())->toBe(1);
});

it('awaitingPayment scope returns only pending orders', function () {
    ProductOrder::factory()->create(['payment_status' => 'paid']);
    ProductOrder::factory()->create(['payment_status' => 'pending']);
    ProductOrder::factory()->create(['payment_status' => 'pending']);

    expect(ProductOrder::awaitingPayment()->count())->toBe(2);
});

it('paymentConfirmedBy returns the confirming user', function () {
    $accountant = User::factory()->create(['name' => 'Accountant Alice']);
    $order = ProductOrder::factory()->create([
        'payment_confirmed_by_user_id' => $accountant->id,
    ]);

    expect($order->paymentConfirmedBy)
        ->not->toBeNull()
        ->name->toBe('Accountant Alice');
});
