<?php

use App\Models\ExternalProvisioningRequest;
use App\Models\ExternalSystem;
use App\Models\ProductOrder;
use App\Models\User;
use App\Services\ExternalProvisioning\ExternalProvisioningManager;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;

function extSystem(): ExternalSystem
{
    return ExternalSystem::factory()->create([
        'base_url' => 'https://ext.test',
        'provision_path' => '/api/v1/provision',
        'auth_type' => 'both',
        'api_key' => 'test-key',
        'signing_secret' => 'test-secret',
        'is_active' => true,
    ]);
}

function fakeProvisionOk(): void
{
    Http::fake(['ext.test/*' => Http::response([
        'external_user_id' => '9987',
        'login_url' => 'https://ext.test/go/abc',
        'magic_link' => 'https://ext.test/go/abc',
    ])]);
}

function manager(): ExternalProvisioningManager
{
    return app(ExternalProvisioningManager::class);
}

it('provisions an order that has a real email', function () {
    fakeProvisionOk();
    $system = extSystem();
    $order = ProductOrder::factory()->create([
        'guest_email' => 'buyer@example.com',
        'customer_phone' => '60123456789',
        'payment_status' => 'paid',
    ]);

    $request = manager()->provisionManually($order, $system);

    expect($request->status)->toBe('succeeded')
        ->and($request->external_user_id)->toBe('9987')
        ->and($request->login_url)->toBe('https://ext.test/go/abc')
        ->and($request->funnel_product_id)->toBeNull()
        ->and($request->product_order_id)->toBe($order->id)
        ->and($request->request_payload['customer']['email'])->toBe('buyer@example.com');
});

it('derives a synthetic email from the phone when the order has no email, on an unpaid order', function () {
    fakeProvisionOk();
    $system = extSystem();
    $order = ProductOrder::factory()->create([
        'guest_email' => null,
        'customer_phone' => '+60 12-345 6789',
        'payment_status' => 'pending', // any order is allowed
    ]);

    $request = manager()->provisionManually($order, $system);

    $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'kelasify.com';

    expect($request->status)->toBe('succeeded')
        ->and($request->request_payload['customer']['email'])->toBe("60123456789@noemail.{$host}")
        ->and($request->request_payload['customer']['phone'])->toBe('+60 12-345 6789');
});

it('passes the chosen plan through to the payload', function () {
    fakeProvisionOk();
    $system = extSystem();
    $order = ProductOrder::factory()->create(['guest_email' => 'buyer@example.com', 'payment_status' => 'paid']);

    $request = manager()->provisionManually($order, $system, 'gold');

    expect($request->request_payload['product']['plan'])->toBe('gold');
});

it('is idempotent for the same order and system', function () {
    fakeProvisionOk();
    $system = extSystem();
    $order = ProductOrder::factory()->create(['guest_email' => 'buyer@example.com', 'payment_status' => 'paid']);

    $first = manager()->provisionManually($order, $system);
    $second = manager()->provisionManually($order, $system);

    expect($second->id)->toBe($first->id)
        ->and(ExternalProvisioningRequest::count())->toBe(1);

    Http::assertSentCount(1); // the second call short-circuits (already succeeded)
});

it('marks the request failed when the order has no email and no phone', function () {
    Http::fake();
    $system = extSystem();
    $order = ProductOrder::factory()->create([
        'guest_email' => null,
        'customer_phone' => null,
        'customer_id' => null,
        'payment_status' => 'paid',
    ]);

    $request = manager()->provisionManually($order, $system);

    expect($request->status)->toBe('failed')
        ->and($request->last_error)->toContain('no email or phone');

    Http::assertNothingSent();
});

it('records a failure without throwing when the endpoint errors', function () {
    Http::fake(['ext.test/*' => Http::response(['message' => 'boom'], 500)]);
    $system = extSystem();
    $order = ProductOrder::factory()->create(['guest_email' => 'buyer@example.com', 'payment_status' => 'paid']);

    $request = manager()->provisionManually($order, $system);

    expect($request->status)->toBe('failed')
        ->and($request->last_error)->not->toBeNull();
});

it('provisions from the order detail page and dispatches a toast', function () {
    fakeProvisionOk();
    $admin = User::factory()->admin()->create();
    $system = extSystem();
    $order = ProductOrder::factory()->create(['guest_email' => 'buyer@example.com', 'payment_status' => 'paid']);

    $this->actingAs($admin);

    Volt::test('admin.orders.order-show', ['order' => $order])
        ->call('openProvisionModal', $order->id)
        ->assertSet('showProvisionModal', true)
        ->set('provisionSystemId', $system->id)
        ->call('provisionOrder')
        ->assertDispatched('order-provisioned')
        ->assertSet('showProvisionModal', false);

    expect(ExternalProvisioningRequest::where('product_order_id', $order->id)->where('status', 'succeeded')->exists())->toBeTrue();
});

it('loads the plan dropdown from the chosen system packages', function () {
    Http::fake(['ext.test/api/v1/packages' => Http::response(['packages' => [
        ['slug' => 'gold', 'name' => 'Gold'],
        ['slug' => 'silver', 'name' => 'Silver'],
    ]])]);
    $admin = User::factory()->admin()->create();
    $system = extSystem();
    $order = ProductOrder::factory()->create(['guest_email' => 'buyer@example.com', 'payment_status' => 'paid']);

    $this->actingAs($admin);

    Volt::test('admin.orders.order-show', ['order' => $order])
        ->call('openProvisionModal', $order->id)
        ->assertSet('provisionSystemId', $system->id) // single system auto-selected
        ->assertSet('provisionPlans', ['gold' => 'Gold', 'silver' => 'Silver']);
});

it('falls back to an empty plan list when the system has no packages endpoint', function () {
    Http::fake(['ext.test/api/v1/packages' => Http::response(['message' => 'Not found'], 404)]);
    $admin = User::factory()->admin()->create();
    extSystem();
    $order = ProductOrder::factory()->create(['guest_email' => 'buyer@example.com', 'payment_status' => 'paid']);

    $this->actingAs($admin);

    Volt::test('admin.orders.order-show', ['order' => $order])
        ->call('openProvisionModal', $order->id)
        ->assertSet('provisionPlans', [])
        ->assertSet('provisionPlansLoaded', true);
});
