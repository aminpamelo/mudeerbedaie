<?php

use App\Models\LiveHostPlatformCommissionRate;
use App\Models\LiveHostPlatformCommissionTier;
use App\Models\Platform;
use App\Models\User;
use App\Services\LiveHost\BackfillTiersFromFlatRates;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates one open-ended zero-override Tier 1 row per active (host, platform) rate', function () {
    $ahmad = User::factory()->create(['role' => 'live_host']);
    $sarah = User::factory()->create(['role' => 'live_host']);
    $amin = User::factory()->create(['role' => 'live_host']);

    $tiktok = Platform::factory()->create(['slug' => 'tiktok-shop']);
    $shopee = Platform::factory()->create(['slug' => 'shopee']);
    $lazada = Platform::factory()->create(['slug' => 'lazada']);

    LiveHostPlatformCommissionRate::factory()->create([
        'user_id' => $ahmad->id,
        'platform_id' => $tiktok->id,
        'commission_rate_percent' => 4.00,
        'effective_from' => now()->subMonths(3),
        'is_active' => true,
    ]);

    LiveHostPlatformCommissionRate::factory()->create([
        'user_id' => $sarah->id,
        'platform_id' => $shopee->id,
        'commission_rate_percent' => 5.00,
        'effective_from' => now()->subMonths(2),
        'is_active' => true,
    ]);

    LiveHostPlatformCommissionRate::factory()->create([
        'user_id' => $amin->id,
        'platform_id' => $lazada->id,
        'commission_rate_percent' => 6.00,
        'effective_from' => now()->subMonth(),
        'is_active' => true,
    ]);

    $created = app(BackfillTiersFromFlatRates::class)->run();

    expect($created)->toBe(3);
    expect(LiveHostPlatformCommissionTier::count())->toBe(3);

    foreach ([
        [$ahmad->id, $tiktok->id, 4.00],
        [$sarah->id, $shopee->id, 5.00],
        [$amin->id, $lazada->id, 6.00],
    ] as [$userId, $platformId, $expectedPercent]) {
        $tier = LiveHostPlatformCommissionTier::query()
            ->where('user_id', $userId)
            ->where('platform_id', $platformId)
            ->where('tier_number', 1)
            ->firstOrFail();

        expect((float) $tier->internal_percent)->toEqual($expectedPercent);
        expect((float) $tier->l1_percent)->toEqual(0.00);
        expect((float) $tier->l2_percent)->toEqual(0.00);
        expect((float) $tier->min_gmv_myr)->toEqual(0.00);
        expect($tier->max_gmv_myr)->toBeNull();
    }
});

it('is idempotent — second run creates no new tier rows', function () {
    $host = User::factory()->create(['role' => 'live_host']);
    $platform = Platform::factory()->create();

    LiveHostPlatformCommissionRate::factory()->create([
        'user_id' => $host->id,
        'platform_id' => $platform->id,
        'commission_rate_percent' => 4.50,
        'effective_from' => now()->subMonth(),
        'is_active' => true,
    ]);

    $service = app(BackfillTiersFromFlatRates::class);

    $firstRun = $service->run();
    $rowsAfterFirst = LiveHostPlatformCommissionTier::count();

    $secondRun = $service->run();
    $rowsAfterSecond = LiveHostPlatformCommissionTier::count();

    expect($firstRun)->toBe(1);
    expect($rowsAfterFirst)->toBe(1);
    expect($secondRun)->toBe(0);
    expect($rowsAfterSecond)->toBe(1);
});

it('copies effective_from, effective_to, and is_active from the source rate row', function () {
    $host = User::factory()->create(['role' => 'live_host']);
    $platform = Platform::factory()->create();

    $effectiveFrom = now()->subMonths(3);
    $effectiveTo = now()->subMonth();

    LiveHostPlatformCommissionRate::factory()->create([
        'user_id' => $host->id,
        'platform_id' => $platform->id,
        'commission_rate_percent' => 7.00,
        'effective_from' => $effectiveFrom,
        'effective_to' => $effectiveTo,
        'is_active' => true,
    ]);

    app(BackfillTiersFromFlatRates::class)->run();

    $tier = LiveHostPlatformCommissionTier::query()
        ->where('user_id', $host->id)
        ->where('platform_id', $platform->id)
        ->firstOrFail();

    expect($tier->effective_from->toDateString())->toBe($effectiveFrom->toDateString());
    expect($tier->effective_to->toDateString())->toBe($effectiveTo->toDateString());
    expect($tier->is_active)->toBeTrue();
});

it('skips inactive rate rows', function () {
    $host = User::factory()->create(['role' => 'live_host']);
    $platform = Platform::factory()->create();

    LiveHostPlatformCommissionRate::factory()->create([
        'user_id' => $host->id,
        'platform_id' => $platform->id,
        'commission_rate_percent' => 3.00,
        'effective_from' => now()->subMonths(6),
        'effective_to' => now()->subMonths(3),
        'is_active' => false,
    ]);

    $created = app(BackfillTiersFromFlatRates::class)->run();

    expect($created)->toBe(0);
    expect(LiveHostPlatformCommissionTier::count())->toBe(0);
});
