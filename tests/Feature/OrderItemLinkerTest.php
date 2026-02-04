<?php

declare(strict_types=1);

use App\Models\Package;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\PlatformSkuMapping;
use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\ProductOrderItem;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\TikTok\OrderItemLinker;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// --- linkItemToMapping ---

test('linkItemToMapping links item to product mapping', function () {
    $platform = Platform::factory()->create();
    $account = PlatformAccount::factory()->create(['platform_id' => $platform->id]);
    $product = Product::factory()->create();
    $warehouse = Warehouse::factory()->active()->default()->create();

    PlatformSkuMapping::create([
        'platform_id' => $platform->id,
        'platform_account_id' => $account->id,
        'platform_sku' => 'TT-SKU-001',
        'product_id' => $product->id,
        'is_active' => true,
    ]);

    $order = ProductOrder::factory()->create([
        'platform_id' => $platform->id,
        'platform_account_id' => $account->id,
    ]);

    $item = ProductOrderItem::create([
        'order_id' => $order->id,
        'platform_sku' => 'TT-SKU-001',
        'product_name' => 'TikTok Product',
        'quantity_ordered' => 1,
        'unit_price' => 25.00,
        'total_price' => 25.00,
    ]);

    $linker = app(OrderItemLinker::class);
    $result = $linker->linkItemToMapping($item, $platform->id, $account->id);

    expect($result)->toBeTrue();

    $item->refresh();
    expect($item->product_id)->toBe($product->id);
    expect($item->package_id)->toBeNull();
    expect($item->warehouse_id)->toBe($warehouse->id);
});

test('linkItemToMapping links item to package mapping', function () {
    $platform = Platform::factory()->create();
    $account = PlatformAccount::factory()->create(['platform_id' => $platform->id]);
    $package = Package::factory()->create();
    $warehouse = Warehouse::factory()->active()->default()->create();

    PlatformSkuMapping::create([
        'platform_id' => $platform->id,
        'platform_account_id' => $account->id,
        'platform_sku' => 'TT-PKG-001',
        'package_id' => $package->id,
        'is_active' => true,
    ]);

    $order = ProductOrder::factory()->create([
        'platform_id' => $platform->id,
        'platform_account_id' => $account->id,
    ]);

    $item = ProductOrderItem::create([
        'order_id' => $order->id,
        'platform_sku' => 'TT-PKG-001',
        'product_name' => 'TikTok Package Product',
        'quantity_ordered' => 1,
        'unit_price' => 50.00,
        'total_price' => 50.00,
    ]);

    $linker = app(OrderItemLinker::class);
    $result = $linker->linkItemToMapping($item, $platform->id, $account->id);

    expect($result)->toBeTrue();

    $item->refresh();
    expect($item->package_id)->toBe($package->id);
    expect($item->product_id)->toBeNull();
    expect($item->warehouse_id)->toBe($warehouse->id);
});

test('linkItemToMapping returns false when no mapping exists', function () {
    $platform = Platform::factory()->create();
    $account = PlatformAccount::factory()->create(['platform_id' => $platform->id]);

    $order = ProductOrder::factory()->create([
        'platform_id' => $platform->id,
        'platform_account_id' => $account->id,
    ]);

    $item = ProductOrderItem::create([
        'order_id' => $order->id,
        'platform_sku' => 'UNMAPPED-SKU',
        'product_name' => 'Unmapped Product',
        'quantity_ordered' => 1,
        'unit_price' => 10.00,
        'total_price' => 10.00,
    ]);

    $linker = app(OrderItemLinker::class);
    $result = $linker->linkItemToMapping($item, $platform->id, $account->id);

    expect($result)->toBeFalse();

    $item->refresh();
    expect($item->product_id)->toBeNull();
    expect($item->package_id)->toBeNull();
});

test('linkItemToMapping returns false when platform_sku is null', function () {
    $linker = app(OrderItemLinker::class);

    $order = ProductOrder::factory()->create();
    $item = ProductOrderItem::create([
        'order_id' => $order->id,
        'platform_sku' => null,
        'product_name' => 'No SKU Product',
        'quantity_ordered' => 1,
        'unit_price' => 10.00,
        'total_price' => 10.00,
    ]);

    $result = $linker->linkItemToMapping($item, 1, 1);

    expect($result)->toBeFalse();
});

