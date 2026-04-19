<?php

use App\Models\LiveHostCommissionProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->pic = User::factory()->create(['role' => 'admin_livehost']);
    $this->host = User::factory()->create(['role' => 'live_host']);
});

it('PIC can POST to create a commission profile and old one gets deactivated', function () {
    LiveHostCommissionProfile::factory()->for($this->host)->create([
        'base_salary_myr' => 1500,
        'per_live_rate_myr' => 20,
        'is_active' => true,
        'effective_from' => now()->subDay(),
    ]);

    actingAs($this->pic)
        ->post("/livehost/hosts/{$this->host->id}/commission-profile", [
            'base_salary_myr' => 2000,
            'per_live_rate_myr' => 30,
            'upline_user_id' => null,
            'override_rate_l1_percent' => 10,
            'override_rate_l2_percent' => 5,
            'notes' => 'New profile',
        ])
        ->assertRedirect();

    $active = LiveHostCommissionProfile::query()
        ->where('user_id', $this->host->id)
        ->where('is_active', true)
        ->get();

    expect($active)->toHaveCount(1);
    expect((float) $active->first()->base_salary_myr)->toBe(2000.0);
    expect((float) $active->first()->per_live_rate_myr)->toBe(30.0);
    expect($active->first()->notes)->toBe('New profile');

    $inactive = LiveHostCommissionProfile::query()
        ->where('user_id', $this->host->id)
        ->where('is_active', false)
        ->get();
    expect($inactive)->toHaveCount(1);
    expect($inactive->first()->effective_to)->not->toBeNull();
});

it('PIC can PUT to update and the prior row is deactivated with effective_to set', function () {
    $old = LiveHostCommissionProfile::factory()->for($this->host)->create([
        'base_salary_myr' => 1500,
        'per_live_rate_myr' => 20,
        'is_active' => true,
        'effective_from' => now()->subDay(),
    ]);

    actingAs($this->pic)
        ->put("/livehost/hosts/{$this->host->id}/commission-profile", [
            'base_salary_myr' => 2500,
            'per_live_rate_myr' => 40,
            'upline_user_id' => null,
            'override_rate_l1_percent' => 12,
            'override_rate_l2_percent' => 6,
            'notes' => 'Raise',
        ])
        ->assertRedirect();

    $old->refresh();
    expect($old->is_active)->toBeFalse();
    expect($old->effective_to)->not->toBeNull();

    $active = LiveHostCommissionProfile::query()
        ->where('user_id', $this->host->id)
        ->where('is_active', true)
        ->firstOrFail();
    expect((float) $active->base_salary_myr)->toBe(2500.0);
    expect((float) $active->per_live_rate_myr)->toBe(40.0);
});

it('rejects circular upline (host as their own upline) with 422', function () {
    actingAs($this->pic)
        ->put("/livehost/hosts/{$this->host->id}/commission-profile", [
            'base_salary_myr' => 2000,
            'per_live_rate_myr' => 30,
            'upline_user_id' => $this->host->id,
            'override_rate_l1_percent' => 10,
            'override_rate_l2_percent' => 5,
        ])
        ->assertSessionHasErrors('upline_user_id');

    expect(LiveHostCommissionProfile::where('user_id', $this->host->id)->count())->toBe(0);
});

it('live_host role cannot create a profile (403)', function () {
    $other = User::factory()->create(['role' => 'live_host']);

    actingAs($other)
        ->post("/livehost/hosts/{$this->host->id}/commission-profile", [
            'base_salary_myr' => 2000,
            'per_live_rate_myr' => 30,
            'upline_user_id' => null,
            'override_rate_l1_percent' => 10,
            'override_rate_l2_percent' => 5,
        ])
        ->assertForbidden();

    expect(LiveHostCommissionProfile::where('user_id', $this->host->id)->count())->toBe(0);
});

it('validation errors when required fields are missing (422)', function () {
    actingAs($this->pic)
        ->post("/livehost/hosts/{$this->host->id}/commission-profile", [])
        ->assertSessionHasErrors([
            'base_salary_myr',
            'per_live_rate_myr',
            'override_rate_l1_percent',
            'override_rate_l2_percent',
        ]);
});
