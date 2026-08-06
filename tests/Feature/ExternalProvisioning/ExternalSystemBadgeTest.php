<?php

use App\Models\ExternalSystem;
use App\Models\Package;
use App\Models\Product;
use App\Models\User;

function externalItemMetadata(ExternalSystem $system, ?string $plan = 'gold'): array
{
    return ['provisioning' => ['external_system_id' => $system->id, 'plan' => $plan]];
}

it('badges a product that provisions to an external system', function () {
    $admin = User::factory()->admin()->create();
    $system = ExternalSystem::factory()->create(['name' => 'Kelasify Ext Test']);
    Product::factory()->create([
        'name' => 'Digital Access Product',
        'fulfillment_type' => 'external_system',
        'metadata' => externalItemMetadata($system),
    ]);

    $this->actingAs($admin)
        ->get(route('products.index'))
        ->assertOk()
        ->assertSee('Kelasify Ext Test')
        ->assertSee('Provisions to');
});

it('does not badge a physical product', function () {
    $admin = User::factory()->admin()->create();
    Product::factory()->create([
        'name' => 'Physical Product',
        'fulfillment_type' => 'physical',
        'metadata' => null,
    ]);

    $this->actingAs($admin)
        ->get(route('products.index'))
        ->assertOk()
        ->assertSee('Physical Product')
        ->assertDontSee('Provisions to');
});

it('badges a package that provisions to an external system', function () {
    $admin = User::factory()->admin()->create();
    $system = ExternalSystem::factory()->create(['name' => 'Kelasify Ext Test']);
    Package::factory()->create([
        'name' => 'Premium Access Package',
        'fulfillment_type' => 'external_system',
        'metadata' => externalItemMetadata($system, null),
    ]);

    $this->actingAs($admin)
        ->get(route('packages.index'))
        ->assertOk()
        ->assertSee('Kelasify Ext Test')
        ->assertSee('Provisions to');
});

it('shows an amber warning when marked external-system but no system linked', function () {
    $admin = User::factory()->admin()->create();
    Product::factory()->create([
        'name' => 'Misconfigured Product',
        'fulfillment_type' => 'external_system',
        'metadata' => ['provisioning' => []], // marked, but no system id
    ]);

    $this->actingAs($admin)
        ->get(route('products.index'))
        ->assertOk()
        ->assertSee('no system linked');
});
