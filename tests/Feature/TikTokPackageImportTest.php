<?php

declare(strict_types=1);

use App\Jobs\ProcessTikTokOrderImport;
use App\Models\ImportJob;
use App\Models\Package;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\TikTokOrderProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create authenticated user
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    // Create platform and account
    $this->platform = Platform::factory()->create([
        'slug' => 'tiktok-shop',
        'name' => 'TikTok Shop',
    ]);

    $this->account = PlatformAccount::factory()->create([
        'platform_id' => $this->platform->id,
        'name' => 'Test TikTok Account',
        'is_active' => true,
    ]);

    // Create warehouse
    $this->warehouse = Warehouse::create([
        'name' => 'Main Warehouse',
        'code' => 'WH-MAIN',
        'location' => 'Main Location',
        'is_default' => true,
        'is_active' => true,
    ]);

    // Create products
    $this->product1 = Product::factory()->create([
        'name' => 'Buku Panduan Smart Tajwid',
        'sku' => 'BOOK-TAJWID-001',
        'status' => 'active',
        'track_quantity' => true,
    ]);

    $this->product2 = Product::factory()->create([
        'name' => 'Buku Safinatun Najah',
        'sku' => 'BOOK-NAJAH-001',
        'status' => 'active',
        'track_quantity' => true,
    ]);

    $this->product3 = Product::factory()->create([
        'name' => 'Buku Aqidatul Awam',
        'sku' => 'BOOK-AQIDAH-001',
        'status' => 'active',
        'track_quantity' => true,
    ]);

    // Create package with 3 products
    $this->package = Package::create([
        'name' => 'Paket Kitab Bundle 3in1',
        'slug' => 'paket-kitab-bundle-3in1',
        'description' => 'Bundle of 3 religious books',
        'status' => 'active',
        'price' => 150.00,
        'track_stock' => true,
        'created_by' => $this->user->id,
    ]);

    // Attach products to package
    $this->package->products()->attach($this->product1->id, [
        'quantity' => 1,
        'product_variant_id' => null,
        'original_price' => 50.00,
    ]);

    $this->package->products()->attach($this->product2->id, [
        'quantity' => 1,
        'product_variant_id' => null,
        'original_price' => 60.00,
    ]);

    $this->package->products()->attach($this->product3->id, [
        'quantity' => 1,
        'product_variant_id' => null,
        'original_price' => 40.00,
    ]);

    // Create initial stock for all products
    StockLevel::create([
        'product_id' => $this->product1->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 1000,
        'available_quantity' => 1000,
        'reserved_quantity' => 0,
    ]);

    StockLevel::create([
        'product_id' => $this->product2->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 800,
        'available_quantity' => 800,
        'reserved_quantity' => 0,
    ]);

    StockLevel::create([
        'product_id' => $this->product3->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 600,
        'available_quantity' => 600,
        'reserved_quantity' => 0,
    ]);

    // Setup fake storage
    Storage::fake('local');
});

test('package mapping creates order item with package_id', function () {
    $processor = new TikTokOrderProcessor(
        $this->platform,
        $this->account,
        ['order_id' => 0, 'product_name' => 1, 'quantity' => 2, 'created_time' => 3],
        [], // No product mappings
        [
            'Paket Kitab Bundle 3in1' => [
                'type' => 'package',
                'package_id' => $this->package->id,
                'package_name' => $this->package->name,
                'items_count' => 3,
                'confidence' => 100,
            ],
        ] // Package mapping
    );

    $orderData = [
        'order_id' => 'TT-PKG-001',
        'product_name' => 'Paket Kitab Bundle 3in1',
        'quantity' => 2,
        'created_time' => now(),
        'order_amount' => 300,
    ];

    $result = $processor->processOrderRow($orderData);

    // Assert order created
    expect($result['product_order'])->not->toBeNull();
    expect($result['product_order']->platform_order_id)->toBe('TT-PKG-001');

    // Assert order item has package_id set
    expect($result['product_order_items'])->toHaveCount(1);
    $orderItem = $result['product_order_items'][0];
    expect($orderItem->package_id)->toBe($this->package->id);
    expect($orderItem->product_name)->toBe('Paket Kitab Bundle 3in1');
    expect($orderItem->quantity_ordered)->toBe(2);
});

