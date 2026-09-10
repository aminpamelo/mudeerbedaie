<?php

declare(strict_types=1);

use App\Models\ProductOrder;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($this->admin);
});

it('displays notes in orders list', function () {
    $order = ProductOrder::factory()->create([
        'internal_notes' => 'Test POS note',
    ]);

    Volt::test('admin.orders.order-list')
        ->assertSee('Test POS note');
});

it('displays customer notes when internal notes are empty', function () {
    $order = ProductOrder::factory()->create([
        'internal_notes' => null,
        'customer_notes' => 'Customer said something',
    ]);

    Volt::test('admin.orders.order-list')
        ->assertSee('Customer said something');
});

it('displays tracking number in orders list', function () {
    $order = ProductOrder::factory()->create([
        'tracking_id' => 'TRACK123456',
    ]);

    Volt::test('admin.orders.order-list')
        ->assertSee('TRACK123456');
});

it('can add a tracking number via the modal (save only)', function () {
    $order = ProductOrder::factory()->create([
        'tracking_id' => null,
        'status' => 'processing',
    ]);

    Volt::test('admin.orders.order-list')
        ->call('openTrackingModal', $order->id)
        ->assertSet('showTrackingModal', true)
        ->assertSet('trackingStep', 'input')
        ->set('trackingNumber', 'NEW-TRACK-789')
        ->call('checkTracking')
        ->assertSet('trackingStep', 'verify')
        ->set('trackingMarkShipped', true)
        ->call('saveTrackingOnly')
        ->assertSet('showTrackingModal', false);

    $fresh = $order->fresh();
    expect($fresh->tracking_id)->toBe('NEW-TRACK-789');
    expect($fresh->status)->toBe('shipped');
    expect($fresh->shipped_at)->not->toBeNull();
});

it('requires a tracking number before reaching the verify step', function () {
    $order = ProductOrder::factory()->create(['tracking_id' => null]);

    Volt::test('admin.orders.order-list')
        ->call('openTrackingModal', $order->id)
        ->set('trackingNumber', '')
        ->call('checkTracking')
        ->assertHasErrors('trackingNumber')
        ->assertSet('trackingStep', 'input');
});

it('can close the tracking modal without saving', function () {
    $order = ProductOrder::factory()->create(['tracking_id' => null]);

    Volt::test('admin.orders.order-list')
        ->call('openTrackingModal', $order->id)
        ->set('trackingNumber', 'SHOULD-NOT-SAVE')
        ->call('closeTrackingModal')
        ->assertSet('showTrackingModal', false)
        ->assertSet('trackingNumber', '');

    expect($order->fresh()->tracking_id)->toBeNull();
});
