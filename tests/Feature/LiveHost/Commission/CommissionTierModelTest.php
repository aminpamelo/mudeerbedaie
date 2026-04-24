<?php

use App\Models\LiveHostPlatformCommissionTier;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('belongs to a host user and a platform', function () {
    $tier = LiveHostPlatformCommissionTier::factory()->create();

    expect($tier->user)->toBeInstanceOf(User::class);
    expect($tier->platform)->toBeInstanceOf(Platform::class);
});

it('scopes to active tiers', function () {
    LiveHostPlatformCommissionTier::factory()->create(['is_active' => true]);
    LiveHostPlatformCommissionTier::factory()->create(['is_active' => false]);

    expect(LiveHostPlatformCommissionTier::active()->count())->toBe(1);
});