test('importing shipped package order deducts stock for all products in package', function () {
    // Create CSV file with shipped package order
    $csvContent = "Order ID,Product Name,Quantity,Created Time,Shipped Time\n";
    $csvContent .= "TT-PKG-SHIPPED,Paket Kitab Bundle 3in1,3,01/10/2025 10:00:00,02/10/2025 15:00:00\n";

    $csvPath = 'imports/test-package-import.csv';
    Storage::put($csvPath, $csvContent);

    // Create import job
    $importJob = ImportJob::create([
        'type' => 'tiktok_orders',
        'file_path' => $csvPath,
        'file_name' => 'test-package-import.csv',
        'total_rows' => 1,
        'processed_rows' => 0,
        'successful_rows' => 0,
        'failed_rows' => 0,
        'status' => 'pending',
        'platform_id' => $this->platform->id,
        'platform_account_id' => $this->account->id,
        'user_id' => $this->user->id,
        'created_by' => $this->user->id,
    ]);

    // Field mapping
    $fieldMapping = [
        'order_id' => 0,
        'product_name' => 1,
        'quantity' => 2,
        'created_time' => 3,
        'shipped_time' => 4,
    ];

    // Package mapping (not product mapping!)
    $packageMappings = [
        'Paket Kitab Bundle 3in1' => [
            'type' => 'package',
            'package_id' => $this->package->id,
            'package_name' => $this->package->name,
            'items_count' => 3,
            'confidence' => 100,
        ],
    ];

    // Execute import job with package mappings
    $job = new ProcessTikTokOrderImport(
        $importJob->id,
        $this->platform->id,
        $this->account->id,
        $fieldMapping,
        [], // No product mappings
        $packageMappings, // Package mappings
        50 // Batch size
    );

    $job->handle();

    // Assert order created and status is shipped
    $order = ProductOrder::where('platform_order_id', 'TT-PKG-SHIPPED')->first();
    expect($order)->not->toBeNull();
    expect($order->status)->toBe('shipped');

    // Assert order item has package_id
    expect($order->items)->toHaveCount(1);
    $orderItem = $order->items->first();
    expect($orderItem->package_id)->toBe($this->package->id);
    expect($orderItem->product_id)->toBeNull(); // Should not have product_id when it's a package

    // Assert stock movements created for ALL 3 products in the package
    $movements = StockMovement::where('type', 'out')
        ->where('reference_type', 'App\\Models\\ProductOrderItem')
        ->where('reference_id', $orderItem->id)
        ->get();

    expect($movements)->toHaveCount(3); // One movement for each product in the package

    // Verify each product's stock was deducted
    // Package has 3 products, quantity ordered = 3
    // Each product should be deducted: 1 (product qty in package) * 3 (packages ordered) = 3 units

    // Product 1 movement
    $movement1 = $movements->where('product_id', $this->product1->id)->first();
    expect($movement1)->not->toBeNull();
    expect($movement1->quantity)->toBe(-3); // -1 * 3 packages
    expect($movement1->quantity_before)->toBe(1000);
    expect($movement1->quantity_after)->toBe(997);

    // Product 2 movement
    $movement2 = $movements->where('product_id', $this->product2->id)->first();
    expect($movement2)->not->toBeNull();
    expect($movement2->quantity)->toBe(-3);
    expect($movement2->quantity_before)->toBe(800);
    expect($movement2->quantity_after)->toBe(797);

    // Product 3 movement
    $movement3 = $movements->where('product_id', $this->product3->id)->first();
    expect($movement3)->not->toBeNull();
    expect($movement3->quantity)->toBe(-3);
    expect($movement3->quantity_before)->toBe(600);
    expect($movement3->quantity_after)->toBe(597);

    // Verify stock levels updated correctly
    $stockLevel1 = StockLevel::where('product_id', $this->product1->id)->first();
    expect($stockLevel1->quantity)->toBe(997);

    $stockLevel2 = StockLevel::where('product_id', $this->product2->id)->first();
    expect($stockLevel2->quantity)->toBe(797);

    $stockLevel3 = StockLevel::where('product_id', $this->product3->id)->first();
    expect($stockLevel3->quantity)->toBe(597);
});

