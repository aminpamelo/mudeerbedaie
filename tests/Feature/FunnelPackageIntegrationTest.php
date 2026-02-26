<?php

use App\Models\Funnel;
use App\Models\FunnelProduct;
use App\Models\FunnelStep;
use App\Models\Package;
use App\Models\Product;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('search packages endpoint returns active packages', function () {
    $user = User::factory()->create();

    Package::factory()->create(['name' => 'Starter Bundle', 'status' => 'active']);
    Package::factory()->create(['name' => 'Pro Bundle', 'status' => 'active']);
    Package::factory()->create(['name' => 'Draft Package', 'status' => 'draft']);

    $response = $this->actingAs($user)->getJson('/api/v1/packages/search?q=Bundle');

    $response->assertSuccessful();
    $response->assertJsonCount(2, 'data');
});

test('search packages returns package details with item count', function () {
    $user = User::factory()->create();

    $package = Package::factory()->create(['name' => 'Test Package', 'status' => 'active']);

    $response = $this->actingAs($user)->getJson('/api/v1/packages/search?q=Test');

    $response->assertSuccessful();
    $response->assertJsonPath('data.0.name', 'Test Package');
    $response->assertJsonPath('data.0.item_count', 0);
});

test('can add package to funnel step', function () {
    $user = User::factory()->create();
    $funnel = Funnel::factory()->for($user)->create();
    $step = FunnelStep::create([
        'funnel_id' => $funnel->id,
        'name' => 'Checkout',
        'slug' => 'checkout',
        'type' => 'checkout',
        'sort_order' => 0,
        'is_active' => true,
    ]);
    $package = Package::factory()->create(['status' => 'active', 'price' => 99.00]);

    $response = $this->actingAs($user)->postJson("/api/v1/funnels/{$funnel->uuid}/steps/{$step->id}/products", [
        'package_id' => $package->id,
        'type' => 'main',
        'funnel_price' => 89.00,
    ]);

    $response->assertStatus(201);
    $response->assertJsonPath('data.is_package', true);
    $response->assertJsonPath('data.source_package.id', $package->id);

    $this->assertDatabaseHas('funnel_products', [
        'funnel_step_id' => $step->id,
        'package_id' => $package->id,
        'funnel_price' => 89.00,
    ]);
});

test('cannot add both product_id and package_id to same funnel product', function () {
    $user = User::factory()->create();
    $funnel = Funnel::factory()->for($user)->create();
    $step = FunnelStep::create([
        'funnel_id' => $funnel->id,
        'name' => 'Checkout',
        'slug' => 'checkout',
        'type' => 'checkout',
        'sort_order' => 0,
        'is_active' => true,
    ]);
    $product = Product::factory()->create(['status' => 'active']);
    $package = Package::factory()->create(['status' => 'active']);

    $response = $this->actingAs($user)->postJson("/api/v1/funnels/{$funnel->uuid}/steps/{$step->id}/products", [
        'product_id' => $product->id,
        'package_id' => $package->id,
        'type' => 'main',
        'funnel_price' => 50.00,
    ]);

    $response->assertStatus(422);
});

test('funnel product model correctly identifies package type', function () {
    $package = Package::factory()->create();
    $funnel = Funnel::factory()->create();
    $step = FunnelStep::create([
        'funnel_id' => $funnel->id,
        'name' => 'Checkout',
        'slug' => 'checkout',
        'type' => 'checkout',
        'sort_order' => 0,
        'is_active' => true,
    ]);

    $funnelProduct = FunnelProduct::create([
        'funnel_step_id' => $step->id,
        'package_id' => $package->id,
        'type' => 'main',
        'name' => 'Test Package',
        'funnel_price' => 100.00,
        'sort_order' => 0,
    ]);

    expect($funnelProduct->isPackage())->toBeTrue();
    expect($funnelProduct->isProduct())->toBeFalse();
    expect($funnelProduct->isCourse())->toBeFalse();
});

test('funnel product display name falls back to package name', function () {
    $package = Package::factory()->create(['name' => 'Premium Bundle']);
    $funnel = Funnel::factory()->create();
    $step = FunnelStep::create([
        'funnel_id' => $funnel->id,
        'name' => 'Checkout',
        'slug' => 'checkout',
        'type' => 'checkout',
        'sort_order' => 0,
        'is_active' => true,
    ]);

    $funnelProduct = FunnelProduct::create([
        'funnel_step_id' => $step->id,
        'package_id' => $package->id,
        'type' => 'main',
        'funnel_price' => 100.00,
        'sort_order' => 0,
    ]);

    expect($funnelProduct->getDisplayName())->toBe('Premium Bundle');
});

test('funnel product list includes package info', function () {
    $user = User::factory()->create();
    $funnel = Funnel::factory()->for($user)->create();
    $step = FunnelStep::create([
        'funnel_id' => $funnel->id,
        'name' => 'Checkout',
        'slug' => 'checkout',
        'type' => 'checkout',
        'sort_order' => 0,
        'is_active' => true,
    ]);
    $package = Package::factory()->create(['name' => 'Bundle Deal', 'status' => 'active']);

    FunnelProduct::create([
        'funnel_step_id' => $step->id,
        'package_id' => $package->id,
        'type' => 'main',
        'name' => 'Bundle Deal',
        'funnel_price' => 79.00,
        'sort_order' => 0,
    ]);

    $response = $this->actingAs($user)->getJson("/api/v1/funnels/{$funnel->uuid}/products");

    $response->assertSuccessful();
    $response->assertJsonPath('data.0.is_package', true);
    $response->assertJsonPath('data.0.source_package.id', $package->id);
});
