<?php

declare(strict_types=1);

use App\Models\Package;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\TikTokOrderProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create test data
    $this->platform = Platform::factory()->create([
        'slug' => 'tiktok-shop',
        'name' => 'TikTok Shop',
    ]);

    $this->account = PlatformAccount::factory()->create([
        'platform_id' => $this->platform->id,
        'name' => 'Test TikTok Account',
        'is_active' => true,
    ]);

    $this->warehouse = Warehouse::create([
        'name' => 'Test Warehouse',
        'code' => 'WH-TEST',
        'location' => 'Test Location',
        'is_default' => true,
        'is_active' => true,
    ]);

    $this->product = Product::factory()->create([
        'name' => 'Test Product',
        'sku' => 'PROD-001',
        'status' => 'active',
        'track_quantity' => true,
    ]);

    // Create initial stock
    $this->stockLevel = StockLevel::create([
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 100,
        'available_quantity' => 100,
        'reserved_quantity' => 0,
    ]);
});

test('initial import creates order and deducts stock', function () {
    $processor = new TikTokOrderProcessor(
        $this->platform,
        $this->account,
        [
            'order_id' => 0,
            'product_name' => 1,
            'quantity' => 2,
            'created_time' => 3,
            'shipped_time' => 4,
        ],
        [
            'Test Product' => [
                'product_id' => $this->product->id,
                'product_name' => $this->product->name,
            ],
        ],
        []
    );

    $csvRow = [
        'ORDER-123',           // order_id
        'Test Product',        // product_name
        '5',                   // quantity
        '01/01/2025 10:00:00', // created_time
        '02/01/2025 10:00:00', // shipped_time
    ];

    $mappedData = [
        'order_id' => 'ORDER-123',
        'product_name' => 'Test Product',
        'quantity' => 5,
        'created_time' => \Carbon\Carbon::createFromFormat('d/m/Y H:i:s', '01/01/2025 10:00:00'),
        'shipped_time' => \Carbon\Carbon::createFromFormat('d/m/Y H:i:s', '02/01/2025 10:00:00'),
        'internal_product' => $this->product,
    ];

    $result = $processor->processOrderRow($mappedData);

    // Assert order created
    expect($result)->toHaveKey('product_order');
    expect($result['product_order']->platform_order_id)->toBe('ORDER-123');
    expect($result['product_order']->status)->toBe('shipped');

    // Assert order item created
    expect($result['product_order']->items)->toHaveCount(1);
    $item = $result['product_order']->items->first();
    expect($item->quantity_ordered)->toBe(5);
    expect($item->product_id)->toBe($this->product->id);

    // Assert stock deducted
    $this->stockLevel->refresh();
    expect($this->stockLevel->quantity)->toBe(95); // 100 - 5

    // Assert stock movement created
    $movement = StockMovement::where('product_id', $this->product->id)
        ->where('reference_type', 'App\\Models\\ProductOrderItem')
        ->where('reference_id', $item->id)
        ->first();

    expect($movement)->not->toBeNull();
    expect($movement->quantity)->toBe(-5);
    expect($movement->quantity_before)->toBe(100);
    expect($movement->quantity_after)->toBe(95);
});

test('re-importing same order updates item and does NOT deduct stock again', function () {
    // First import
    $processor = new TikTokOrderProcessor(
        $this->platform,
        $this->account,
        [
            'order_id' => 0,
            'product_name' => 1,
            'quantity' => 2,
            'created_time' => 3,
            'shipped_time' => 4,
        ],
        [
            'Test Product' => [
                'product_id' => $this->product->id,
                'product_name' => $this->product->name,
            ],
        ],
        []
    );

    $mappedData = [
        'order_id' => 'ORDER-123',
        'product_name' => 'Test Product',
        'quantity' => 5,
        'created_time' => \Carbon\Carbon::createFromFormat('d/m/Y H:i:s', '01/01/2025 10:00:00'),
        'shipped_time' => \Carbon\Carbon::createFromFormat('d/m/Y H:i:s', '02/01/2025 10:00:00'),
        'internal_product' => $this->product,
    ];

    $result1 = $processor->processOrderRow($mappedData);
    $firstItemId = $result1['product_order']->items->first()->id;

    // Assert first import stock deducted
    $this->stockLevel->refresh();
    expect($this->stockLevel->quantity)->toBe(95);

    // Second import (re-import with updated quantity)
    $mappedData['quantity'] = 7; // Changed quantity
    $result2 = $processor->processOrderRow($mappedData);

    $secondItemId = $result2['product_order']->items->first()->id;

    // Assert SAME item ID (updated, not recreated)
    expect($secondItemId)->toBe($firstItemId);

    // Assert item quantity updated
    expect($result2['product_order']->items->first()->quantity_ordered)->toBe(7);

    // Assert stock NOT deducted again (should still be 95)
    $this->stockLevel->refresh();
    expect($this->stockLevel->quantity)->toBe(95); // Still 95, NOT 93 (100-5-7)

    // Assert only ONE stock movement exists
    $movements = StockMovement::where('product_id', $this->product->id)
        ->where('reference_type', 'App\\Models\\ProductOrderItem')
        ->where('reference_id', $firstItemId)
        ->get();

    expect($movements)->toHaveCount(1);
});

