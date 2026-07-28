<?php

use App\Events\ExternalAccountProvisioned;
use App\Models\ExternalProvisioningRequest;
use App\Services\ExternalProvisioning\ExternalProvisioningManager;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

require_once __DIR__.'/ProvisioningTestHelpers.php';

it('provisions the account, stores credentials and marks the request succeeded', function () {
    Event::fake([ExternalAccountProvisioned::class]);
    $system = makeExternalSystem();
    [$order, $funnelProduct] = makeEligibleFunnelOrder($system);

    Http::fake([
        'external.test/*' => Http::response([
            'external_user_id' => 'ext-99',
            'login_url' => 'https://external.test/login/abc',
            'username' => 'buyer@example.test',
            'temp_password' => 'Passw0rd!',
        ], 200),
    ]);

    $request = makePendingProvisioningRequest($system, $order, $funnelProduct);

    app(ExternalProvisioningManager::class)->fulfill($request->id);

    $request->refresh();

    expect($request->status)->toBe('succeeded')
        ->and($request->external_user_id)->toBe('ext-99')
        ->and($request->login_url)->toBe('https://external.test/login/abc')
        ->and($request->credentials['username'])->toBe('buyer@example.test')
        ->and($request->credentials['temp_password'])->toBe('Passw0rd!')
        ->and($request->attempts)->toBe(1)
        ->and($request->provisioned_at)->not->toBeNull();

    Event::assertDispatched(ExternalAccountProvisioned::class, function (ExternalAccountProvisioned $event) use ($request) {
        return $event->request->id === $request->id;
    });
});

it('signs the outbound request with a bearer token and an hmac signature over the exact body', function () {
    $system = makeExternalSystem();
    [$order, $funnelProduct] = makeEligibleFunnelOrder($system);

    Http::fake(['external.test/*' => Http::response(['external_user_id' => '1'], 200)]);

    $request = makePendingProvisioningRequest($system, $order, $funnelProduct, ['hello' => 'world']);

    app(ExternalProvisioningManager::class)->fulfill($request->id);

    Http::assertSent(function (Request $req) use ($system) {
        $expectedSignature = hash_hmac('sha256', $req->body(), $system->signing_secret);

        return $req->url() === 'https://external.test/api/v1/provision'
            && $req->hasHeader('Authorization', 'Bearer '.$system->api_key)
            && $req->header('X-Signature')[0] === $expectedSignature
            && json_decode($req->body(), true) === ['hello' => 'world'];
    });
});

it('bubbles the exception and leaves the request unresolved on a server error', function () {
    $system = makeExternalSystem();
    [$order, $funnelProduct] = makeEligibleFunnelOrder($system);

    Http::fake(['external.test/*' => Http::response('boom', 500)]);

    $request = makePendingProvisioningRequest($system, $order, $funnelProduct);

    expect(fn () => app(ExternalProvisioningManager::class)->fulfill($request->id))
        ->toThrow(RequestException::class);

    $request->refresh();

    expect($request->status)->toBe('processing')
        ->and($request->attempts)->toBe(1)
        ->and($request->provisioned_at)->toBeNull();
});

it('does not call the external system again once the request already succeeded', function () {
    $system = makeExternalSystem();
    [$order] = makeEligibleFunnelOrder($system);

    Http::fake();

    $request = ExternalProvisioningRequest::factory()->succeeded()->create([
        'external_system_id' => $system->id,
        'product_order_id' => $order->id,
    ]);

    app(ExternalProvisioningManager::class)->fulfill($request->id);

    Http::assertNothingSent();
});

it('fails fast when the external system was deactivated after dispatch', function () {
    $system = makeExternalSystem();
    [$order, $funnelProduct] = makeEligibleFunnelOrder($system);

    Http::fake();
    $request = makePendingProvisioningRequest($system, $order, $funnelProduct);

    $system->update(['is_active' => false]);

    app(ExternalProvisioningManager::class)->fulfill($request->id);

    expect($request->refresh()->status)->toBe('failed');
    Http::assertNothingSent();
});
