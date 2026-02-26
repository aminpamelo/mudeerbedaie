<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\User;

test('pos sale saves customer phone and email from user record when customer_id is provided', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $customer = User::factory()->create([
        'name' => 'Test Customer',
        'phone' => '0123456789',
        'email' => 'customer@example.com',
        'role' => 'student',
    ]);

    $product = Product::factory()->create(['status' => 'active']);

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/pos/sales', [
            'customer_id' => $customer->id,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'items' => [
                [
                    'itemable_type' => 'product',
                    'itemable_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 50.00,
                ],
            ],
        ]);

    $response->assertCreated();

    $order = ProductOrder::where('source', 'pos')->latest('id')->first();

    expect($order->customer_id)->toBe($customer->id)
        ->and($order->customer_name)->toBe('Test Customer')
        ->and($order->customer_phone)->toBe('0123456789')
        ->and($order->guest_email)->toBe('customer@example.com');
});

test('pos sale saves explicit customer info over user record values', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $customer = User::factory()->create([
        'name' => 'Original Name',
        'phone' => '0111111111',
        'email' => 'original@example.com',
        'role' => 'student',
    ]);

    $product = Product::factory()->create(['status' => 'active']);

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/pos/sales', [
            'customer_id' => $customer->id,
            'customer_name' => 'Edited Name',
            'customer_phone' => '0199999999',
            'customer_email' => 'edited@example.com',
            'customer_address' => 'Lot 123, Jalan Test, 12345 Selangor',
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'items' => [
                [
                    'itemable_type' => 'product',
                    'itemable_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 50.00,
                ],
            ],
        ]);

    $response->assertCreated();

    $order = ProductOrder::where('source', 'pos')->latest('id')->first();

    expect($order->customer_name)->toBe('Edited Name')
        ->and($order->customer_phone)->toBe('0199999999')
        ->and($order->guest_email)->toBe('edited@example.com')
        ->and($order->shipping_address)->toBe(['full_address' => 'Lot 123, Jalan Test, 12345 Selangor']);
});
