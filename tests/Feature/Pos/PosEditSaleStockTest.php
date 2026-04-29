<?php

use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('restoreStock returns quantity to the warehouse and writes an in movement', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create([
        'status' => 'active',
        'track_quantity' => true,
    ]);
    StockLevel::create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 10,
    ]);

    $order = ProductOrder::factory()->create(['source' => 'pos']);
    $item = $order->items()->create([
        'itemable_type' => Product::class,
        'itemable_id' => $product->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'product_name' => $product->name,
        'sku' => $product->sku ?? 'sku',
        'quantity_ordered' => 3,
        'unit_price' => 10,
        'total_price' => 30,
    ]);

    $item->restoreStock();

    $level = StockLevel::where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->first();
    expect($level->quantity)->toBe(13);

    $movement = StockMovement::where('reference_id', $order->id)->where('type', 'in')->first();
    expect($movement)->not->toBeNull();
    expect($movement->quantity)->toBe(3);
});
