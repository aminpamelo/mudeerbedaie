<?php

use App\Jobs\ExternalProvisioning\ProvisionExternalAccountJob;
use App\Models\ExternalProvisioningRequest;
use App\Models\ProductOrder;
use App\Services\Funnel\FunnelAutomationService;
use Illuminate\Support\Facades\Queue;

require_once __DIR__.'/ProvisioningTestHelpers.php';

it('dispatches provisioning from triggerPurchaseCompleted for an eligible funnel order', function () {
    Queue::fake();
    $system = makeExternalSystem();
    [$order] = makeEligibleFunnelOrder($system);

    app(FunnelAutomationService::class)->triggerPurchaseCompleted($order);

    Queue::assertPushed(ProvisionExternalAccountJob::class, 1);

    expect(ExternalProvisioningRequest::where('product_order_id', $order->id)->count())->toBe(1);
});

it('does not break the purchase flow when there is nothing to provision', function () {
    Queue::fake();
    $order = ProductOrder::factory()->create([
        'source' => 'funnel',
        'metadata' => ['funnel_id' => 1],
    ]);

    app(FunnelAutomationService::class)->triggerPurchaseCompleted($order);

    Queue::assertNotPushed(ProvisionExternalAccountJob::class);
    expect(ExternalProvisioningRequest::count())->toBe(0);
});
