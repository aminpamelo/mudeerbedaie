<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\ProductCart;
use App\Models\ProductOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

function cartForUser(User $user): ProductCart
{
    $cart = ProductCart::create([
        'user_id' => $user->id,
        'currency' => 'MYR',
    ]);

    $product = Product::factory()->create(['track_quantity' => false, 'base_price' => 50]);
    $cart->addItem($product, quantity: 1);

    return $cart->fresh(['items']);
}

it('defaults to FPX and only offers FPX for online payment', function () {
    $user = User::factory()->create();
    cartForUser($user);

    Volt::actingAs($user)->test('cart.checkout')
        ->assertSet('paymentMethod', 'fpx')
        ->set('currentStep', 'payment')
        ->assertSee(__('store.co_pm_fpx'))
        ->assertDontSee(__('store.co_pm_credit_card'))
        ->assertDontSee(__('store.co_pm_grabpay'))
        ->assertDontSee(__('store.co_pm_boost'));
});

it('rejects a removed payment method like credit_card without creating an order', function () {
    $user = User::factory()->create();
    cartForUser($user);

    Volt::actingAs($user)->test('cart.checkout')
        ->set('paymentMethod', 'credit_card')
        ->call('processOrder')
        ->assertDispatched('checkout-error');

    expect(ProductOrder::count())->toBe(0);
});
