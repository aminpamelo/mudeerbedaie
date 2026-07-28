<?php

use App\Jobs\ExternalProvisioning\ProvisionExternalAccountJob;
use App\Models\ExternalProvisioningRequest;
use App\Models\ExternalSystem;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Livewire\Volt\Volt;

it('lists provisioned accounts for a system', function () {
    $admin = User::factory()->admin()->create();
    $system = ExternalSystem::factory()->create();
    ExternalProvisioningRequest::factory()->succeeded()->create([
        'external_system_id' => $system->id,
        'external_user_id' => '77',
        'request_payload' => ['customer' => ['email' => 'buyer@example.test'], 'product' => ['plan' => 'gold']],
    ]);

    $this->actingAs($admin)
        ->get(route('admin.external-systems.accounts', $system))
        ->assertOk()
        ->assertSee('buyer@example.test')
        ->assertSee('gold');
});

it('denies non-admins the accounts page', function () {
    $user = User::factory()->create();
    $system = ExternalSystem::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.external-systems.accounts', $system))
        ->assertForbidden();
});

it('shows an account detail page for a request belonging to the system', function () {
    $admin = User::factory()->admin()->create();
    $system = ExternalSystem::factory()->create();
    $request = ExternalProvisioningRequest::factory()->succeeded()->create([
        'external_system_id' => $system->id,
        'request_payload' => ['customer' => ['email' => 'buyer@example.test', 'name' => 'Buyer Test'], 'product' => ['plan' => 'gold']],
    ]);

    $this->actingAs($admin)
        ->get(route('admin.external-systems.account', [$system, $request]))
        ->assertOk()
        ->assertSee('buyer@example.test')
        ->assertSee('Buyer Test');
});

it('404s when the request does not belong to the system in the URL', function () {
    $admin = User::factory()->admin()->create();
    $systemA = ExternalSystem::factory()->create();
    $systemB = ExternalSystem::factory()->create();
    $request = ExternalProvisioningRequest::factory()->create(['external_system_id' => $systemB->id]);

    $this->actingAs($admin)
        ->get(route('admin.external-systems.account', [$systemA, $request]))
        ->assertNotFound();
});

it('retries a failed provisioning and re-queues the job', function () {
    Queue::fake();
    $admin = User::factory()->admin()->create();
    $system = ExternalSystem::factory()->create();
    $request = ExternalProvisioningRequest::factory()->create([
        'external_system_id' => $system->id,
        'status' => 'failed',
        'last_error' => 'boom',
    ]);

    $this->actingAs($admin);

    Volt::test('admin.external-system-accounts', ['externalSystem' => $system])
        ->call('retry', $request->id);

    $request->refresh();

    expect($request->status)->toBe('pending')
        ->and($request->last_error)->toBeNull();

    Queue::assertPushed(ProvisionExternalAccountJob::class, 1);
});
