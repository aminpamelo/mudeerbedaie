<?php

declare(strict_types=1);

use App\Models\Funnel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lets admin and employees add and remove a funnel from the Fighter Library in Funnel Studio', function (string $role) {
    $funnel = Funnel::factory()->create(['available_to_fighters' => false]);
    $staff = User::factory()->create(['role' => $role]);

    $this->actingAs($staff)
        ->postJson("/api/v1/funnels/{$funnel->uuid}/fighter-availability")
        ->assertOk()
        ->assertJson(['available_to_fighters' => true]);
    expect($funnel->fresh()->available_to_fighters)->toBeTrue();

    $this->actingAs($staff)
        ->postJson("/api/v1/funnels/{$funnel->uuid}/fighter-availability")
        ->assertOk()
        ->assertJson(['available_to_fighters' => false]);
    expect($funnel->fresh()->available_to_fighters)->toBeFalse();
})->with(['admin', 'employee']);

it('does not let fighters put funnels in the Fighter Library', function () {
    $fighter = User::factory()->create(['role' => 'fighter']);
    $funnel = Funnel::factory()->create(['user_id' => $fighter->id]);

    $this->actingAs($fighter)
        ->postJson("/api/v1/funnels/{$funnel->uuid}/fighter-availability")
        ->assertForbidden();

    expect($funnel->fresh()->available_to_fighters)->toBeFalse();
});

it('exposes the Fighter Library flag in the Studio funnel list', function () {
    Funnel::factory()->create(['name' => 'Library Funnel', 'available_to_fighters' => true]);

    $this->actingAs(User::factory()->admin()->create())
        ->getJson('/api/v1/funnels')
        ->assertOk()
        ->assertJsonPath('data.0.available_to_fighters', true);
});
