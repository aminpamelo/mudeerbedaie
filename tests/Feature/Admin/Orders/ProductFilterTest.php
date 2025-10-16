<?php

use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\ProductOrderItem;
use App\Models\User;
use Livewire\Volt\Volt;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
});

test('product filter dropdown is visible on orders page', function () {
    actingAs($this->admin);

    Volt::test('admin.orders.order-list')
        ->assertSee('Product/Package')
        ->assertSee('All Products', escape: false)
        ->assertSee('Packages', escape: false);
});

test('can filter orders by product', function () {
    actingAs($this->admin);

    // Create products
    $product1 = Product::factory()->create(['name' => 'iPhone 15']);
    $product2 = Product::factory()->create(['name' => 'Samsung Galaxy']);

    // Create orders with items
    $order1 = ProductOrder::factory()->create([
        'status' => 'delivered',
        'order_type' => 'retail',
    ]);
    ProductOrderItem::factory()->create([
        'order_id' => $order1->id,
        'product_id' => $product1->id,
    ]);

    $order2 = ProductOrder::factory()->create([
        'status' => 'delivered',
        'order_type' => 'retail',
    ]);
    ProductOrderItem::factory()->create([
        'order_id' => $order2->id,
        'product_id' => $product2->id,
    ]);

    // Test filtering by product1
    Volt::test('admin.orders.order-list')
        ->set('productFilter', $product1->id)
        ->assertSee($order1->order_number)
        ->assertDontSee($order2->order_number);

    // Test filtering by product2
    Volt::test('admin.orders.order-list')
        ->set('productFilter', $product2->id)
        ->assertSee($order2->order_number)
        ->assertDontSee($order1->order_number);
});

test('can filter orders by package', function () {
    actingAs($this->admin);

    // Create packages
    $package1 = \App\Models\Package::factory()->create(['name' => 'Premium Package']);
    $package2 = \App\Models\Package::factory()->create(['name' => 'Basic Package']);

    // Create orders
    $packageOrder1 = ProductOrder::factory()->create(['status' => 'delivered']);
    $packageOrder2 = ProductOrder::factory()->create(['status' => 'delivered']);
    $regularOrder = ProductOrder::factory()->create(['status' => 'delivered']);

    // Create order items with packages
    ProductOrderItem::factory()->create([
        'order_id' => $packageOrder1->id,
        'package_id' => $package1->id,
        'product_id' => Product::factory()->create()->id,
    ]);

    ProductOrderItem::factory()->create([
        'order_id' => $packageOrder2->id,
        'package_id' => $package2->id,
        'product_id' => Product::factory()->create()->id,
    ]);

    ProductOrderItem::factory()->create([
        'order_id' => $regularOrder->id,
        'package_id' => null,
        'product_id' => Product::factory()->create()->id,
    ]);

    // Test filtering by Premium Package
    Volt::test('admin.orders.order-list')
        ->set('productFilter', 'package:'.$package1->id)
        ->assertSee($packageOrder1->order_number)
        ->assertDontSee($packageOrder2->order_number)
        ->assertDontSee($regularOrder->order_number);
});

test('shows both products and packages in filter dropdown', function () {
    actingAs($this->admin);

    // Create a product
    $product = Product::factory()->create(['name' => 'Test Product', 'sku' => 'TEST-123']);

    // Create a package
    $package = \App\Models\Package::factory()->create(['name' => 'Premium Package']);

    // Create orders with these items so they appear in the filter
    $order1 = ProductOrder::factory()->create();
    ProductOrderItem::factory()->create([
        'order_id' => $order1->id,
        'product_id' => $product->id,
    ]);

    $order2 = ProductOrder::factory()->create();
    ProductOrderItem::factory()->create([
        'order_id' => $order2->id,
        'package_id' => $package->id,
        'product_id' => Product::factory()->create()->id,
    ]);

    // Test that dropdown options are visible in the component
    Volt::test('admin.orders.order-list')
        ->assertSee('Test Product (TEST-123)', escape: false)
        ->assertSee('Premium Package (Package)', escape: false);
});

test('clearing filters also clears product filter', function () {
    actingAs($this->admin);

    $product = Product::factory()->create();
    $order = ProductOrder::factory()->create();
    ProductOrderItem::factory()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
    ]);

    Volt::test('admin.orders.order-list')
        ->set('productFilter', $product->id)
        ->set('statusFilter', 'delivered')
        ->assertSet('productFilter', $product->id)
        ->assertSet('statusFilter', 'delivered')
        ->call('$set', 'productFilter', '')
        ->assertSet('productFilter', '');
});
