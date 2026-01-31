<?php

use App\Models\Funnel;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('funnel defaults to shipping enabled', function () {
    $funnel = Funnel::factory()->create();

    expect($funnel->disable_shipping)->toBeFalse();
});

test('funnel can be created with shipping disabled', function () {
    $funnel = Funnel::factory()->shippingDisabled()->create();

    expect($funnel->disable_shipping)->toBeTrue();
});

test('disable_shipping can be toggled via API', function () {
    $user = User::factory()->create();
    $funnel = Funnel::factory()->for($user)->create();

    $response = $this->actingAs($user)->putJson("/api/v1/funnels/{$funnel->uuid}", [
        'disable_shipping' => true,
    ]);

    $response->assertSuccessful();

    $funnel->refresh();
    expect($funnel->disable_shipping)->toBeTrue();
});

test('disable_shipping can be set back to false via API', function () {
    $user = User::factory()->create();
    $funnel = Funnel::factory()->for($user)->shippingDisabled()->create();

    $response = $this->actingAs($user)->putJson("/api/v1/funnels/{$funnel->uuid}", [
        'disable_shipping' => false,
    ]);

    $response->assertSuccessful();

    $funnel->refresh();
    expect($funnel->disable_shipping)->toBeFalse();
});

test('funnel API response includes disable_shipping', function () {
    $user = User::factory()->create();
    $funnel = Funnel::factory()->for($user)->create();

    $response = $this->actingAs($user)->getJson("/api/v1/funnels/{$funnel->uuid}");

    $response->assertSuccessful();
    $response->assertJsonPath('data.disable_shipping', false);
});

test('funnel API response shows disable_shipping as true when enabled', function () {
    $user = User::factory()->create();
    $funnel = Funnel::factory()->for($user)->shippingDisabled()->create();

    $response = $this->actingAs($user)->getJson("/api/v1/funnels/{$funnel->uuid}");

    $response->assertSuccessful();
    $response->assertJsonPath('data.disable_shipping', true);
});
