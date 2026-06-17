<?php

declare(strict_types=1);

use App\Models\ProductOrder;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($this->admin);
});

it('displays the payment method column', function () {
    ProductOrder::factory()->create([
        'payment_method' => 'bank_transfer',
    ]);

    Volt::test('admin.orders.order-list')
        ->assertSee('Method')
        ->assertSee('Bank Transfer');
});

it('shows a whatsapp link when the phone number is contactable', function () {
    $order = ProductOrder::factory()->create([
        'customer_phone' => '60123456789',
    ]);

    expect($order->hasContactablePhone())->toBeTrue();
    expect($order->getWhatsAppUrl())->toBe('https://wa.me/60123456789');

    Volt::test('admin.orders.order-list')
        ->assertSee('wa.me/60123456789');
});

it('normalizes a local leading-zero phone number to a malaysian whatsapp link', function () {
    $order = ProductOrder::factory()->make([
        'customer_phone' => '0123456789',
    ]);

    expect($order->getWhatsAppUrl())->toBe('https://wa.me/60123456789');
});

it('hides the whatsapp link when the phone number is masked', function () {
    $order = ProductOrder::factory()->make([
        'customer_phone' => '60132****97',
    ]);

    expect($order->hasContactablePhone())->toBeFalse();
    expect($order->getWhatsAppUrl())->toBeNull();
});

it('hides the whatsapp link when no phone number exists', function () {
    $order = ProductOrder::factory()->make([
        'customer_phone' => null,
    ]);

    expect($order->getWhatsAppUrl())->toBeNull();
});

it('opens the order quick-view modal when the order number is clicked', function () {
    $order = ProductOrder::factory()->create();

    Volt::test('admin.orders.order-list')
        ->assertSet('showOrderModal', false)
        ->call('openOrderModal', $order->id)
        ->assertSet('showOrderModal', true)
        ->assertSet('selectedOrderId', $order->id)
        ->assertSee($order->order_number);
});
