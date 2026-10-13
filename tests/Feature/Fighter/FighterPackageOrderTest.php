<?php

declare(strict_types=1);

use App\Models\Package;
use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists active packages with their contents in the fighter catalog', function () {
    $fighter = User::factory()->create(['role' => 'fighter']);
    $book = Product::factory()->create(['status' => 'active', 'name' => 'Buku Qadha']);
    $package = Package::factory()->create(['name' => 'Pakej Combo Qadha', 'price' => 89, 'original_price' => 120, 'status' => 'active', 'start_date' => null, 'end_date' => null, 'max_purchases' => null]);
    $package->products()->attach($book->id, ['quantity' => 2, 'sort_order' => 0, 'original_price' => 60]);
    Package::factory()->create(['name' => 'Pakej Draf', 'status' => 'draft']);

    $response = $this->actingAs($fighter)->getJson('/fighter/catalog')->assertOk();

    expect($response->json('packages'))->toHaveCount(1)
        ->and($response->json('packages.0.name'))->toBe('Pakej Combo Qadha')
        ->and($response->json('packages.0.price'))->toEqual(89)
        ->and($response->json('packages.0.contents'))->toBe(['2× Buku Qadha']);

    $this->actingAs($fighter)->getJson('/fighter/catalog?search=tiada')->assertJsonCount(0, 'packages');
});

it('lets a fighter record a manual order for a package', function () {
    $fighter = User::factory()->create(['role' => 'fighter']);
    $package = Package::factory()->create(['name' => 'Pakej Combo', 'price' => 89, 'status' => 'active']);

    $this->actingAs($fighter)->postJson('/api/pos/sales', [
        'customer_name' => 'Siti',
        'customer_phone' => '60198887766',
        'payment_method' => 'cod',
        'payment_status' => 'pending',
        'items' => [
            ['itemable_type' => 'package', 'itemable_id' => $package->id, 'quantity' => 1, 'unit_price' => 89],
        ],
    ])->assertSuccessful();

    $order = ProductOrder::query()->latest('id')->firstOrFail();
    $item = $order->items()->sole();

    expect($item->package_id)->toBe($package->id)
        ->and($item->product_name)->toBe('Pakej Combo')
        ->and((float) $order->total_amount)->toEqual(89.0)
        ->and($order->sales_source_id)->toBe($fighter->fresh()->sales_source_id);
});
