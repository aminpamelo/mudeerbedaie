<?php

use App\Models\LiveHostPlatformCommissionTier;
use App\Models\Platform;
use App\Models\User;
use App\Services\LiveHost\CommissionTierResolver;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->platform = Platform::factory()->create();
    $this->asOf = Carbon::parse('2026-04-15');

    // Seed a 3-tier schedule: 15-30k / 30-60k / 60k+
    $common = [
        'user_id' => $this->user->id,
        'platform_id' => $this->platform->id,
        'effective_from' => '2026-01-01',
        'effective_to' => null,
        'is_active' => true,
    ];

    LiveHostPlatformCommissionTier::factory()->create($common + [
        'tier_number' => 1, 'min_gmv_myr' => 15000, 'max_gmv_myr' => 30000,
        'internal_percent' => 6.00, 'l1_percent' => 1.00, 'l2_percent' => 2.00,
    ]);
    LiveHostPlatformCommissionTier::factory()->create($common + [
        'tier_number' => 2, 'min_gmv_myr' => 30000, 'max_gmv_myr' => 60000,
        'internal_percent' => 6.00, 'l1_percent' => 1.30, 'l2_percent' => 2.30,
    ]);
    LiveHostPlatformCommissionTier::factory()->create($common + [
        'tier_number' => 3, 'min_gmv_myr' => 60000, 'max_gmv_myr' => null,
        'internal_percent' => 6.00, 'l1_percent' => 1.50, 'l2_percent' => 2.50,
    ]);
});

it('returns null when gmv is below tier 1 floor', function () {
    $resolver = app(CommissionTierResolver::class);

    $tier = $resolver->resolveTier($this->user, $this->platform, 8000, $this->asOf);

    expect($tier)->toBeNull();
});

it('resolves the tier at the lower boundary (inclusive)', function () {
    $resolver = app(CommissionTierResolver::class);

    $tier = $resolver->resolveTier($this->user, $this->platform, 30000, $this->asOf);

    expect($tier->tier_number)->toBe(2);
});

it('resolves the tier below the upper boundary (exclusive)', function () {
    $resolver = app(CommissionTierResolver::class);

    $tier = $resolver->resolveTier($this->user, $this->platform, 29999.99, $this->asOf);

    expect($tier->tier_number)->toBe(1);
});

it('resolves the open-ended top tier for very large gmv', function () {
    $resolver = app(CommissionTierResolver::class);

    $tier = $resolver->resolveTier($this->user, $this->platform, 500000, $this->asOf);

    expect($tier->tier_number)->toBe(3);
});

it('ignores tiers where is_active is false', function () {
    LiveHostPlatformCommissionTier::query()
        ->where('user_id', $this->user->id)
        ->update(['is_active' => false]);

    $resolver = app(CommissionTierResolver::class);
    $tier = $resolver->resolveTier($this->user, $this->platform, 50000, $this->asOf);

    expect($tier)->toBeNull();
});

it('ignores tiers whose effective_to is in the past', function () {
    LiveHostPlatformCommissionTier::query()
        ->where('user_id', $this->user->id)
        ->update(['effective_to' => '2026-03-31']);

    $resolver = app(CommissionTierResolver::class);
    $tier = $resolver->resolveTier($this->user, $this->platform, 50000, $this->asOf);

    expect($tier)->toBeNull();
});

it('ignores tiers belonging to a different host or platform', function () {
    $otherHost = User::factory()->create();
    $otherPlatform = Platform::factory()->create();

    // Competing tier: different host, same platform, overlapping GMV window.
    LiveHostPlatformCommissionTier::factory()->create([
        'user_id' => $otherHost->id,
        'platform_id' => $this->platform->id,
        'tier_number' => 2,
        'min_gmv_myr' => 30000,
        'max_gmv_myr' => 60000,
        'internal_percent' => 99.00,
        'l1_percent' => 1.30,
        'l2_percent' => 2.30,
        'effective_from' => '2026-01-01',
        'effective_to' => null,
        'is_active' => true,
    ]);

    // Competing tier: original host, different platform, overlapping GMV window.
    LiveHostPlatformCommissionTier::factory()->create([
        'user_id' => $this->user->id,
        'platform_id' => $otherPlatform->id,
        'tier_number' => 2,
        'min_gmv_myr' => 30000,
        'max_gmv_myr' => 60000,
        'internal_percent' => 77.00,
        'l1_percent' => 1.30,
        'l2_percent' => 2.30,
        'effective_from' => '2026-01-01',
        'effective_to' => null,
        'is_active' => true,
    ]);

    $resolver = app(CommissionTierResolver::class);
    $tier = $resolver->resolveTier($this->user, $this->platform, 45000, $this->asOf);

    expect($tier->user_id)->toBe($this->user->id);
    expect($tier->platform_id)->toBe($this->platform->id);
});