test('re-importing shipped package order does NOT duplicate stock deduction', function () {
    $fieldMapping = [
        'order_id' => 0,
        'product_name' => 1,
        'quantity' => 2,
        'created_time' => 3,
        'shipped_time' => 4,
    ];

    $packageMappings = [
        'Paket Kitab Bundle 3in1' => [
            'type' => 'package',
            'package_id' => $this->package->id,
            'package_name' => $this->package->name,
            'items_count' => 3,
            'confidence' => 100,
        ],
    ];

    $processor = new TikTokOrderProcessor(
        $this->platform,
        $this->account,
        $fieldMapping,
        [],
        $packageMappings
    );

    $orderData = [
        'order_id' => 'TT-PKG-REIMPORT',
        'product_name' => 'Paket Kitab Bundle 3in1',
        'quantity' => 5,
        'created_time' => now(),
        'shipped_time' => now()->addHour(),
    ];

    // FIRST IMPORT
    $result1 = $processor->processOrderRow($orderData);

    // Verify first import created stock movements
    $order = ProductOrder::where('platform_order_id', 'TT-PKG-REIMPORT')->first();
    $orderItem = $order->items->first();

    $movements1 = StockMovement::where('type', 'out')
        ->where('reference_type', 'App\\Models\\ProductOrderItem')
        ->where('reference_id', $orderItem->id)
        ->get();

    expect($movements1)->toHaveCount(3);

    // Check stock levels after first import
    $stockLevel1After1st = StockLevel::where('product_id', $this->product1->id)->first();
    expect($stockLevel1After1st->quantity)->toBe(995); // 1000 - (1*5)

    $stockLevel2After1st = StockLevel::where('product_id', $this->product2->id)->first();
    expect($stockLevel2After1st->quantity)->toBe(795); // 800 - (1*5)

    $stockLevel3After1st = StockLevel::where('product_id', $this->product3->id)->first();
    expect($stockLevel3After1st->quantity)->toBe(595); // 600 - (1*5)

    // SECOND IMPORT (RE-IMPORT SAME ORDER)
    $result2 = $processor->processOrderRow($orderData);

    // Assert stock NOT deducted again (should still be same values)
    $stockLevel1After2nd = StockLevel::where('product_id', $this->product1->id)->first();
    expect($stockLevel1After2nd->quantity)->toBe(995); // Still 995, NOT 990

    $stockLevel2After2nd = StockLevel::where('product_id', $this->product2->id)->first();
    expect($stockLevel2After2nd->quantity)->toBe(795); // Still 795, NOT 790

    $stockLevel3After2nd = StockLevel::where('product_id', $this->product3->id)->first();
    expect($stockLevel3After2nd->quantity)->toBe(595); // Still 595, NOT 590

    // Assert still only 3 stock movements (not 6)
    $movements2 = StockMovement::where('type', 'out')
        ->where('reference_type', 'App\\Models\\ProductOrderItem')
        ->where('reference_id', $orderItem->id)
        ->get();

    expect($movements2)->toHaveCount(3);

    // Assert order was updated, not duplicated
    $orders = ProductOrder::where('platform_order_id', 'TT-PKG-REIMPORT')->get();
    expect($orders)->toHaveCount(1);
});

test('importing pending package order does NOT deduct stock', function () {
    $processor = new TikTokOrderProcessor(
        $this->platform,
        $this->account,
        ['order_id' => 0, 'product_name' => 1, 'quantity' => 2, 'created_time' => 3],
        [],
        [
            'Paket Kitab Bundle 3in1' => [
                'type' => 'package',
                'package_id' => $this->package->id,
                'package_name' => $this->package->name,
                'items_count' => 3,
                'confidence' => 100,
            ],
        ]
    );

    $orderData = [
        'order_id' => 'TT-PKG-PENDING',
        'product_name' => 'Paket Kitab Bundle 3in1',
        'quantity' => 10,
        'created_time' => now(),
        // No shipped_time or delivered_time = pending order
    ];

    $result = $processor->processOrderRow($orderData);

    // Assert order created with pending status
    $order = ProductOrder::where('platform_order_id', 'TT-PKG-PENDING')->first();
    expect($order)->not->toBeNull();
    expect($order->status)->toBe('pending');

    // Assert NO stock movements created (pending order)
    $movements = StockMovement::where('type', 'out')->get();
    expect($movements)->toHaveCount(0);

    // Verify stock levels unchanged
    $stockLevel1 = StockLevel::where('product_id', $this->product1->id)->first();
    expect($stockLevel1->quantity)->toBe(1000); // Unchanged

    $stockLevel2 = StockLevel::where('product_id', $this->product2->id)->first();
    expect($stockLevel2->quantity)->toBe(800); // Unchanged

    $stockLevel3 = StockLevel::where('product_id', $this->product3->id)->first();
    expect($stockLevel3->quantity)->toBe(600); // Unchanged
});

