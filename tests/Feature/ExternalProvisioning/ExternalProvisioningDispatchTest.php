<?php

use App\Jobs\ExternalProvisioning\ProvisionExternalAccountJob;
use App\Models\ExternalProvisioningRequest;
use App\Models\FunnelProduct;
use App\Models\Product;
use App\Models\ProductOrder;
use App\Services\ExternalProvisioning\ExternalProvisioningManager;
use Illuminate\Support\Facades\Queue;

require_once __DIR__.'/ProvisioningTestHelpers.php';

beforeEach(function () {
    Queue::fake();
});

it('dispatches a provisioning job for an eligible funnel order', function () {
    $system = makeExternalSystem();
    [$order] = makeEligibleFunnelOrder($system, plan: 'gold');

    app(ExternalProvisioningManager::class)->dispatchForOrder($order);

    $request = ExternalProvisioningRequest::first();

    expect($request)->not->toBeNull()
        ->and($request->status)->toBe('pending')
        ->and($request->external_system_id)->toBe($system->id)
        ->and($request->product_order_id)->toBe($order->id)
        ->and($request->request_payload['product']['plan'])->toBe('gold')
        ->and($request->request_payload['customer']['email'])->toBe('buyer@example.test');

    Queue::assertPushed(ProvisionExternalAccountJob::class, 1);
});

it('is idempotent when dispatched twice for the same order', function () {
    $system = makeExternalSystem();
    [$order] = makeEligibleFunnelOrder($system);
    $manager = app(ExternalProvisioningManager::class);

    $manager->dispatchForOrder($order);
    $manager->dispatchForOrder($order);

    expect(ExternalProvisioningRequest::count())->toBe(1);
    Queue::assertPushed(ProvisionExternalAccountJob::class, 1);
});

it('ignores funnel products that have not opted in to provisioning', function () {
    $system = makeExternalSystem();

    $order = ProductOrder::factory()->create(['source' => 'funnel']);
    $funnelProduct = FunnelProduct::factory()->create(); // no provisioning settings
    $order->items()->create([
        'product_id' => Product::factory()->create()->id,
        'product_name' => 'No provisioning',
        'quantity_ordered' => 1,
        'unit_price' => 50,
        'total_price' => 50,
        'item_metadata' => ['funnel_product_id' => $funnelProduct->id],
    ]);

    app(ExternalProvisioningManager::class)->dispatchForOrder($order);

    expect(ExternalProvisioningRequest::count())->toBe(0);
    Queue::assertNothingPushed();
});

it('skips provisioning when the external system is inactive', function () {
    $system = makeExternalSystem(['is_active' => false]);
    [$order] = makeEligibleFunnelOrder($system);

    app(ExternalProvisioningManager::class)->dispatchForOrder($order);

    expect(ExternalProvisioningRequest::count())->toBe(0);
    Queue::assertNothingPushed();
});

it('does nothing for an order without funnel items', function () {
    $system = makeExternalSystem();
    $order = ProductOrder::factory()->create(['source' => 'storefront']);
    $order->items()->create([
        'product_id' => Product::factory()->create()->id,
        'product_name' => 'Plain product',
        'quantity_ordered' => 1,
        'unit_price' => 20,
        'total_price' => 20,
    ]);

    app(ExternalProvisioningManager::class)->dispatchForOrder($order);

    expect(ExternalProvisioningRequest::count())->toBe(0);
    Queue::assertNothingPushed();
});
