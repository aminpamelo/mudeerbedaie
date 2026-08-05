<?php

declare(strict_types=1);

use App\Models\Package;
use App\Models\Product;
use App\Models\ProductCart;
use App\Models\ProductOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

/** A bundle of one product, priced below its contents. */
function storePackage(array $attrs = []): Package
{
    $package = Package::factory()->create(array_merge([
        'status' => 'active',
        'name' => 'Doa Wirid Bundle',
        'slug' => 'doa-wirid-bundle',
        'price' => 150,
    ], $attrs));

    $product = Product::factory()->create(['type' => 'simple', 'status' => 'active', 'base_price' => 100, 'name' => 'Buku Doa']);
    $package->products()->attach($product->id, [
        'quantity' => 2,
        'sort_order' => 0,
        'original_price' => $product->base_price,
    ]);

    return $package->fresh();
}

function guestCart(): ProductCart
{
    return ProductCart::create([
        'session_id' => 'test-session',
        'currency' => 'MYR',
        'subtotal' => 0,
        'tax_amount' => 0,
        'total_amount' => 0,
        'discount_amount' => 0,
    ]);
}

it('shows the package detail page for an active package', function () {
    $package = storePackage();

    $this->get(route('storefront.package', $package->slug))
        ->assertOk()
        ->assertSee('Doa Wirid Bundle')
        ->assertSee('RM 150.00')
        ->assertSeeLivewire('store.package-cart');
});

it('returns 404 for an inactive package', function () {
    $package = storePackage(['status' => 'inactive']);

    $this->get(route('storefront.package', $package->slug))->assertNotFound();
});

it('adds a package to the cart with the bundle price', function () {
    $package = storePackage();

    Volt::test('store.package-cart', ['package' => $package])
        ->set('quantity', 2)
        ->call('add')
        ->assertDispatched('cart-updated');

    $cart = ProductCart::first();
    $item = $cart->items()->first();

    expect($item->isPackage())->toBeTrue()
        ->and($item->package_id)->toBe($package->id)
        ->and((float) $item->unit_price)->toBe(150.0)
        ->and((float) $item->total_price)->toBe(300.0)
        ->and((int) $cart->items()->sum('quantity'))->toBe(2)
        ->and((float) $cart->fresh()->subtotal)->toBe(300.0);
});

it('merges quantity when the same package is added twice', function () {
    $package = storePackage();
    $cart = guestCart();

    $cart->addPackage($package, 1);
    $cart->addPackage($package, 2);

    expect($cart->items()->count())->toBe(1)
        ->and((int) $cart->items()->first()->quantity)->toBe(3);
});

it('creates a package order line from the cart at checkout', function () {
    $package = storePackage();
    $cart = guestCart();
    $cart->addPackage($package, 1);

    $order = ProductOrder::createFromCart($cart, ['email' => 'buyer@example.test', 'phone' => '0123456789'], []);

    $item = $order->items()->first();

    expect($order->source)->toBe('storefront')
        ->and($item->isPackage())->toBeTrue()
        ->and($item->package_id)->toBe($package->id)
        ->and($item->product_id)->toBeNull()
        ->and($item->package_snapshot)->not->toBeNull()
        ->and($item->package_items_snapshot)->not->toBeNull()
        ->and((float) $item->total_price)->toBe(150.0);
});

it('handles a mixed cart of a product and a package', function () {
    $package = storePackage();
    $product = Product::factory()->create(['type' => 'simple', 'status' => 'active', 'base_price' => 25, 'name' => 'Standalone Book']);

    $cart = guestCart();
    $cart->addItem($product, quantity: 1);
    $cart->addPackage($package, 1);

    $order = ProductOrder::createFromCart($cart, ['email' => 'buyer@example.test', 'phone' => '0123456789'], []);

    expect($order->items()->count())->toBe(2)
        ->and($order->items()->whereNotNull('package_id')->count())->toBe(1)
        ->and($order->items()->whereNotNull('product_id')->count())->toBe(1);
});

it('links home package cards to the package detail page', function () {
    $package = storePackage();

    // The compact card add-to-cart component renders for the package.
    Volt::test('store.package-cart', ['package' => $package, 'compact' => true])
        ->call('add');

    expect(ProductCart::first()->items()->first()->package_id)->toBe($package->id);
});
