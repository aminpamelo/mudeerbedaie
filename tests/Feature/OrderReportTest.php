<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\ProductOrderItem;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
});

test('report page loads for authenticated admin', function () {
    $this->actingAs($this->admin)
        ->get('/admin/product-orders/report')
        ->assertSuccessful();
});

test('report page shows summary cards', function () {
    ProductOrder::factory()->create([
        'order_date' => now(),
        'status' => 'delivered',
        'total_amount' => 500,
    ]);

    $this->actingAs($this->admin)
        ->get('/admin/product-orders/report')
        ->assertSuccessful()
        ->assertSee('Total Revenue')
        ->assertSee('Total Orders')
        ->assertSee('Avg Order Value')
        ->assertSee('Completion Rate');
});

test('report page shows product insights tab', function () {
    $product = Product::factory()->create(['name' => 'Test Widget XYZ']);

    $order = ProductOrder::factory()->create([
        'order_date' => now(),
        'status' => 'pending',
        'total_amount' => 300,
    ]);

    ProductOrderItem::factory()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'quantity_ordered' => 5,
        'unit_price' => 60,
        'total_price' => 300,
    ]);

    $this->actingAs($this->admin)
        ->get('/admin/product-orders/report?tab=products')
        ->assertSuccessful()
        ->assertSee('Product Sales Detail')
        ->assertSee('Test Widget XYZ');
});

test('report page shows order status tab', function () {
    ProductOrder::factory()->create([
        'order_date' => now(),
        'status' => 'delivered',
        'total_amount' => 100,
    ]);

    ProductOrder::factory()->create([
        'order_date' => now(),
        'status' => 'pending',
        'total_amount' => 200,
    ]);

    $this->actingAs($this->admin)
        ->get('/admin/product-orders/report?tab=status')
        ->assertSuccessful()
        ->assertSee('Order Status Distribution')
        ->assertSee('Delivered')
        ->assertSee('Pending');
});

test('report page shows customer insights tab', function () {
    $customer = User::factory()->create(['name' => 'VIP Customer Test']);

    ProductOrder::factory()->create([
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'order_date' => now(),
        'status' => 'delivered',
        'total_amount' => 1000,
    ]);

    $this->actingAs($this->admin)
        ->get('/admin/product-orders/report?tab=customers')
        ->assertSuccessful()
        ->assertSee('Top Customers')
        ->assertSee('VIP Customer Test');
});

test('cancelled orders are excluded from revenue calculations', function () {
    ProductOrder::factory()->create([
        'order_date' => now(),
        'status' => 'cancelled',
        'total_amount' => 5000,
    ]);

    ProductOrder::factory()->create([
        'order_date' => now(),
        'status' => 'delivered',
        'total_amount' => 100,
    ]);

    $response = $this->actingAs($this->admin)
        ->get('/admin/product-orders/report')
        ->assertSuccessful();

    // Revenue should not include the 5000 cancelled order
    $response->assertDontSee('5,000.00');
    $response->assertSee('100.00');
});
