<?php

use App\Jobs\ExternalProvisioning\ProvisionExternalAccountJob;
use App\Models\ExternalProvisioningRequest;
use App\Models\ExternalSystem;
use App\Models\Package;
use App\Models\Product;
use App\Models\ProductOrder;
use App\Services\ExternalProvisioning\ExternalProvisioningManager;
use Illuminate\Support\Facades\Queue;

require_once __DIR__.'/ProvisioningTestHelpers.php';

beforeEach(function () {
    Queue::fake();
});

/**
 * A product marked external-system + linked to a system, added to a paid order.
 */
function makeExternalProductOrder(ExternalSystem $system, ?string $plan = 'pro'): ProductOrder
{
    $product = Product::factory()->create([
        'name' => 'DaiePRO Access',
        'fulfillment_type' => 'external_system',
        'metadata' => ['provisioning' => ['external_system_id' => $system->id, 'plan' => $plan]],
    ]);

    $order = ProductOrder::factory()->create([
        'source' => 'storefront',
        'guest_email' => 'buyer@example.test',
        'customer_name' => 'Buyer Test',
        'customer_phone' => '+60123456789',
    ]);

    $order->items()->create([
        'product_id' => $product->id,
        'product_name' => $product->name,
        'quantity_ordered' => 1,
        'unit_price' => 79,
        'total_price' => 79,
    ]);

    return $order;
}

it('dispatches provisioning for an external-system product order', function () {
    $system = makeExternalSystem();
    $order = makeExternalProductOrder($system, plan: 'pro');

    app(ExternalProvisioningManager::class)->dispatchForOrder($order);

    $request = ExternalProvisioningRequest::first();

    expect($request)->not->toBeNull()
        ->and($request->external_system_id)->toBe($system->id)
        ->and($request->product_order_id)->toBe($order->id)
        ->and($request->funnel_product_id)->toBeNull()
        ->and($request->request_payload['product']['plan'])->toBe('pro')
        ->and($request->request_payload['customer']['email'])->toBe('buyer@example.test');

    Queue::assertPushed(ProvisionExternalAccountJob::class, 1);
});

it('dispatches provisioning for an external-system package order', function () {
    $system = makeExternalSystem();

    $package = Package::factory()->create([
        'fulfillment_type' => 'external_system',
        'metadata' => ['provisioning' => ['external_system_id' => $system->id, 'plan' => 'gold']],
    ]);

    $order = ProductOrder::factory()->create([
        'source' => 'storefront',
        'guest_email' => 'buyer@example.test',
    ]);

    $order->items()->create([
        'package_id' => $package->id,
        'product_name' => $package->name,
        'quantity_ordered' => 1,
        'unit_price' => 199,
        'total_price' => 199,
    ]);

    app(ExternalProvisioningManager::class)->dispatchForOrder($order);

    $request = ExternalProvisioningRequest::first();

    expect($request)->not->toBeNull()
        ->and($request->external_system_id)->toBe($system->id)
        ->and($request->request_payload['product']['package_id'])->toBe($package->id)
        ->and($request->request_payload['product']['plan'])->toBe('gold');

    Queue::assertPushed(ProvisionExternalAccountJob::class, 1);
});

it('ignores products not marked external-system', function () {
    makeExternalSystem();

    $product = Product::factory()->create(['fulfillment_type' => 'physical']);
    $order = ProductOrder::factory()->create(['source' => 'storefront']);
    $order->items()->create([
        'product_id' => $product->id,
        'product_name' => $product->name,
        'quantity_ordered' => 1,
        'unit_price' => 10,
        'total_price' => 10,
    ]);

    app(ExternalProvisioningManager::class)->dispatchForOrder($order);

    expect(ExternalProvisioningRequest::count())->toBe(0);
    Queue::assertNothingPushed();
});

it('ignores external-system products with no system linked', function () {
    makeExternalSystem();

    $product = Product::factory()->create([
        'fulfillment_type' => 'external_system',
        'metadata' => null,
    ]);
    $order = ProductOrder::factory()->create(['source' => 'storefront']);
    $order->items()->create([
        'product_id' => $product->id,
        'product_name' => $product->name,
        'quantity_ordered' => 1,
        'unit_price' => 10,
        'total_price' => 10,
    ]);

    app(ExternalProvisioningManager::class)->dispatchForOrder($order);

    expect(ExternalProvisioningRequest::count())->toBe(0);
    Queue::assertNothingPushed();
});

it('is idempotent for product orders', function () {
    $system = makeExternalSystem();
    $order = makeExternalProductOrder($system);
    $manager = app(ExternalProvisioningManager::class);

    $manager->dispatchForOrder($order);
    $manager->dispatchForOrder($order);

    expect(ExternalProvisioningRequest::count())->toBe(1);
    Queue::assertPushed(ProvisionExternalAccountJob::class, 1);
});

it('auto-provisions when a product order becomes paid via the observer', function () {
    $system = makeExternalSystem();
    $order = makeExternalProductOrder($system);

    expect(ExternalProvisioningRequest::count())->toBe(0);

    $order->update(['payment_status' => 'paid', 'paid_time' => now()]);

    expect(ExternalProvisioningRequest::where('product_order_id', $order->id)->count())->toBe(1);
    Queue::assertPushed(ProvisionExternalAccountJob::class, 1);
});

it('filters orders needing an external-system account', function () {
    $system = makeExternalSystem();

    $needs = makeExternalProductOrder($system);
    $physicalProduct = Product::factory()->create(['fulfillment_type' => 'physical']);
    $plain = ProductOrder::factory()->create(['source' => 'storefront']);
    $plain->items()->create([
        'product_id' => $physicalProduct->id,
        'product_name' => $physicalProduct->name,
        'quantity_ordered' => 1,
        'unit_price' => 10,
        'total_price' => 10,
    ]);

    $needsIds = ProductOrder::query()
        ->where(function ($q) {
            $q->whereHas('items.product', fn ($p) => $p->where('fulfillment_type', 'external_system'))
                ->orWhereHas('items.package', fn ($p) => $p->where('fulfillment_type', 'external_system'));
        })
        ->whereDoesntHave('provisioningRequests', fn ($r) => $r->succeeded())
        ->pluck('id');

    expect($needsIds)->toContain($needs->id)
        ->and($needsIds)->not->toContain($plain->id);
});
