<?php

declare(strict_types=1);

use App\Jobs\ProcessTikTokOrderImport;
use App\Models\ImportJob;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
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

    // Create initial stock
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
        'quantity' => 500,
        'available_quantity' => 500,
        'reserved_quantity' => 0,
    ]);

    // Setup fake storage
    Storage::fake('local');
});

test('importing shipped order creates stock movement records', function () {
    // Create CSV file with shipped order
    $csvContent = "Order ID,Product Name,Quantity,Created Time,Shipped Time\n";
    $csvContent .= "TT-ORDER-001,Buku Panduan Smart Tajwid,5,01/10/2025 10:00:00,02/10/2025 15:00:00\n";
    $csvContent .= "TT-ORDER-002,Buku Safinatun Najah,3,01/10/2025 11:00:00,02/10/2025 16:00:00\n";

    $csvPath = 'imports/test-tiktok-import.csv';
    Storage::put($csvPath, $csvContent);

    // Create import job
    $importJob = ImportJob::create([
        'type' => 'tiktok_orders',
        'file_path' => $csvPath,
        'file_name' => 'test-tiktok-import.csv',
        'total_rows' => 2,
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

    // Product mapping
    $productMappings = [
        'Buku Panduan Smart Tajwid' => [
            'product_id' => $this->product1->id,
            'product_name' => $this->product1->name,
        ],
        'Buku Safinatun Najah' => [
            'product_id' => $this->product2->id,
            'product_name' => $this->product2->name,
        ],
    ];

    // Execute import job
    $job = new ProcessTikTokOrderImport(
        $importJob->id,
        $this->platform->id,
        $this->account->id,
        $fieldMapping,
        $productMappings,
        [], // No package mappings
        50
    );

    $job->handle();

    // Refresh import job
    $importJob->refresh();

    // Assert import completed successfully
    expect($importJob->status)->toBe('completed');
    expect($importJob->successful_rows)->toBe(2);
    expect($importJob->failed_rows)->toBe(0);

    // Assert orders created
    $orders = ProductOrder::where('platform_id', $this->platform->id)->get();
    expect($orders)->toHaveCount(2);

    // Assert stock movements created
    $movements = StockMovement::where('type', 'out')
        ->where('reference_type', 'App\\Models\\ProductOrderItem')
        ->get();

    expect($movements)->toHaveCount(2);

    // Verify first order stock movement
    $order1 = ProductOrder::where('platform_order_id', 'TT-ORDER-001')->first();
    expect($order1)->not->toBeNull();
    expect($order1->status)->toBe('shipped');
    expect($order1->items)->toHaveCount(1);

    $item1 = $order1->items->first();
    $movement1 = StockMovement::where('reference_type', 'App\\Models\\ProductOrderItem')
        ->where('reference_id', $item1->id)
        ->where('product_id', $this->product1->id)
        ->first();

    expect($movement1)->not->toBeNull();
    expect($movement1->type)->toBe('out');
    expect($movement1->quantity)->toBe(-5);
    expect($movement1->warehouse_id)->toBe($this->warehouse->id);
    expect($movement1->quantity_before)->toBe(1000);
    expect($movement1->quantity_after)->toBe(995);

    // Verify second order stock movement
    $order2 = ProductOrder::where('platform_order_id', 'TT-ORDER-002')->first();
    expect($order2)->not->toBeNull();

    $item2 = $order2->items->first();
    $movement2 = StockMovement::where('reference_type', 'App\\Models\\ProductOrderItem')
        ->where('reference_id', $item2->id)
        ->where('product_id', $this->product2->id)
        ->first();

    expect($movement2)->not->toBeNull();
    expect($movement2->quantity)->toBe(-3);
    expect($movement2->quantity_before)->toBe(500);
    expect($movement2->quantity_after)->toBe(497);

    // Verify stock levels updated
    $stockLevel1 = StockLevel::where('product_id', $this->product1->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->first();
    expect($stockLevel1->quantity)->toBe(995);
    expect($stockLevel1->available_quantity)->toBe(995);

    $stockLevel2 = StockLevel::where('product_id', $this->product2->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->first();
    expect($stockLevel2->quantity)->toBe(497);
    expect($stockLevel2->available_quantity)->toBe(497);
});

test('importing pending order does NOT create stock movement', function () {
    // Create CSV file with pending order (no shipped_time)
    $csvContent = "Order ID,Product Name,Quantity,Created Time\n";
    $csvContent .= "TT-ORDER-PENDING,Buku Panduan Smart Tajwid,10,01/10/2025 10:00:00\n";

    $csvPath = 'imports/test-pending-order.csv';
    Storage::put($csvPath, $csvContent);

    // Create import job
    $importJob = ImportJob::create([
        'type' => 'tiktok_orders',
        'file_path' => $csvPath,
        'file_name' => 'test-pending-order.csv',
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

    $fieldMapping = [
        'order_id' => 0,
        'product_name' => 1,
        'quantity' => 2,
        'created_time' => 3,
    ];

    $productMappings = [
        'Buku Panduan Smart Tajwid' => [
            'product_id' => $this->product1->id,
            'product_name' => $this->product1->name,
        ],
    ];

    // Execute import job
    $job = new ProcessTikTokOrderImport(
        $importJob->id,
        $this->platform->id,
        $this->account->id,
        $fieldMapping,
        $productMappings,
        [], // No package mappings
        50
    );

    $job->handle();

    // Assert order created
    $order = ProductOrder::where('platform_order_id', 'TT-ORDER-PENDING')->first();
    expect($order)->not->toBeNull();
    expect($order->status)->toBe('pending');

    // Assert NO stock movement created (pending order)
    $movements = StockMovement::where('reference_type', 'App\\Models\\ProductOrderItem')->get();
    expect($movements)->toHaveCount(0);

    // Verify stock levels unchanged
    $stockLevel = StockLevel::where('product_id', $this->product1->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->first();
    expect($stockLevel->quantity)->toBe(1000); // Unchanged
});

test('re-importing same shipped order does NOT duplicate stock movement', function () {
    // Create CSV file
    $csvContent = "Order ID,Product Name,Quantity,Created Time,Shipped Time\n";
    $csvContent .= "TT-ORDER-SAME,Buku Panduan Smart Tajwid,7,01/10/2025 10:00:00,02/10/2025 15:00:00\n";

    $csvPath = 'imports/test-reimport.csv';
    Storage::put($csvPath, $csvContent);

    $fieldMapping = [
        'order_id' => 0,
        'product_name' => 1,
        'quantity' => 2,
        'created_time' => 3,
        'shipped_time' => 4,
    ];

    $productMappings = [
        'Buku Panduan Smart Tajwid' => [
            'product_id' => $this->product1->id,
            'product_name' => $this->product1->name,
        ],
    ];

    // FIRST IMPORT
    $importJob1 = ImportJob::create([
        'type' => 'tiktok_orders',
        'file_path' => $csvPath,
        'file_name' => 'test-reimport.csv',
        'total_rows' => 1,
        'processed_rows' => 0,
        'status' => 'pending',
        'platform_id' => $this->platform->id,
        'platform_account_id' => $this->account->id,
        'user_id' => $this->user->id,
        'created_by' => $this->user->id,
    ]);

    $job1 = new ProcessTikTokOrderImport(
        $importJob1->id,
        $this->platform->id,
        $this->account->id,
        $fieldMapping,
        $productMappings
    );

    $job1->handle();

    // Assert first import successful
    $stockLevel1 = StockLevel::where('product_id', $this->product1->id)->first();
    expect($stockLevel1->quantity)->toBe(993); // 1000 - 7

    $movements1 = StockMovement::where('type', 'out')->get();
    expect($movements1)->toHaveCount(1);

    // SECOND IMPORT (RE-IMPORT SAME ORDER)
    $importJob2 = ImportJob::create([
        'type' => 'tiktok_orders',
        'file_path' => $csvPath,
        'file_name' => 'test-reimport.csv',
        'total_rows' => 1,
        'processed_rows' => 0,
        'status' => 'pending',
        'platform_id' => $this->platform->id,
        'platform_account_id' => $this->account->id,
        'user_id' => $this->user->id,
        'created_by' => $this->user->id,
    ]);

    $job2 = new ProcessTikTokOrderImport(
        $importJob2->id,
        $this->platform->id,
        $this->account->id,
        $fieldMapping,
        $productMappings
    );

    $job2->handle();

    // Assert stock NOT deducted again
    $stockLevel2 = StockLevel::where('product_id', $this->product1->id)->first();
    expect($stockLevel2->quantity)->toBe(993); // Still 993, NOT 986 (993-7)

    // Assert only ONE stock movement exists
    $movements2 = StockMovement::where('type', 'out')->get();
    expect($movements2)->toHaveCount(1);

    // Assert order was updated, not duplicated
    $orders = ProductOrder::where('platform_order_id', 'TT-ORDER-SAME')->get();
    expect($orders)->toHaveCount(1);
});

test('imported order with delivered status creates stock movement', function () {
    // Create CSV file with delivered order
    $csvContent = "Order ID,Product Name,Quantity,Created Time,Delivered Time\n";
    $csvContent .= "TT-DELIVERED-001,Buku Safinatun Najah,12,01/10/2025 10:00:00,03/10/2025 18:00:00\n";

    $csvPath = 'imports/test-delivered.csv';
    Storage::put($csvPath, $csvContent);

    $importJob = ImportJob::create([
        'type' => 'tiktok_orders',
        'file_path' => $csvPath,
        'file_name' => 'test-delivered.csv',
        'total_rows' => 1,
        'status' => 'pending',
        'platform_id' => $this->platform->id,
        'platform_account_id' => $this->account->id,
        'user_id' => $this->user->id,
        'created_by' => $this->user->id,
    ]);

    $fieldMapping = [
        'order_id' => 0,
        'product_name' => 1,
        'quantity' => 2,
        'created_time' => 3,
        'delivered_time' => 4,
    ];

    $productMappings = [
        'Buku Safinatun Najah' => [
            'product_id' => $this->product2->id,
            'product_name' => $this->product2->name,
        ],
    ];

    $job = new ProcessTikTokOrderImport(
        $importJob->id,
        $this->platform->id,
        $this->account->id,
        $fieldMapping,
        $productMappings
    );

    $job->handle();

    // Assert order status is delivered
    $order = ProductOrder::where('platform_order_id', 'TT-DELIVERED-001')->first();
    expect($order->status)->toBe('delivered');

    // Assert stock movement created for delivered order
    $movements = StockMovement::where('type', 'out')
        ->where('product_id', $this->product2->id)
        ->get();

    expect($movements)->toHaveCount(1);
    expect($movements->first()->quantity)->toBe(-12);

    // Assert stock deducted
    $stockLevel = StockLevel::where('product_id', $this->product2->id)->first();
    expect($stockLevel->quantity)->toBe(488); // 500 - 12
});
