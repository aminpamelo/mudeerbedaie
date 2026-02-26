<?php

declare(strict_types=1);

use App\Models\ProductOrder;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($this->admin);
});

it('displays notes column in orders list', function () {
    $order = ProductOrder::factory()->create([
        'internal_notes' => 'Test POS note',
    ]);

    Volt::test('admin.orders.order-list')
        ->assertSee('Notes')
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

it('displays tracking number column in orders list', function () {
    $order = ProductOrder::factory()->create([
        'tracking_id' => 'TRACK123456',
    ]);

    Volt::test('admin.orders.order-list')
        ->assertSee('Tracking No.')
        ->assertSee('TRACK123456');
});

it('can inline edit tracking number', function () {
    $order = ProductOrder::factory()->create([
        'tracking_id' => null,
    ]);

    Volt::test('admin.orders.order-list')
        ->call('startEditingTracking', $order->id, null)
        ->assertSet('editingTrackingOrderId', $order->id)
        ->set('editingTrackingValue', 'NEW-TRACK-789')
        ->call('saveTracking')
        ->assertSet('editingTrackingOrderId', null);

    expect($order->fresh()->tracking_id)->toBe('NEW-TRACK-789');
});

it('can cancel editing tracking number', function () {
    $order = ProductOrder::factory()->create();

    Volt::test('admin.orders.order-list')
        ->call('startEditingTracking', $order->id, 'OLD-TRACK')
        ->assertSet('editingTrackingOrderId', $order->id)
        ->call('cancelEditingTracking')
        ->assertSet('editingTrackingOrderId', null)
        ->assertSet('editingTrackingValue', '');
});
