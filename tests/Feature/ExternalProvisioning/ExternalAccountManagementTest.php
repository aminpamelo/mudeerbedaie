<?php

use App\Models\ExternalProvisioningRequest;
use App\Models\ExternalSystem;
use App\Models\User;
use App\Services\ExternalProvisioning\ExternalSystemClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;

function managedSystem(): ExternalSystem
{
    return ExternalSystem::factory()->create([
        'base_url' => 'https://ext.test',
        'provision_path' => '/api/v1/provision',
        'auth_type' => 'both',
        'api_key' => 'test-key',
        'signing_secret' => 'test-secret',
    ]);
}

it('signs management calls and targets the derived account paths', function () {
    $system = managedSystem();
    Http::fake(['ext.test/*' => Http::response(['status' => 'active'])]);

    app(ExternalSystemClient::class)->accountStatus($system, 'buyer@ext.test');

    Http::assertSent(function (Request $r) {
        return $r->url() === 'https://ext.test/api/v1/accounts/status'
            && $r->hasHeader('Authorization', 'Bearer test-key')
            && $r->header('X-Signature')[0] === hash_hmac('sha256', $r->body(), 'test-secret')
            && json_decode($r->body(), true) === ['email' => 'buyer@ext.test'];
    });
});

it('loads live status and available plans on the detail page', function () {
    $admin = User::factory()->admin()->create();
    $system = managedSystem();
    $request = ExternalProvisioningRequest::factory()->succeeded()->create([
        'external_system_id' => $system->id,
        'request_payload' => ['customer' => ['email' => 'buyer@ext.test'], 'product' => ['plan' => 'gold']],
    ]);

    Http::fake([
        'ext.test/api/v1/accounts/status' => Http::response(['status' => 'active', 'plan' => ['slug' => 'gold', 'name' => 'Gold'], 'active_until' => '2026-12-01T00:00:00+00:00']),
        'ext.test/api/v1/packages' => Http::response(['packages' => [['slug' => 'gold', 'name' => 'Gold'], ['slug' => 'silver', 'name' => 'Silver']]]),
    ]);

    $this->actingAs($admin);

    $component = Volt::test('admin.external-system-account', ['externalSystem' => $system, 'provisioningRequest' => $request])
        ->call('loadStatus')
        ->assertSet('statusError', null);

    expect($component->get('liveStatus')['status'])->toBe('active')
        ->and($component->get('availablePlans'))->toHaveCount(2)
        ->and($component->get('selectedPlan'))->toBe('gold');
});

it('changes a plan through the detail page', function () {
    $admin = User::factory()->admin()->create();
    $system = managedSystem();
    $request = ExternalProvisioningRequest::factory()->succeeded()->create([
        'external_system_id' => $system->id,
        'request_payload' => ['customer' => ['email' => 'buyer@ext.test'], 'product' => ['plan' => 'gold'], 'order_ref' => 'ORD-1'],
    ]);

    Http::fake([
        'ext.test/api/v1/accounts/change-plan' => Http::response(['result' => 'changed', 'status' => 'active', 'plan' => ['slug' => 'silver', 'name' => 'Silver']]),
        'ext.test/api/v1/accounts/status' => Http::response(['status' => 'active', 'plan' => ['slug' => 'silver', 'name' => 'Silver']]),
        'ext.test/api/v1/packages' => Http::response(['packages' => [['slug' => 'silver', 'name' => 'Silver']]]),
    ]);

    $this->actingAs($admin);

    Volt::test('admin.external-system-account', ['externalSystem' => $system, 'provisioningRequest' => $request])
        ->set('selectedPlan', 'silver')
        ->call('changePlan');

    Http::assertSent(function (Request $r): bool {
        return str_contains($r->url(), '/accounts/change-plan')
            && json_decode($r->body(), true)['plan'] === 'silver';
    });
});

it('revokes access through the detail page', function () {
    $admin = User::factory()->admin()->create();
    $system = managedSystem();
    $request = ExternalProvisioningRequest::factory()->succeeded()->create([
        'external_system_id' => $system->id,
        'request_payload' => ['customer' => ['email' => 'buyer@ext.test'], 'product' => ['plan' => 'gold']],
    ]);

    Http::fake([
        'ext.test/api/v1/accounts/revoke' => Http::response(['result' => 'revoked', 'status' => 'inactive']),
        'ext.test/api/v1/accounts/status' => Http::response(['status' => 'inactive', 'plan' => null]),
        'ext.test/api/v1/packages' => Http::response(['packages' => []]),
    ]);

    $this->actingAs($admin);

    Volt::test('admin.external-system-account', ['externalSystem' => $system, 'provisioningRequest' => $request])
        ->call('revoke');

    Http::assertSent(fn (Request $r): bool => str_contains($r->url(), '/accounts/revoke'));
});
