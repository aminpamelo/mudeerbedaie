<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\ProductCart;
use App\Models\StockLevel;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

it('shows the detail page for an active simple product', function () {
    $product = Product::factory()->create([
        'type' => 'simple',
        'status' => 'active',
        'track_quantity' => false,
        'name' => 'Panduan Solat Lengkap',
        'base_price' => 79,
    ]);

    $this->get(route('storefront.product', $product->slug))
        ->assertOk()
        ->assertSee('Panduan Solat Lengkap')
        ->assertSee('RM 79.00')
        ->assertSeeLivewire('store.product-cart');
});

it('returns 404 for variable or inactive products', function () {
    $variable = Product::factory()->create(['type' => 'variable', 'status' => 'active']);
    $inactive = Product::factory()->create(['type' => 'simple', 'status' => 'inactive']);

    $this->get(route('storefront.product', $variable->slug))->assertNotFound();
    $this->get(route('storefront.product', $inactive->slug))->assertNotFound();
});

it('shows the sold-out state and hides the add-to-cart control', function () {
    $product = Product::factory()->create(['type' => 'simple', 'status' => 'active', 'track_quantity' => true, 'name' => 'Sold Out Book']);
    StockLevel::factory()->create(['product_id' => $product->id, 'quantity' => 0, 'reserved_quantity' => 0]);

    $this->get(route('storefront.product', $product->slug))
        ->assertOk()
        ->assertDontSeeLivewire('store.product-cart');
});

it('adds the chosen quantity to the cart from the product page', function () {
    Warehouse::factory()->create();
    $product = Product::factory()->create(['type' => 'simple', 'status' => 'active', 'track_quantity' => false]);

    Volt::test('store.product-cart', ['product' => $product])
        ->set('quantity', 3)
        ->call('add')
        ->assertDispatched('cart-updated');

    $cart = ProductCart::first();

    expect($cart)->not->toBeNull()
        ->and((int) $cart->items()->sum('quantity'))->toBe(3);
});

it('clamps the quantity stepper between 1 and 99', function () {
    $product = Product::factory()->create(['type' => 'simple', 'status' => 'active', 'track_quantity' => false]);

    Volt::test('store.product-cart', ['product' => $product])
        ->set('quantity', 500)
        ->assertSet('quantity', 99)
        ->set('quantity', 1)
        ->call('decrement')
        ->assertSet('quantity', 1)
        ->call('increment')
        ->assertSet('quantity', 2);
});

it('links product cards to the detail page', function () {
    $product = Product::factory()->create(['type' => 'simple', 'status' => 'active', 'track_quantity' => false, 'name' => 'Card Linked Product']);

    $html = view('components.store.product-card', [
        'product' => $product->load('primaryImage', 'category', 'stockLevels'),
    ])->render();

    expect($html)->toContain(route('storefront.product', $product->slug));
});
