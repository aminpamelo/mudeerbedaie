<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\ProductCart;
use App\Models\ProductCartItem;
use App\Models\ProductOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

it('captures the buyer name, phone and storefront source when creating an order from the cart', function () {
    $product = Product::factory()->create(['name' => 'Test Book']);
    $cart = ProductCart::create(['currency' => 'MYR', 'subtotal' => 39, 'tax_amount' => 2.34, 'total_amount' => 41.34, 'discount_amount' => 0]);
    ProductCartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1, 'unit_price' => 39, 'total_price' => 39, 'product_snapshot' => ['name' => 'Test Book']]);
    $cart->load('items.product');

    $address = ['first_name' => 'Ali', 'last_name' => 'Bin Abu', 'phone' => '0123456789', 'address_line_1' => '1 Jln', 'city' => 'KL', 'state' => 'Selangor', 'postal_code' => '40000', 'email' => 'buyer@example.com'];

    $order = ProductOrder::createFromCart(
        $cart,
        ['email' => 'buyer@example.com', 'phone' => '0123456789', 'notes' => 'leave at door'],
        ['billing' => $address, 'shipping' => $address],
    );

    expect($order->source)->toBe('storefront')
        ->and($order->customer_name)->toBe('Ali Bin Abu')
        ->and($order->customer_phone)->toBe('0123456789')
        ->and($order->guest_email)->toBe('buyer@example.com');
});

function adminOrderList(User $admin)
{
    return Volt::actingAs($admin)->test('admin.orders.order-list')->instance();
}

it('counts storefront orders as their own source category, separate from agent/company', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    ProductOrder::factory()->create(['source' => 'storefront', 'agent_id' => null, 'platform_id' => null, 'hidden_from_admin' => false]);
    ProductOrder::factory()->create(['source' => 'funnel', 'hidden_from_admin' => false]);
    ProductOrder::factory()->create(['source' => 'manual', 'agent_id' => null, 'platform_id' => null, 'hidden_from_admin' => false]);

    $counts = adminOrderList($admin)->getSourceCounts();

    expect($counts['all'])->toBe(3)
        ->and($counts['storefront'])->toBe(1)
        ->and($counts['funnel'])->toBe(1)
        // 'manual' falls into agent_company; the storefront order must NOT.
        ->and($counts['agent_company'])->toBe(1);
});

it('labels a storefront order with the Storefront badge (not "Company")', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $order = ProductOrder::factory()->create(['source' => 'storefront', 'agent_id' => null, 'platform_id' => null]);

    $src = adminOrderList($admin)->getOrderSource($order);

    expect($src['type'])->toBe('storefront')
        ->and($src['label'])->toBe('Storefront')
        ->and($src['color'])->toBe('emerald');
});

it('new factory orders default to the storefront source', function () {
    expect(ProductOrder::factory()->create()->source)->toBe('storefront');
});
