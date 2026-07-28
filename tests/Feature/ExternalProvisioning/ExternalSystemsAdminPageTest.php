<?php

use App\Models\ExternalProvisioningRequest;
use App\Models\ExternalSystem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Volt;

it('denies access to non-admins at the route', function () {
    $user = User::factory()->create(); // default student role

    $this->actingAs($user)->get('/admin/external-systems')->assertForbidden();
});

it('renders the external systems page for an admin', function () {
    $admin = User::factory()->admin()->create();
    ExternalSystem::factory()->create(['name' => 'Membership Portal']);

    $this->actingAs($admin)
        ->get('/admin/external-systems')
        ->assertOk()
        ->assertSee('Membership Portal');
});

it('creates an external system and stores secrets encrypted at rest', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    Volt::test('admin.external-systems')
        ->set('name', 'Membership Portal')
        ->set('base_url', 'https://portal.example.com')
        ->set('auth_type', 'both')
        ->set('api_key', 'super-secret-key')
        ->set('signing_secret', 'super-secret-hmac')
        ->call('save')
        ->assertHasNoErrors();

    $system = ExternalSystem::first();

    expect($system)->not->toBeNull()
        ->and($system->slug)->toBe('membership-portal')
        ->and($system->api_key)->toBe('super-secret-key'); // decrypted via cast

    $rawApiKey = DB::table('external_systems')->where('id', $system->id)->value('api_key');
    expect($rawApiKey)->not->toBe('super-secret-key'); // ciphertext at rest
});

it('keeps the stored secret when the field is left blank on edit', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $system = ExternalSystem::factory()->create(['api_key' => 'original-key']);

    Volt::test('admin.external-systems')
        ->call('openEditModal', $system->id)
        ->set('name', 'Renamed Portal')
        ->set('api_key', '') // untouched
        ->call('save')
        ->assertHasNoErrors();

    $system->refresh();

    expect($system->api_key)->toBe('original-key')
        ->and($system->name)->toBe('Renamed Portal');
});

it('blocks deleting a system that has provisioning history', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $system = ExternalSystem::factory()->create();
    ExternalProvisioningRequest::factory()->create(['external_system_id' => $system->id]);

    Volt::test('admin.external-systems')->call('deleteSystem', $system->id);

    expect(ExternalSystem::find($system->id))->not->toBeNull();
});

it('generates a matching pair of secrets in the form', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $component = Volt::test('admin.external-systems')
        ->assertSet('api_key', '')
        ->call('generateSecrets');

    expect(strlen((string) $component->get('api_key')))->toBe(64)
        ->and(strlen((string) $component->get('signing_secret')))->toBe(64)
        ->and($component->get('api_key'))->not->toBe($component->get('signing_secret'));
});
