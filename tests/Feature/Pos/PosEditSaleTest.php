<?php

use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\SalesSource;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('non-pos orders cannot be edited via POS endpoint', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $order = ProductOrder::factory()->create(['source' => 'website']);
    $source = SalesSource::factory()->create(['is_active' => true]);
    $product = Product::factory()->create(['status' => 'active']);

    $response = $this->actingAs($admin)->putJson(route('api.pos.sales.update', $order), [
        'sales_source_id' => $source->id,
        'customer_name' => 'X',
        'customer_phone' => '0',
        'payment_method' => 'cash',
        'items' => [[
            'itemable_type' => 'product',
            'itemable_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 10,
        ]],
    ]);

    $response->assertForbidden();
});

test('admin can edit customer info and recompute totals', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $source = SalesSource::factory()->create(['is_active' => true]);
    $product = Product::factory()->create(['status' => 'active']);

    // Seed an existing POS sale via the create endpoint.
    $created = $this->actingAs($admin)->postJson(route('api.pos.sales.store'), [
        'sales_source_id' => $source->id,
        'customer_name' => 'Original',
        'customer_phone' => '0123456789',
        'payment_method' => 'cash',
        'payment_status' => 'pending',
        'items' => [[
            'itemable_type' => 'product',
            'itemable_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 50,
        ]],
    ])->assertCreated()->json('data');

    $itemId = $created['items'][0]['id'];

    $response = $this->actingAs($admin)->putJson(route('api.pos.sales.update', $created['id']), [
        'sales_source_id' => $source->id,
        'customer_name' => 'Updated Name',
        'customer_phone' => '0199999999',
        'customer_email' => 'foo@bar.com',
        'customer_address' => '1 Jalan ABC',
        'payment_method' => 'cash',
        'discount_amount' => 5,
        'discount_type' => 'fixed',
        'shipping_cost' => 10,
        'items' => [[
            'id' => $itemId,
            'itemable_type' => 'product',
            'itemable_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 50,
        ]],
    ]);

    $response->assertOk();

    $order = ProductOrder::find($created['id'])->fresh(['items', 'payments']);
    expect($order->customer_name)->toBe('Updated Name');
    expect($order->customer_phone)->toBe('0199999999');
    expect($order->guest_email)->toBe('foo@bar.com');
    expect((float) $order->subtotal)->toBe(100.0);
    expect((float) $order->discount_amount)->toBe(5.0);
    expect((float) $order->shipping_cost)->toBe(10.0);
    expect((float) $order->total_amount)->toBe(105.0);
    expect((float) $order->payments->first()->amount)->toBe(105.0);
});
