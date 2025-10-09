<?php

use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\Product;
use App\Models\ProductOrder;
use App\Services\TikTokOrderProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Set up test platform and account using factories
    $this->platform = Platform::factory()->create([
        'name' => 'TikTok Shop',
        'slug' => 'tiktok-shop',
        'is_active' => true,
    ]);

    $this->account = PlatformAccount::factory()->create([
        'platform_id' => $this->platform->id,
        'name' => 'Test TikTok Account',
        'is_active' => true,
    ]);

    $this->product = Product::factory()->create([
        'name' => 'Test Product',
        'slug' => 'test-product',
        'sku' => 'TEST-001',
    ]);
});

test('creates new order when importing for the first time', function () {
    $processor = new TikTokOrderProcessor(
        $this->platform,
        $this->account,
        ['order_id' => 0, 'product_name' => 1, 'quantity' => 2, 'created_time' => 3, 'phone' => 4],
        ['Test Product' => ['product_id' => $this->product->id, 'product_name' => 'Test Product', 'confidence' => 100]]
    );

    $orderData = [
        'order_id' => 'TT-12345',
        'product_name' => 'Test Product',
        'quantity' => 2,
        'created_time' => now(),
        'phone' => '(+60)1234567890',
        'recipient' => 'John Doe',
        'order_amount' => 200,
    ];

    $result = $processor->processOrderRow($orderData);

    expect($result['product_order'])->not->toBeNull();
    expect($result['product_order']->platform_order_id)->toBe('TT-12345');
    expect($result['product_order']->customer_phone)->toBe('601234567890'); // Normalized format
    expect($result['product_order']->customer_name)->toBe('John Doe');
});

test('updates existing order when importing duplicate order ID', function () {
    // Create initial order with masked data
    $existingOrder = ProductOrder::create([
        'order_number' => 'TT-12345',
        'platform_id' => $this->platform->id,
        'platform_account_id' => $this->account->id,
        'platform_order_id' => 'TT-12345',
        'order_type' => 'retail',
        'source' => 'platform_import',
        'status' => 'pending',
        'currency' => 'MYR',
        'customer_name' => 'John***',
        'customer_phone' => '(+60)148****88',
        'total_amount' => 200,
        'order_date' => now(),
        'shipping_address' => [
            'detail_address' => '123 Main****',
        ],
    ]);

    $processor = new TikTokOrderProcessor(
        $this->platform,
        $this->account,
        ['order_id' => 0, 'product_name' => 1, 'quantity' => 2, 'created_time' => 3, 'phone' => 4, 'recipient' => 5, 'detail_address' => 6],
        ['Test Product' => ['product_id' => $this->product->id, 'product_name' => 'Test Product', 'confidence' => 100]]
    );

    $orderData = [
        'order_id' => 'TT-12345',
        'product_name' => 'Test Product',
        'quantity' => 2,
        'created_time' => now(),
        'phone' => '(+60)1482345588',  // Unmasked phone number
        'recipient' => 'John Smith',    // Unmasked name
        'detail_address' => '123 Main Street',  // Unmasked address
        'order_amount' => 200,
    ];

    $result = $processor->processOrderRow($orderData);

    $existingOrder->refresh();

    expect($existingOrder->customer_phone)->toBe('601482345588'); // Normalized format
    expect($existingOrder->customer_name)->toBe('John Smith');
    expect($existingOrder->shipping_address['detail_address'])->toBe('123 Main Street');
    expect(ProductOrder::count())->toBe(1); // Should update, not create new
});

test('preserves unmasked data when importing masked data', function () {
    // Create initial order with clean (manually corrected) data
    $existingOrder = ProductOrder::create([
        'order_number' => 'TT-67890',
        'platform_id' => $this->platform->id,
        'platform_account_id' => $this->account->id,
        'platform_order_id' => 'TT-67890',
        'order_type' => 'retail',
        'source' => 'platform_import',
        'status' => 'pending',
        'currency' => 'MYR',
        'customer_name' => 'Jane Doe',  // Clean data (manually corrected)
        'customer_phone' => '(+60)1987654321',  // Clean data (manually corrected)
        'total_amount' => 150,
        'order_date' => now(),
        'shipping_address' => [
            'detail_address' => '456 Oak Avenue',  // Clean data
        ],
    ]);

    $processor = new TikTokOrderProcessor(
        $this->platform,
        $this->account,
        ['order_id' => 0, 'product_name' => 1, 'quantity' => 2, 'created_time' => 3, 'phone' => 4, 'recipient' => 5, 'detail_address' => 6],
        ['Test Product' => ['product_id' => $this->product->id, 'product_name' => 'Test Product', 'confidence' => 100]]
    );

    $orderData = [
        'order_id' => 'TT-67890',
        'product_name' => 'Test Product',
        'quantity' => 1,
        'created_time' => now(),
        'phone' => '(+60)198****21',  // Masked phone (from new TikTok export)
        'recipient' => 'Jane***',      // Masked name (from new TikTok export)
        'detail_address' => '456 Oak****',  // Masked address
        'order_amount' => 150,
    ];

    $result = $processor->processOrderRow($orderData);

    $existingOrder->refresh();

    // Should preserve the clean data, not overwrite with masked data
    expect($existingOrder->customer_phone)->toBe('(+60)1987654321');
    expect($existingOrder->customer_name)->toBe('Jane Doe');
    expect($existingOrder->shipping_address['detail_address'])->toBe('456 Oak Avenue');
});

test('detects masked values correctly', function () {
    $processor = new TikTokOrderProcessor(
        $this->platform,
        $this->account,
        [],
        []
    );

    // Use reflection to test the protected method
    $reflection = new ReflectionClass($processor);
    $method = $reflection->getMethod('isMaskedValue');
    $method->setAccessible(true);

    // Masked values should return true
    expect($method->invoke($processor, '(+60)148****88'))->toBeTrue();
    expect($method->invoke($processor, 'John***'))->toBeTrue();
    expect($method->invoke($processor, '***n Doe'))->toBeTrue();
    expect($method->invoke($processor, '123 Main****'))->toBeTrue();

    // Clean values should return false
    expect($method->invoke($processor, '(+60)1234567890'))->toBeFalse();
    expect($method->invoke($processor, 'John Smith'))->toBeFalse();
    expect($method->invoke($processor, '123 Main Street'))->toBeFalse();
    expect($method->invoke($processor, null))->toBeFalse();
    expect($method->invoke($processor, 123))->toBeFalse();
});
