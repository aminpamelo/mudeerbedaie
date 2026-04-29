<?php

use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\SalesSource;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('non-pos orders cannot be edited via POS endpoint', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $order = ProductOrder::factory()->create(['source' => 'website']);
    $source = SalesSource::factory()->create(['is_active' => true]);
    $product = Product::factory()->create(['status' => 'active']);

    $response = $this->actingAs($admin)->putJson(route('api.pos.sales.update', $order), [
        'sales_source_id' => $source->id,
        'customer_name' => 'X',
        'customer_phone' => '0',
        'payment_method' => 'cash',
        'items' => [[
            'itemable_type' => 'product',
            'itemable_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 10,
        ]],
    ]);

    $response->assertForbidden();
});
