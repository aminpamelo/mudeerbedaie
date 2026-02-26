<?php

use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('admin can create a POS sale with product items', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $product = Product::factory()->create(['status' => 'active']);

    $response = $this->actingAs($admin)->postJson(route('api.pos.sales.store'), [
        'customer_name' => 'Test Customer',
        'customer_phone' => '0123456789',
        'payment_method' => 'cash',
        'payment_status' => 'paid',
        'items' => [
            [
                'itemable_type' => 'product',
                'itemable_id' => $product->id,
                'quantity' => 2,
                'unit_price' => 50.00,
            ],
        ],
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.total_amount', '100.00');
    $response->assertJsonPath('data.salesperson_id', $admin->id);

    $order = ProductOrder::where('source', 'pos')->first();
    expect($order)->not->toBeNull();
    expect($order->items)->toHaveCount(1);
    expect($order->metadata['salesperson_id'])->toBe($admin->id);
});

test('sales user can create a POS sale', function () {
    $sales = User::factory()->create(['role' => 'sales']);
    $product = Product::factory()->create(['status' => 'active']);

    $response = $this->actingAs($sales)->postJson(route('api.pos.sales.store'), [
        'customer_name' => 'Test Customer',
        'customer_phone' => '0123456789',
        'payment_method' => 'cash',
        'payment_status' => 'paid',
        'items' => [
            [
                'itemable_type' => 'product',
                'itemable_id' => $product->id,
                'quantity' => 1,
                'unit_price' => 30.00,
            ],
        ],
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.salesperson_id', $sales->id);
});

test('student cannot create a POS sale', function () {
    $student = User::factory()->create(['role' => 'student']);
    $product = Product::factory()->create(['status' => 'active']);

    $response = $this->actingAs($student)->postJson(route('api.pos.sales.store'), [
        'customer_name' => 'Test',
        'customer_phone' => '0123456789',
        'payment_method' => 'cash',
        'payment_status' => 'paid',
        'items' => [
            [
                'itemable_type' => 'product',
                'itemable_id' => $product->id,
                'quantity' => 1,
                'unit_price' => 30.00,
            ],
        ],
    ]);

    $response->assertForbidden();
});

test('sale requires at least one item', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->postJson(route('api.pos.sales.store'), [
        'customer_name' => 'Test',
        'customer_phone' => '0123456789',
        'payment_method' => 'cash',
        'payment_status' => 'paid',
        'items' => [],
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('items');
});

test('sale requires valid payment method', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $product = Product::factory()->create(['status' => 'active']);

    $response = $this->actingAs($admin)->postJson(route('api.pos.sales.store'), [
        'customer_name' => 'Test',
        'customer_phone' => '0123456789',
        'payment_method' => 'credit_card',
        'payment_status' => 'paid',
        'items' => [
            [
                'itemable_type' => 'product',
                'itemable_id' => $product->id,
                'quantity' => 1,
                'unit_price' => 30.00,
            ],
        ],
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('payment_method');
});

test('bank transfer requires payment reference', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $product = Product::factory()->create(['status' => 'active']);

    $response = $this->actingAs($admin)->postJson(route('api.pos.sales.store'), [
        'customer_name' => 'Test',
        'customer_phone' => '0123456789',
        'payment_method' => 'bank_transfer',
        'payment_status' => 'paid',
        'items' => [
            [
                'itemable_type' => 'product',
                'itemable_id' => $product->id,
                'quantity' => 1,
                'unit_price' => 30.00,
            ],
        ],
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('payment_reference');
});

test('bank transfer with reference succeeds', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $product = Product::factory()->create(['status' => 'active']);

    $response = $this->actingAs($admin)->postJson(route('api.pos.sales.store'), [
        'customer_name' => 'Test',
        'customer_phone' => '0123456789',
        'payment_method' => 'bank_transfer',
        'payment_reference' => 'REF-12345',
        'payment_status' => 'paid',
        'items' => [
            [
                'itemable_type' => 'product',
                'itemable_id' => $product->id,
                'quantity' => 1,
                'unit_price' => 30.00,
            ],
        ],
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.payment_method', 'bank_transfer');
    $response->assertJsonPath('data.payment_reference', 'REF-12345');
});

test('sale with walk-in customer stores customer info', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $product = Product::factory()->create(['status' => 'active']);

    $response = $this->actingAs($admin)->postJson(route('api.pos.sales.store'), [
        'customer_name' => 'Walk-in Customer',
        'customer_phone' => '0123456789',
        'customer_email' => 'walkin@test.com',
        'customer_address' => '123 Main St, KL',
        'payment_method' => 'cash',
        'payment_status' => 'paid',
        'items' => [
            [
                'itemable_type' => 'product',
                'itemable_id' => $product->id,
                'quantity' => 1,
                'unit_price' => 25.00,
            ],
        ],
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.customer_name', 'Walk-in Customer');
    $response->assertJsonPath('data.customer_phone', '0123456789');

    $order = ProductOrder::where('source', 'pos')->first();
    expect($order->customer_name)->toBe('Walk-in Customer');
    expect($order->guest_email)->toBe('walkin@test.com');
    expect($order->shipping_address['full_address'])->toBe('123 Main St, KL');
});

test('sale with existing customer links customer_id', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $customer = User::factory()->create(['role' => 'student']);
    $product = Product::factory()->create(['status' => 'active']);

    $response = $this->actingAs($admin)->postJson(route('api.pos.sales.store'), [
        'customer_id' => $customer->id,
        'payment_method' => 'cash',
        'payment_status' => 'paid',
        'items' => [
            [
                'itemable_type' => 'product',
                'itemable_id' => $product->id,
                'quantity' => 1,
                'unit_price' => 25.00,
            ],
        ],
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.customer_id', $customer->id);
});

test('walk-in sale requires customer name and phone', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $product = Product::factory()->create(['status' => 'active']);

    $response = $this->actingAs($admin)->postJson(route('api.pos.sales.store'), [
        'payment_method' => 'cash',
        'payment_status' => 'paid',
        'items' => [
            [
                'itemable_type' => 'product',
                'itemable_id' => $product->id,
                'quantity' => 1,
                'unit_price' => 25.00,
            ],
        ],
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['customer_name', 'customer_phone']);
});

test('sale with fixed discount calculates correctly', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $product = Product::factory()->create(['status' => 'active']);

    $response = $this->actingAs($admin)->postJson(route('api.pos.sales.store'), [
        'customer_name' => 'Test',
        'customer_phone' => '0123456789',
        'payment_method' => 'cash',
        'payment_status' => 'paid',
        'discount_amount' => 10,
        'discount_type' => 'fixed',
        'items' => [
            [
                'itemable_type' => 'product',
                'itemable_id' => $product->id,
                'quantity' => 2,
                'unit_price' => 50.00,
            ],
        ],
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.subtotal', '100.00');
    $response->assertJsonPath('data.discount_amount', '10.00');
    $response->assertJsonPath('data.total_amount', '90.00');
});

test('sale with percentage discount calculates correctly', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $product = Product::factory()->create(['status' => 'active']);

    $response = $this->actingAs($admin)->postJson(route('api.pos.sales.store'), [
        'customer_name' => 'Test',
        'customer_phone' => '0123456789',
        'payment_method' => 'cash',
        'payment_status' => 'paid',
        'discount_amount' => 10,
        'discount_type' => 'percentage',
        'items' => [
            [
                'itemable_type' => 'product',
                'itemable_id' => $product->id,
                'quantity' => 1,
                'unit_price' => 200.00,
            ],
        ],
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.subtotal', '200.00');
    $response->assertJsonPath('data.discount_amount', '20.00');
    $response->assertJsonPath('data.total_amount', '180.00');
});

test('sale generates unique order number', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $product = Product::factory()->create(['status' => 'active']);

    $saleData = [
        'customer_name' => 'Test',
        'customer_phone' => '0123456789',
        'payment_method' => 'cash',
        'payment_status' => 'paid',
        'items' => [
            [
                'itemable_type' => 'product',
                'itemable_id' => $product->id,
                'quantity' => 1,
                'unit_price' => 10.00,
            ],
        ],
    ];

    $response1 = $this->actingAs($admin)->postJson(route('api.pos.sales.store'), $saleData);
    $response2 = $this->actingAs($admin)->postJson(route('api.pos.sales.store'), $saleData);

    $response1->assertCreated();
    $response2->assertCreated();

    $order1 = $response1->json('data.order_number');
    $order2 = $response2->json('data.order_number');

    expect($order1)->not->toBe($order2);
    expect($order1)->toStartWith('PO-');
});

test('sale with multiple items calculates subtotal correctly', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $product1 = Product::factory()->create(['status' => 'active']);
    $product2 = Product::factory()->create(['status' => 'active']);

    $response = $this->actingAs($admin)->postJson(route('api.pos.sales.store'), [
        'customer_name' => 'Test',
        'customer_phone' => '0123456789',
        'payment_method' => 'cash',
        'payment_status' => 'paid',
        'items' => [
            [
                'itemable_type' => 'product',
                'itemable_id' => $product1->id,
                'quantity' => 2,
                'unit_price' => 50.00,
            ],
            [
                'itemable_type' => 'product',
                'itemable_id' => $product2->id,
                'quantity' => 1,
                'unit_price' => 30.00,
            ],
        ],
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.subtotal', '130.00');
    $response->assertJsonPath('data.total_amount', '130.00');
});

test('sale with pending status is created correctly', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $product = Product::factory()->create(['status' => 'active']);

    $response = $this->actingAs($admin)->postJson(route('api.pos.sales.store'), [
        'customer_name' => 'Test',
        'customer_phone' => '0123456789',
        'payment_method' => 'cash',
        'payment_status' => 'pending',
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
    $response->assertJsonPath('data.payment_status', 'pending');

    $order = ProductOrder::where('source', 'pos')->first();
    expect($order->paid_time)->toBeNull();
});

test('pos sale is stored as product order with pos source', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $product = Product::factory()->create(['status' => 'active']);

    $response = $this->actingAs($admin)->postJson(route('api.pos.sales.store'), [
        'customer_name' => 'Test',
        'customer_phone' => '0123456789',
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

    $order = ProductOrder::where('source', 'pos')->first();
    expect($order)->not->toBeNull();
    expect($order->source)->toBe('pos');
    expect($order->source_reference)->toStartWith('salesperson:');
    expect($order->metadata['pos_sale'])->toBeTrue();
    expect($order->metadata['salesperson_id'])->toBe($admin->id);
    expect($order->metadata['salesperson_name'])->toBe($admin->name);
    expect($order->paid_time)->not->toBeNull();
});
