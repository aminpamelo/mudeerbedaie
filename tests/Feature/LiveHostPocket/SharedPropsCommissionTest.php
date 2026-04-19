<?php

use App\Models\LiveHostCommissionProfile;
use App\Models\LiveHostPlatformCommissionRate;
use App\Models\Platform;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

it('live host sees commission props via shared auth.user.commission', function () {
    $host = User::factory()->create(['role' => 'live_host']);
    LiveHostCommissionProfile::factory()->for($host)->create([
        'per_live_rate_myr' => 30.00,
    ]);
    $platform = Platform::where('slug', 'tiktok-shop')->first()
        ?? Platform::factory()->create(['slug' => 'tiktok-shop', 'name' => 'TikTok Shop']);
    LiveHostPlatformCommissionRate::factory()->for($host)->create([
        'platform_id' => $platform->id,
        'commission_rate_percent' => 4.00,
    ]);

    $response = actingAs($host)->get('/live-host');

    $response->assertInertia(fn (Assert $page) => $page
        ->where('auth.user.commission.perLiveRateMyr', fn ($v) => (float) $v === 30.0)
        ->where('auth.user.commission.primaryPlatformRatePercent', fn ($v) => (float) $v === 4.0)
    );
});

it('live host without commission profile sees zero values', function () {
    $host = User::factory()->create(['role' => 'live_host']);
    // no profile, no platform rate

    $response = actingAs($host)->get('/live-host');

    $response->assertInertia(fn (Assert $page) => $page
        ->where('auth.user.commission.perLiveRateMyr', fn ($v) => (float) $v === 0.0)
        ->where('auth.user.commission.primaryPlatformRatePercent', fn ($v) => (float) $v === 0.0)
    );
});

it('non-live-host role does NOT get the commission key', function () {
    $pic = User::factory()->create(['role' => 'admin_livehost']);

    $response = actingAs($pic)->get('/livehost');

    $response->assertInertia(fn (Assert $page) => $page
        ->where('auth.user.commission', null)
    );
});
