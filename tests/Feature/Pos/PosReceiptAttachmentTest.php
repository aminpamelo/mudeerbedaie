<?php

use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});

test('pos sale can be created with receipt attachment image', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $product = Product::factory()->create(['status' => 'active']);

    $file = UploadedFile::fake()->image('receipt.jpg', 800, 600)->size(500);

    $response = $this->actingAs($admin)->post(route('api.pos.sales.store'), [
        'customer_name' => 'Test Customer',
        'customer_phone' => '0123456789',
        'payment_method' => 'bank_transfer',
        'payment_reference' => 'REF-123',
        'payment_status' => 'paid',
        'receipt_attachment' => $file,
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
    expect($order->receipt_attachment)->not->toBeNull();
    expect($order->receipt_attachment)->toStartWith('pos/receipts/');

    Storage::disk('public')->assertExists($order->receipt_attachment);
});

test('pos sale can be created with receipt attachment pdf', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $product = Product::factory()->create(['status' => 'active']);

    $file = UploadedFile::fake()->create('receipt.pdf', 200, 'application/pdf');

    $response = $this->actingAs($admin)->post(route('api.pos.sales.store'), [
        'customer_name' => 'Test Customer',
        'customer_phone' => '0123456789',
        'payment_method' => 'cash',
        'payment_status' => 'paid',
        'receipt_attachment' => $file,
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

    $order = ProductOrder::where('source', 'pos')->first();
    expect($order->receipt_attachment)->not->toBeNull();

    Storage::disk('public')->assertExists($order->receipt_attachment);
});

test('pos sale can be created without receipt attachment', function () {
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
                'quantity' => 1,
                'unit_price' => 50.00,
            ],
        ],
    ]);

    $response->assertCreated();

    $order = ProductOrder::where('source', 'pos')->first();
    expect($order->receipt_attachment)->toBeNull();
    expect($order->receipt_attachment_url)->toBeNull();
});

test('receipt attachment rejects invalid file types', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $product = Product::factory()->create(['status' => 'active']);

    $file = UploadedFile::fake()->create('receipt.txt', 100, 'text/plain');

    $response = $this->actingAs($admin)->post(route('api.pos.sales.store'), [
        'customer_name' => 'Test Customer',
        'customer_phone' => '0123456789',
        'payment_method' => 'cash',
        'payment_status' => 'paid',
        'receipt_attachment' => $file,
        'items' => [
            [
                'itemable_type' => 'product',
                'itemable_id' => $product->id,
                'quantity' => 1,
                'unit_price' => 50.00,
            ],
        ],
    ], ['Accept' => 'application/json']);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('receipt_attachment');
});

test('receipt attachment rejects files over 5MB', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $product = Product::factory()->create(['status' => 'active']);

    $file = UploadedFile::fake()->image('receipt.jpg')->size(6000);

    $response = $this->actingAs($admin)->post(route('api.pos.sales.store'), [
        'customer_name' => 'Test Customer',
        'customer_phone' => '0123456789',
        'payment_method' => 'cash',
        'payment_status' => 'paid',
        'receipt_attachment' => $file,
        'items' => [
            [
                'itemable_type' => 'product',
                'itemable_id' => $product->id,
                'quantity' => 1,
                'unit_price' => 50.00,
            ],
        ],
    ], ['Accept' => 'application/json']);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('receipt_attachment');
});

test('receipt attachment url is returned in sale response', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $product = Product::factory()->create(['status' => 'active']);

    $file = UploadedFile::fake()->image('receipt.png', 400, 300);

    $response = $this->actingAs($admin)->post(route('api.pos.sales.store'), [
        'customer_name' => 'Test Customer',
        'customer_phone' => '0123456789',
        'payment_method' => 'cash',
        'payment_status' => 'paid',
        'receipt_attachment' => $file,
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
    $data = $response->json('data');
    expect($data['receipt_attachment_url'])->not->toBeNull();
    expect($data['receipt_attachment_url'])->toContain('pos/receipts/');
});

test('receipt attachment url is included in sales history', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $product = Product::factory()->create(['status' => 'active']);

    $file = UploadedFile::fake()->image('receipt.jpg', 400, 300);

    $this->actingAs($admin)->post(route('api.pos.sales.store'), [
        'customer_name' => 'Test Customer',
        'customer_phone' => '0123456789',
        'payment_method' => 'cash',
        'payment_status' => 'paid',
        'receipt_attachment' => $file,
        'items' => [
            [
                'itemable_type' => 'product',
                'itemable_id' => $product->id,
                'quantity' => 1,
                'unit_price' => 50.00,
            ],
        ],
    ]);

    $response = $this->actingAs($admin)->getJson(route('api.pos.sales.index'));
    $response->assertSuccessful();

    $firstSale = $response->json('data.0');
    expect($firstSale['receipt_attachment_url'])->not->toBeNull();
});
