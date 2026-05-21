<?php

declare(strict_types=1);

use App\Models\ProductOrder;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);

    $this->paid = ProductOrder::factory()->create([
        'order_number' => 'PAID-ORDER-1',
        'payment_status' => 'paid',
    ]);
    $this->pending = ProductOrder::factory()->create([
        'order_number' => 'PENDING-ORDER-1',
        'payment_status' => 'pending',
    ]);
    $this->failed = ProductOrder::factory()->create([
        'order_number' => 'FAILED-ORDER-1',
        'payment_status' => 'failed',
    ]);
    $this->refunded = ProductOrder::factory()->create([
        'order_number' => 'REFUNDED-ORDER-1',
        'payment_status' => 'refunded',
    ]);
});

it('shows all orders by default', function () {
    $this->actingAs($this->admin);

    Volt::test('admin.orders.order-list')
        ->assertSee('PAID-ORDER-1')
        ->assertSee('PENDING-ORDER-1')
        ->assertSee('FAILED-ORDER-1')
        ->assertSee('REFUNDED-ORDER-1');
});

it('filters by paid', function () {
    $this->actingAs($this->admin);

    Volt::test('admin.orders.order-list')
        ->set('paymentStatusFilter', 'paid')
        ->assertSee('PAID-ORDER-1')
        ->assertDontSee('PENDING-ORDER-1')
        ->assertDontSee('FAILED-ORDER-1')
        ->assertDontSee('REFUNDED-ORDER-1');
});

it('filters by pending', function () {
    $this->actingAs($this->admin);

    Volt::test('admin.orders.order-list')
        ->set('paymentStatusFilter', 'pending')
        ->assertDontSee('PAID-ORDER-1')
        ->assertSee('PENDING-ORDER-1')
        ->assertDontSee('FAILED-ORDER-1')
        ->assertDontSee('REFUNDED-ORDER-1');
});

it('filters by failed', function () {
    $this->actingAs($this->admin);

    Volt::test('admin.orders.order-list')
        ->set('paymentStatusFilter', 'failed')
        ->assertDontSee('PAID-ORDER-1')
        ->assertDontSee('PENDING-ORDER-1')
        ->assertSee('FAILED-ORDER-1')
        ->assertDontSee('REFUNDED-ORDER-1');
});

it('filters by refunded', function () {
    $this->actingAs($this->admin);

    Volt::test('admin.orders.order-list')
        ->set('paymentStatusFilter', 'refunded')
        ->assertDontSee('PAID-ORDER-1')
        ->assertDontSee('PENDING-ORDER-1')
        ->assertDontSee('FAILED-ORDER-1')
        ->assertSee('REFUNDED-ORDER-1');
});

it('resets pagination when payment status filter changes', function () {
    $this->actingAs($this->admin);

    Volt::test('admin.orders.order-list')
        ->set('paginators.page', 2)
        ->set('paymentStatusFilter', 'paid')
        ->assertSet('paginators.page', 1);
});
