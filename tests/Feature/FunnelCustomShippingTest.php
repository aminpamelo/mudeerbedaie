<?php

declare(strict_types=1);

use App\Models\Funnel;
use App\Models\FunnelStep;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

function customShippingFunnel(array $shippingSettings): FunnelStep
{
    $funnel = Funnel::factory()->create(['shipping_settings' => $shippingSettings]);

    return FunnelStep::create([
        'funnel_id' => $funnel->id,
        'name' => 'Checkout',
        'slug' => 'checkout',
        'type' => 'checkout',
        'sort_order' => 0,
        'is_active' => true,
        'settings' => [],
    ]);
}

$customSettings = [
    'enabled' => true,
    'mode' => 'custom',
    'semenanjung_cost' => 8,
    'sabah_sarawak_cost' => 15,
    'custom_costs' => [
        'stripe' => ['semenanjung' => 8, 'sabah_sarawak' => 15],
        'bayarcash_fpx' => ['semenanjung' => 8, 'sabah_sarawak' => 15],
        'cod' => ['semenanjung' => 12, 'sabah_sarawak' => 20],
    ],
];

/*
|--------------------------------------------------------------------------
| Checkout computation (custom mode: payment method x zone)
|--------------------------------------------------------------------------
*/

it('charges the COD Sabah/Sarawak rate in custom mode', function () use ($customSettings) {
    $step = customShippingFunnel($customSettings);

    $component = Volt::test('funnel.checkout-form', ['funnel' => $step->funnel, 'step' => $step])
        ->set('shippingZone', 'sabah_sarawak')
        ->set('paymentMethod', 'cod');

    expect($component->instance()->calculateShippingCost())->toBe(20.0);
});

it('charges the COD Semenanjung rate in custom mode', function () use ($customSettings) {
    $step = customShippingFunnel($customSettings);

    $component = Volt::test('funnel.checkout-form', ['funnel' => $step->funnel, 'step' => $step])
        ->set('shippingZone', 'semenanjung')
        ->set('paymentMethod', 'cod');

    expect($component->instance()->calculateShippingCost())->toBe(12.0);
});

it('maps the credit_card payment id to the stripe cost bucket', function () use ($customSettings) {
    $step = customShippingFunnel($customSettings);

    $component = Volt::test('funnel.checkout-form', ['funnel' => $step->funnel, 'step' => $step])
        ->set('shippingZone', 'sabah_sarawak')
        ->set('paymentMethod', 'credit_card');

    expect($component->instance()->calculateShippingCost())->toBe(15.0);
});

it('maps the fpx payment id to the bayarcash_fpx cost bucket', function () use ($customSettings) {
    $step = customShippingFunnel($customSettings);

    $component = Volt::test('funnel.checkout-form', ['funnel' => $step->funnel, 'step' => $step])
        ->set('shippingZone', 'semenanjung')
        ->set('paymentMethod', 'fpx');

    expect($component->instance()->calculateShippingCost())->toBe(8.0);
});

it('falls back to zero (not the general rate) for an unconfigured method in custom mode', function () {
    $step = customShippingFunnel([
        'enabled' => true,
        'mode' => 'custom',
        'semenanjung_cost' => 8,
        'sabah_sarawak_cost' => 15,
        'custom_costs' => [
            'cod' => ['semenanjung' => 12, 'sabah_sarawak' => 20],
        ],
    ]);

    $component = Volt::test('funnel.checkout-form', ['funnel' => $step->funnel, 'step' => $step])
        ->set('shippingZone', 'sabah_sarawak')
        ->set('paymentMethod', 'credit_card');

    expect($component->instance()->calculateShippingCost())->toBe(0.0);
});

it('still uses zone rates in general mode regardless of payment method', function () {
    $step = customShippingFunnel([
        'enabled' => true,
        'mode' => 'general',
        'semenanjung_cost' => 8,
        'sabah_sarawak_cost' => 15,
        'custom_costs' => [
            'cod' => ['semenanjung' => 99, 'sabah_sarawak' => 99],
        ],
    ]);

    $component = Volt::test('funnel.checkout-form', ['funnel' => $step->funnel, 'step' => $step])
        ->set('shippingZone', 'sabah_sarawak')
        ->set('paymentMethod', 'cod');

    expect($component->instance()->calculateShippingCost())->toBe(15.0);
});

it('charges no shipping when the feature is disabled even with custom costs set', function () use ($customSettings) {
    $step = customShippingFunnel(['enabled' => false] + $customSettings);

    $component = Volt::test('funnel.checkout-form', ['funnel' => $step->funnel, 'step' => $step])
        ->set('shippingZone', 'sabah_sarawak')
        ->set('paymentMethod', 'cod');

    expect($component->instance()->calculateShippingCost())->toBe(0.0);
});

/*
|--------------------------------------------------------------------------
| Persistence & validation (Save Settings)
|--------------------------------------------------------------------------
*/

it('persists custom shipping settings via the funnel API', function () use ($customSettings) {
    $user = User::factory()->create();
    $funnel = Funnel::factory()->for($user)->create();

    $this->actingAs($user)
        ->putJson("/api/v1/funnels/{$funnel->uuid}", ['shipping_settings' => $customSettings])
        ->assertSuccessful();

    $funnel->refresh();

    expect($funnel->shipping_settings['mode'])->toBe('custom')
        ->and($funnel->shipping_settings['custom_costs']['cod']['sabah_sarawak'])->toBe(20);
});

it('rejects an invalid shipping mode', function () {
    $user = User::factory()->create();
    $funnel = Funnel::factory()->for($user)->create();

    $this->actingAs($user)
        ->putJson("/api/v1/funnels/{$funnel->uuid}", [
            'shipping_settings' => ['enabled' => true, 'mode' => 'bogus'],
        ])
        ->assertJsonValidationErrors('shipping_settings.mode');
});

it('rejects a negative custom shipping cost', function () {
    $user = User::factory()->create();
    $funnel = Funnel::factory()->for($user)->create();

    $this->actingAs($user)
        ->putJson("/api/v1/funnels/{$funnel->uuid}", [
            'shipping_settings' => [
                'enabled' => true,
                'mode' => 'custom',
                'custom_costs' => ['cod' => ['semenanjung' => -5]],
            ],
        ])
        ->assertJsonValidationErrors('shipping_settings.custom_costs.cod.semenanjung');
});
