<?php

use App\Models\ExternalSystem;
use App\Models\Funnel;
use App\Models\FunnelProduct;
use App\Models\FunnelStep;
use App\Models\Product;
use App\Models\User;

function provisioningFunnelSetup(): array
{
    $user = User::factory()->create();
    $funnel = Funnel::factory()->for($user)->create();
    $step = FunnelStep::factory()->create(['funnel_id' => $funnel->id]);
    $product = Product::factory()->create(['status' => 'active']);

    return [$user, $funnel, $step, $product];
}

it('persists provisioning settings when creating a funnel product', function () {
    [$user, $funnel, $step, $product] = provisioningFunnelSetup();
    $system = ExternalSystem::factory()->create();

    $response = $this->actingAs($user)->postJson("/api/v1/funnels/{$funnel->uuid}/steps/{$step->id}/products", [
        'product_id' => $product->id,
        'type' => 'main',
        'funnel_price' => 100,
        'settings' => [
            'provisioning' => [
                'enabled' => true,
                'external_system_id' => $system->id,
                'plan' => 'gold',
            ],
        ],
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.provisioning.enabled', true)
        ->assertJsonPath('data.provisioning.external_system_id', $system->id)
        ->assertJsonPath('data.provisioning.plan', 'gold');

    $funnelProduct = FunnelProduct::first();

    expect($funnelProduct->settings['provisioning'])->toMatchArray([
        'enabled' => true,
        'external_system_id' => $system->id,
        'plan' => 'gold',
    ]);
});

it('rejects a provisioning target that does not exist', function () {
    [$user, $funnel, $step, $product] = provisioningFunnelSetup();

    $response = $this->actingAs($user)->postJson("/api/v1/funnels/{$funnel->uuid}/steps/{$step->id}/products", [
        'product_id' => $product->id,
        'type' => 'main',
        'funnel_price' => 100,
        'settings' => [
            'provisioning' => ['enabled' => true, 'external_system_id' => 999999],
        ],
    ]);

    $response->assertStatus(422);
});

it('clears provisioning settings when disabled on update', function () {
    [$user, $funnel, $step, $product] = provisioningFunnelSetup();
    $system = ExternalSystem::factory()->create();

    $funnelProduct = FunnelProduct::create([
        'funnel_step_id' => $step->id,
        'product_id' => $product->id,
        'type' => 'main',
        'name' => 'Membership',
        'funnel_price' => 100,
        'sort_order' => 0,
        'settings' => ['provisioning' => ['enabled' => true, 'external_system_id' => $system->id, 'plan' => 'gold']],
    ]);

    $response = $this->actingAs($user)->putJson("/api/v1/funnels/{$funnel->uuid}/steps/{$step->id}/products/{$funnelProduct->id}", [
        'settings' => ['provisioning' => ['enabled' => false]],
    ]);

    $response->assertOk()->assertJsonPath('data.provisioning', null);

    expect($funnelProduct->fresh()->settings['provisioning'] ?? null)->toBeNull();
});

it('lists active external systems for the picker', function () {
    $user = User::factory()->admin()->create();
    ExternalSystem::factory()->create(['name' => 'Active One']);
    ExternalSystem::factory()->inactive()->create(['name' => 'Hidden One']);

    $response = $this->actingAs($user)->getJson('/api/v1/external-systems');

    $response->assertOk()
        ->assertJsonFragment(['name' => 'Active One'])
        ->assertJsonMissing(['name' => 'Hidden One']);
});
