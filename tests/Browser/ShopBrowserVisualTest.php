<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\StockLevel;

it('renders the redesigned shop with in-stock products and hides out-of-stock', function () {
    foreach (range(1, 15) as $i) {
        Product::factory()->create([
            'type' => 'simple',
            'status' => 'active',
            'track_quantity' => false,
            'name' => "Katalog Produk {$i}",
            'base_price' => 39 + $i,
        ]);
    }

    $oos = Product::factory()->create([
        'type' => 'simple',
        'status' => 'active',
        'track_quantity' => true,
        'name' => 'Barang Habis Stok',
    ]);
    StockLevel::factory()->create(['product_id' => $oos->id, 'quantity' => 3, 'reserved_quantity' => 3]);

    visit('/shop')
        ->assertNoJavaScriptErrors()
        ->assertSee('Kedai')
        ->assertSee('Katalog Produk')
        ->assertDontSee('Barang Habis Stok')
        ->screenshot(true, 'shop-browser');
});