test('package order item correctly identifies as package type', function () {
    $processor = new TikTokOrderProcessor(
        $this->platform,
        $this->account,
        ['order_id' => 0, 'product_name' => 1, 'quantity' => 2, 'created_time' => 3],
        [],
        [
            'Paket Kitab Bundle 3in1' => [
                'type' => 'package',
                'package_id' => $this->package->id,
                'package_name' => $this->package->name,
                'items_count' => 3,
                'confidence' => 100,
            ],
        ]
    );

    $orderData = [
        'order_id' => 'TT-PKG-TYPE-CHECK',
        'product_name' => 'Paket Kitab Bundle 3in1',
        'quantity' => 1,
        'created_time' => now(),
    ];

    $result = $processor->processOrderRow($orderData);
    $orderItem = $result['product_order_items'][0];

    // Assert item is identified as package
    expect($orderItem->isPackage())->toBeTrue();
    expect($orderItem->isProduct())->toBeFalse();
    expect($orderItem->package_id)->toBe($this->package->id);
    expect($orderItem->product_id)->toBeNull();
});

test('package mapping takes priority over product mapping', function () {
    // Create both product and package mappings for the same item
    $processor = new TikTokOrderProcessor(
        $this->platform,
        $this->account,
        ['order_id' => 0, 'product_name' => 1, 'quantity' => 2, 'created_time' => 3],
        [
            'Paket Kitab Bundle 3in1' => [
                'product_id' => $this->product1->id,
                'product_name' => $this->product1->name,
                'confidence' => 80,
            ],
        ], // Product mapping
        [
            'Paket Kitab Bundle 3in1' => [
                'type' => 'package',
                'package_id' => $this->package->id,
                'package_name' => $this->package->name,
                'items_count' => 3,
                'confidence' => 100,
            ],
        ] // Package mapping (should take priority)
    );

    $orderData = [
        'order_id' => 'TT-PKG-PRIORITY',
        'product_name' => 'Paket Kitab Bundle 3in1',
        'quantity' => 1,
        'created_time' => now(),
    ];

    $result = $processor->processOrderRow($orderData);
    $orderItem = $result['product_order_items'][0];

    // Assert package mapping was used (not product mapping)
    expect($orderItem->package_id)->toBe($this->package->id);
    expect($orderItem->product_id)->toBeNull(); // Product mapping should be ignored
    expect($orderItem->isPackage())->toBeTrue();
});

test('delivered package order deducts stock correctly', function () {
    $processor = new TikTokOrderProcessor(
        $this->platform,
        $this->account,
        ['order_id' => 0, 'product_name' => 1, 'quantity' => 2, 'created_time' => 3, 'delivered_time' => 4],
        [],
        [
            'Paket Kitab Bundle 3in1' => [
                'type' => 'package',
                'package_id' => $this->package->id,
                'package_name' => $this->package->name,
                'items_count' => 3,
                'confidence' => 100,
            ],
        ]
    );

    $orderData = [
        'order_id' => 'TT-PKG-DELIVERED',
        'product_name' => 'Paket Kitab Bundle 3in1',
        'quantity' => 4,
        'created_time' => now(),
        'delivered_time' => now()->addDays(2),
    ];

    $result = $processor->processOrderRow($orderData);

    // Assert order has delivered status
    $order = ProductOrder::where('platform_order_id', 'TT-PKG-DELIVERED')->first();
    expect($order->status)->toBe('delivered');

    // Assert stock movements created
    $orderItem = $order->items->first();
    $movements = StockMovement::where('reference_type', 'App\\Models\\ProductOrderItem')
        ->where('reference_id', $orderItem->id)
        ->get();

    expect($movements)->toHaveCount(3);

    // Verify stock deducted (4 packages * 1 product each = 4 units per product)
    $stockLevel1 = StockLevel::where('product_id', $this->product1->id)->first();
    expect($stockLevel1->quantity)->toBe(996); // 1000 - 4

    $stockLevel2 = StockLevel::where('product_id', $this->product2->id)->first();
    expect($stockLevel2->quantity)->toBe(796); // 800 - 4

    $stockLevel3 = StockLevel::where('product_id', $this->product3->id)->first();
    expect($stockLevel3->quantity)->toBe(596); // 600 - 4
});
