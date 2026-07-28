<?php

use App\Jobs\ExternalProvisioning\ProvisionExternalAccountJob;
use App\Services\ExternalProvisioning\ExternalProvisioningManager;
use Illuminate\Support\Facades\Http;

require_once __DIR__.'/ProvisioningTestHelpers.php';

it('fulfils the request when the job handle runs', function () {
    $system = makeExternalSystem();
    [$order, $funnelProduct] = makeEligibleFunnelOrder($system);

    Http::fake([
        'external.test/*' => Http::response([
            'external_user_id' => 'x1',
            'login_url' => 'https://external.test/l',
        ], 200),
    ]);

    $request = makePendingProvisioningRequest($system, $order, $funnelProduct);

    (new ProvisionExternalAccountJob($request->id))->handle(app(ExternalProvisioningManager::class));

    expect($request->refresh()->status)->toBe('succeeded')
        ->and($request->external_user_id)->toBe('x1');
});

it('marks the request failed when the job is exhausted', function () {
    $system = makeExternalSystem();
    [$order, $funnelProduct] = makeEligibleFunnelOrder($system);

    $request = makePendingProvisioningRequest($system, $order, $funnelProduct);

    (new ProvisionExternalAccountJob($request->id))->failed(new RuntimeException('connection refused'));

    $request->refresh();

    expect($request->status)->toBe('failed')
        ->and($request->last_error)->toBe('connection refused');
});
