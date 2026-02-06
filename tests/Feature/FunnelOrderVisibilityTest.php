<?php

use App\Models\Funnel;
use App\Models\FunnelOrder;
use App\Models\ProductOrder;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('funnel defaults to showing orders in admin', function () {
    $funnel = Funnel::factory()->create();

    expect($funnel->show_orders_in_admin)->toBeTrue();
    expect($funnel->shouldShowOrdersInAdmin())->toBeTrue();
});

test('funnel can be set to hide orders from admin', function () {
    $funnel = Funnel::factory()->hideOrdersFromAdmin()->create();

    expect($funnel->show_orders_in_admin)->toBeFalse();
    expect($funnel->shouldShowOrdersInAdmin())->toBeFalse();
});

test('funnel show_orders_in_admin can be toggled via update', function () {
    $user = User::factory()->create();
    $funnel = Funnel::factory()->for($user)->create();

    $response = $this->actingAs($user)->putJson("/api/v1/funnels/{$funnel->uuid}", [
        'show_orders_in_admin' => false,
    ]);

    $response->assertSuccessful();

    $funnel->refresh();
    expect($funnel->show_orders_in_admin)->toBeFalse();
});

test('funnel api response includes show_orders_in_admin', function () {
    $user = User::factory()->create();
    $funnel = Funnel::factory()->for($user)->create();

    $response = $this->actingAs($user)->getJson("/api/v1/funnels/{$funnel->uuid}");

    $response->assertSuccessful();
    $response->assertJsonPath('data.show_orders_in_admin', true);
});

test('visibleInAdmin scope excludes hidden orders', function () {
    $visibleOrder = ProductOrder::factory()->create([
        'source' => 'funnel',
        'hidden_from_admin' => false,
    ]);

    $hiddenOrder = ProductOrder::factory()->create([
        'source' => 'funnel',
        'hidden_from_admin' => true,
    ]);

    $visibleOrders = ProductOrder::visibleInAdmin()->get();

    expect($visibleOrders->contains($visibleOrder))->toBeTrue();
    expect($visibleOrders->contains($hiddenOrder))->toBeFalse();
});

test('hidden orders still appear in all orders query without scope', function () {
    $hiddenOrder = ProductOrder::factory()->create([
        'source' => 'funnel',
        'hidden_from_admin' => true,
    ]);

    $allOrders = ProductOrder::all();

    expect($allOrders->contains($hiddenOrder))->toBeTrue();
});

test('product order hidden_from_admin defaults to false', function () {
    $order = ProductOrder::factory()->create([
        'source' => 'funnel',
    ]);

    $order->refresh();
    expect($order->hidden_from_admin)->toBeFalse();
});

test('toggling show_orders_in_admin OFF retroactively hides existing orders', function () {
    $user = User::factory()->create();
    $funnel = Funnel::factory()->for($user)->create(['show_orders_in_admin' => true]);

    $productOrder = ProductOrder::factory()->create(['source' => 'funnel', 'hidden_from_admin' => false]);
    FunnelOrder::factory()->create(['funnel_id' => $funnel->id, 'product_order_id' => $productOrder->id]);

    $response = $this->actingAs($user)->putJson("/api/v1/funnels/{$funnel->uuid}", [
        'show_orders_in_admin' => false,
    ]);

    $response->assertSuccessful();

    $productOrder->refresh();
    expect($productOrder->hidden_from_admin)->toBeTrue();
    expect(ProductOrder::visibleInAdmin()->where('id', $productOrder->id)->exists())->toBeFalse();
});

test('toggling show_orders_in_admin ON retroactively unhides existing orders', function () {
    $user = User::factory()->create();
    $funnel = Funnel::factory()->for($user)->hideOrdersFromAdmin()->create();

    $productOrder = ProductOrder::factory()->create(['source' => 'funnel', 'hidden_from_admin' => true]);
    FunnelOrder::factory()->create(['funnel_id' => $funnel->id, 'product_order_id' => $productOrder->id]);

    $response = $this->actingAs($user)->putJson("/api/v1/funnels/{$funnel->uuid}", [
        'show_orders_in_admin' => true,
    ]);

    $response->assertSuccessful();

    $productOrder->refresh();
    expect($productOrder->hidden_from_admin)->toBeFalse();
    expect(ProductOrder::visibleInAdmin()->where('id', $productOrder->id)->exists())->toBeTrue();
});
