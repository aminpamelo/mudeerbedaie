<?php

use App\Models\LiveHostPlatformCommissionTier;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->pic = User::factory()->create(['role' => 'admin_livehost']);
    $this->host = User::factory()->create(['role' => 'live_host']);
    $this->tiktok = Platform::factory()->create([
        'slug' => 'tiktok-shop',
        'name' => 'TikTok Shop',
        'is_active' => true,
    ]);
    $this->shopee = Platform::factory()->create([
        'slug' => 'shopee-live',
        'name' => 'Shopee Live',
        'is_active' => true,
    ]);
});

it('exposes commissionTiers grouped by platform and effective_from', function () {
    $effectiveFrom = now()->subDay()->toDateString();

    // TikTok: 3-tier schedule (inserted out of order to validate sorting)
    foreach ([3, 1, 2] as $tierNumber) {
        LiveHostPlatformCommissionTier::factory()->create([
            'user_id' => $this->host->id,
            'platform_id' => $this->tiktok->id,
            'tier_number' => $tierNumber,
            'min_gmv_myr' => $tierNumber * 1000,
            'max_gmv_myr' => $tierNumber * 1000 + 999,
            'internal_percent' => 5 + $tierNumber,
            'l1_percent' => 1,
            'l2_percent' => 2,
            'effective_from' => $effectiveFrom,
            'is_active' => true,
        ]);
    }

    // Shopee: 3-tier schedule
    foreach ([1, 2, 3] as $tierNumber) {
        LiveHostPlatformCommissionTier::factory()->create([
            'user_id' => $this->host->id,
            'platform_id' => $this->shopee->id,
            'tier_number' => $tierNumber,
            'min_gmv_myr' => $tierNumber * 500,
            'max_gmv_myr' => $tierNumber * 500 + 499,
            'internal_percent' => 4 + $tierNumber,
            'l1_percent' => 1,
            'l2_percent' => 2,
            'effective_from' => $effectiveFrom,
            'is_active' => true,
        ]);
    }

    // Inactive tier — must be excluded
    LiveHostPlatformCommissionTier::factory()->create([
        'user_id' => $this->host->id,
        'platform_id' => $this->tiktok->id,
        'tier_number' => 99,
        'min_gmv_myr' => 999999,
        'max_gmv_myr' => null,
        'internal_percent' => 0,
        'l1_percent' => 0,
        'l2_percent' => 0,
        'effective_from' => $effectiveFrom,
        'is_active' => false,
    ]);

    actingAs($this->pic)
        ->get("/livehost/hosts/{$this->host->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('hosts/Show', false)
            ->has('commissionTiers', 2)
            ->has('commissionTiers.0', fn (Assert $group) => $group
                ->where('platform_id', $this->tiktok->id)
                ->has('platform', fn (Assert $p) => $p
                    ->where('id', $this->tiktok->id)
                    ->where('slug', 'tiktok-shop')
                    ->etc())
                ->where('effective_from', $effectiveFrom)
                ->has('tiers', 3)
                ->where('tiers.0.tier_number', 1)
                ->where('tiers.1.tier_number', 2)
                ->where('tiers.2.tier_number', 3))
            ->has('commissionTiers.1', fn (Assert $group) => $group
                ->where('platform_id', $this->shopee->id)
                ->has('platform', fn (Assert $p) => $p
                    ->where('id', $this->shopee->id)
                    ->where('slug', 'shopee-live')
                    ->etc())
                ->where('effective_from', $effectiveFrom)
                ->has('tiers', 3)
                ->where('tiers.0.tier_number', 1)
                ->where('tiers.1.tier_number', 2)
                ->where('tiers.2.tier_number', 3))
        );
});

it('returns an empty commissionTiers array when host has no tier schedules', function () {
    actingAs($this->pic)
        ->get("/livehost/hosts/{$this->host->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('hosts/Show', false)
            ->has('commissionTiers', 0)
        );
});
