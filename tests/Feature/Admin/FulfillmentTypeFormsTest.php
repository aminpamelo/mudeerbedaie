<?php

use App\Models\ExternalSystem;
use App\Models\Package;
use App\Models\Product;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

it('renders the product create form with the fulfillment type field', function () {
    $this->actingAs($this->admin)
        ->get(route('products.create'))
        ->assertOk()
        ->assertSee('Fulfillment Type');
});

it('renders the product edit form with the fulfillment type field', function () {
    $product = Product::factory()->create();

    $this->actingAs($this->admin)
        ->get(route('products.edit', $product))
        ->assertOk()
        ->assertSee('Fulfillment Type');
});

it('renders the package create form with the fulfillment type field', function () {
    $this->actingAs($this->admin)
        ->get(route('packages.create'))
        ->assertOk()
        ->assertSee('Fulfillment Type');
});

it('renders the package edit form with the fulfillment type field', function () {
    $package = Package::factory()->create();

    $this->actingAs($this->admin)
        ->get(route('packages.edit', $package))
        ->assertOk()
        ->assertSee('Fulfillment Type');
});

it('saves an external-system product with its provisioning link', function () {
    $system = ExternalSystem::factory()->create();

    $this->actingAs($this->admin);

    Volt::test('admin.products.product-create')
        ->set('name', 'DaiePRO Access')
        ->set('slug', 'daiepro-access')
        ->set('sku', 'DAIEPRO-1')
        ->set('base_price', 79)
        ->set('cost_price', 10)
        ->set('type', 'simple')
        ->set('fulfillment_type', 'external_system')
        ->set('external_system_id', $system->id)
        ->set('external_plan', 'pro-monthly')
        ->call('save')
        ->assertHasNoErrors();

    $product = Product::where('sku', 'DAIEPRO-1')->firstOrFail();

    expect($product->fulfillment_type)->toBe('external_system')
        ->and($product->isExternalSystem())->toBeTrue()
        ->and($product->provisioningExternalSystemId())->toBe($system->id)
        ->and($product->provisioningPlan())->toBe('pro-monthly');
});

it('requires an external system when fulfillment type is external system', function () {
    $this->actingAs($this->admin);

    Volt::test('admin.products.product-create')
        ->set('name', 'No system')
        ->set('slug', 'no-system')
        ->set('sku', 'NO-SYS-1')
        ->set('base_price', 10)
        ->set('cost_price', 5)
        ->set('type', 'simple')
        ->set('fulfillment_type', 'external_system')
        ->set('external_system_id', '')
        ->call('save')
        ->assertHasErrors('external_system_id');
});

it('clears the provisioning link when switching a product back to physical', function () {
    $system = ExternalSystem::factory()->create();
    $product = Product::factory()->create([
        'fulfillment_type' => 'external_system',
        'metadata' => ['provisioning' => ['external_system_id' => $system->id, 'plan' => 'pro']],
    ]);

    $this->actingAs($this->admin);

    Volt::test('admin.products.product-edit', ['product' => $product])
        ->set('fulfillment_type', 'physical')
        ->call('save')
        ->assertHasNoErrors();

    $product->refresh();

    expect($product->fulfillment_type)->toBe('physical')
        ->and($product->provisioningSettings())->toBeNull();
});