// --- deductStockForOrder ---

test('deductStockForOrder deducts stock for shipped order with product items', function () {
    $platform = Platform::factory()->create();
    $account = PlatformAccount::factory()->create(['platform_id' => $platform->id]);
    $product = Product::factory()->create();
    $warehouse = Warehouse::factory()->active()->default()->create();

    StockLevel::create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 100,
        'reserved_quantity' => 0,
        'available_quantity' => 100,
    ]);

    $order = ProductOrder::factory()->create([
        'platform_id' => $platform->id,
        'platform_account_id' => $account->id,
        'status' => 'shipped',
    ]);

    ProductOrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'platform_sku' => 'SKU-SHIP-001',
        'product_name' => 'Shipped Product',
        'quantity_ordered' => 3,
        'unit_price' => 25.00,
        'total_price' => 75.00,
    ]);

    $linker = app(OrderItemLinker::class);
    $result = $linker->deductStockForOrder($order);

    expect($result['deducted'])->toBe(1);

    $stockLevel = StockLevel::where('product_id', $product->id)
        ->where('warehouse_id', $warehouse->id)
        ->first();

    expect($stockLevel->quantity)->toBe(97);

    expect(StockMovement::where('product_id', $product->id)->where('type', 'out')->count())->toBe(1);
});

test('deductStockForOrder skips non-shipped orders', function () {
    $order = ProductOrder::factory()->create(['status' => 'pending']);

    $linker = app(OrderItemLinker::class);
    $result = $linker->deductStockForOrder($order);

    expect($result['deducted'])->toBe(0);
    expect($result['skipped'])->toBe(0);
});

test('deductStockForOrder prevents duplicate deductions', function () {
    $platform = Platform::factory()->create();
    $account = PlatformAccount::factory()->create(['platform_id' => $platform->id]);
    $product = Product::factory()->create();
    $warehouse = Warehouse::factory()->active()->default()->create();

    StockLevel::create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 100,
        'reserved_quantity' => 0,
        'available_quantity' => 100,
    ]);

    $order = ProductOrder::factory()->create([
        'platform_id' => $platform->id,
        'platform_account_id' => $account->id,
        'status' => 'delivered',
    ]);

    ProductOrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'platform_sku' => 'SKU-DUP-001',
        'product_name' => 'Duplicate Test',
        'quantity_ordered' => 5,
        'unit_price' => 10.00,
        'total_price' => 50.00,
    ]);

    $linker = app(OrderItemLinker::class);

    // First deduction
    $result1 = $linker->deductStockForOrder($order);
    expect($result1['deducted'])->toBe(1);

    // Second deduction - should skip
    $order->refresh();
    $result2 = $linker->deductStockForOrder($order);
    expect($result2['skipped'])->toBe(1);

    // Stock should only be deducted once
    $stockLevel = StockLevel::where('product_id', $product->id)->first();
    expect($stockLevel->quantity)->toBe(95);
    expect(StockMovement::where('product_id', $product->id)->count())->toBe(1);
});

// --- Backfill command ---

test('backfill command links unlinked order items', function () {
    $platform = Platform::factory()->create();
    $account = PlatformAccount::factory()->create(['platform_id' => $platform->id]);
    $product = Product::factory()->create();
    Warehouse::factory()->active()->default()->create();

    PlatformSkuMapping::create([
        'platform_id' => $platform->id,
        'platform_account_id' => $account->id,
        'platform_sku' => 'BACKFILL-SKU',
        'product_id' => $product->id,
        'is_active' => true,
    ]);

    $order = ProductOrder::factory()->create([
        'platform_id' => $platform->id,
        'platform_account_id' => $account->id,
        'status' => 'pending',
    ]);

    ProductOrderItem::create([
        'order_id' => $order->id,
        'platform_sku' => 'BACKFILL-SKU',
        'product_name' => 'Backfill Test Product',
        'quantity_ordered' => 1,
        'unit_price' => 25.00,
        'total_price' => 25.00,
    ]);

    $this->artisan('tiktok:backfill-order-mappings')
        ->assertSuccessful();

    $item = ProductOrderItem::where('platform_sku', 'BACKFILL-SKU')->first();
    expect($item->product_id)->toBe($product->id);
});