test('re-importing with status change from pending to shipped deducts stock', function () {
    // First import (pending status, no stock deduction)
    $processor = new TikTokOrderProcessor(
        $this->platform,
        $this->account,
        [
            'order_id' => 0,
            'product_name' => 1,
            'quantity' => 2,
            'created_time' => 3,
        ],
        [
            'Test Product' => [
                'product_id' => $this->product->id,
                'product_name' => $this->product->name,
            ],
        ],
        []
    );

    $mappedData = [
        'order_id' => 'ORDER-123',
        'product_name' => 'Test Product',
        'quantity' => 5,
        'created_time' => \Carbon\Carbon::createFromFormat('d/m/Y H:i:s', '01/01/2025 10:00:00'),
        'internal_product' => $this->product,
    ];

    $result1 = $processor->processOrderRow($mappedData);

    // Assert no stock deduction (pending status)
    $this->stockLevel->refresh();
    expect($this->stockLevel->quantity)->toBe(100);

    // Second import (now shipped)
    $mappedData['shipped_time'] = \Carbon\Carbon::createFromFormat('d/m/Y H:i:s', '02/01/2025 10:00:00');
    $result2 = $processor->processOrderRow($mappedData);

    // Assert stock deducted NOW
    $this->stockLevel->refresh();
    expect($this->stockLevel->quantity)->toBe(95);

    // Assert order status updated
    expect($result2['product_order']->status)->toBe('shipped');
});

test('package item re-import updates item and does NOT deduct stock again', function () {
    // Create a user for package creation
    $user = \App\Models\User::factory()->create();
    $this->actingAs($user);

    // Create package with product
    $package = Package::create([
        'name' => 'Test Package',
        'slug' => 'test-package',
        'description' => 'Test package description',
        'price' => 100,
        'status' => 'active',
        'created_by' => $user->id,
    ]);

    $package->products()->attach($this->product->id, [
        'quantity' => 2, // 2 products per package
        'original_price' => $this->product->base_price ?? 50,
    ]);

    // First import with package
    $processor = new TikTokOrderProcessor(
        $this->platform,
        $this->account,
        [
            'order_id' => 0,
            'product_name' => 1,
            'quantity' => 2,
            'created_time' => 3,
            'shipped_time' => 4,
        ],
        [],
        [
            'Test Package' => [
                'package_id' => $package->id,
                'package_name' => $package->name,
                'type' => 'package',
            ],
        ]
    );

    $mappedData = [
        'order_id' => 'ORDER-PKG-123',
        'product_name' => 'Test Package',
        'quantity' => 3, // 3 packages
        'created_time' => \Carbon\Carbon::createFromFormat('d/m/Y H:i:s', '01/01/2025 10:00:00'),
        'shipped_time' => \Carbon\Carbon::createFromFormat('d/m/Y H:i:s', '02/01/2025 10:00:00'),
        'internal_package_id' => $package->id,
    ];

    $result1 = $processor->processOrderRow($mappedData);

    // Assert stock deducted: 3 packages × 2 products = 6 units
    $this->stockLevel->refresh();
    expect($this->stockLevel->quantity)->toBe(94); // 100 - 6

    // Re-import same package order
    $result2 = $processor->processOrderRow($mappedData);

    // Assert stock NOT deducted again
    $this->stockLevel->refresh();
    expect($this->stockLevel->quantity)->toBe(94); // Still 94, NOT 88

    // Assert only ONE stock movement per product
    $movements = StockMovement::where('product_id', $this->product->id)
        ->where('type', 'out')
        ->get();

    expect($movements)->toHaveCount(1);
});

test('item ID is preserved across re-imports maintaining stock movement references', function () {
    $processor = new TikTokOrderProcessor(
        $this->platform,
        $this->account,
        [
            'order_id' => 0,
            'product_name' => 1,
            'quantity' => 2,
            'created_time' => 3,
            'shipped_time' => 4,
        ],
        [
            'Test Product' => [
                'product_id' => $this->product->id,
                'product_name' => $this->product->name,
            ],
        ],
        []
    );

    $mappedData = [
        'order_id' => 'ORDER-123',
        'product_name' => 'Test Product',
        'quantity' => 5,
        'created_time' => \Carbon\Carbon::createFromFormat('d/m/Y H:i:s', '01/01/2025 10:00:00'),
        'shipped_time' => \Carbon\Carbon::createFromFormat('d/m/Y H:i:s', '02/01/2025 10:00:00'),
        'internal_product' => $this->product,
    ];

    // First import
    $result1 = $processor->processOrderRow($mappedData);
    $originalItem = $result1['product_order']->items->first();
    $originalItemId = $originalItem->id;

    // Get stock movement reference
    $movement = StockMovement::where('reference_type', 'App\\Models\\ProductOrderItem')
        ->where('reference_id', $originalItemId)
        ->first();

    expect($movement)->not->toBeNull();

    // Re-import
    $result2 = $processor->processOrderRow($mappedData);
    $updatedItem = $result2['product_order']->items->first();

    // Assert SAME item ID preserved
    expect($updatedItem->id)->toBe($originalItemId);

    // Assert stock movement STILL references correct item
    $movement->refresh();
    expect($movement->reference_id)->toBe($updatedItem->id);
    expect($movement->reference_type)->toBe('App\\Models\\ProductOrderItem');

    // Verify the movement is accessible from the item
    $itemMovements = StockMovement::where('reference_type', 'App\\Models\\ProductOrderItem')
        ->where('reference_id', $updatedItem->id)
        ->get();

    expect($itemMovements)->toHaveCount(1);
});
