<?php

use App\Models\LiveHostCommissionProfile;
use App\Models\LiveHostPlatformCommissionRate;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('LiveHostCommissionProfile factory produces a valid model', function () {
    $profile = LiveHostCommissionProfile::factory()->create();
    expect($profile)->toBeInstanceOf(LiveHostCommissionProfile::class);
    expect($profile->user_id)->not->toBeNull();
    expect($profile->is_active)->toBeTrue();
    // factory defaults one of the business-realistic salaries
    expect((float) $profile->base_salary_myr)->toBeGreaterThanOrEqual(0);
});

it('LiveHostCommissionProfile factory withUpline state sets upline_user_id', function () {
    $upline = User::factory()->create();
    $profile = LiveHostCommissionProfile::factory()->withUpline($upline)->create();
    expect($profile->upline_user_id)->toBe($upline->id);
});

it('LiveHostPlatformCommissionRate factory produces a valid model', function () {
    $rate = LiveHostPlatformCommissionRate::factory()->create();
    expect($rate)->toBeInstanceOf(LiveHostPlatformCommissionRate::class);
    expect($rate->user_id)->not->toBeNull();
    expect($rate->platform_id)->not->toBeNull();
    expect((float) $rate->commission_rate_percent)->toBeGreaterThanOrEqual(0);
});

it('LiveHostCommissionSeeder creates Ahmad/Sarah/Amin with correct upline chain and profile values', function () {
    $this->seed(\Database\Seeders\LiveHostCommissionSeeder::class);

    $ahmad = User::where('email', 'ahmad@livehost.com')->first();
    $sarah = User::where('email', 'sarah@livehost.com')->first();
    $amin = User::where('email', 'amin@livehost.com')->first();

    expect($ahmad)->not->toBeNull();
    expect($sarah)->not->toBeNull();
    expect($amin)->not->toBeNull();

    // Profile values from design doc §5.3
    expect((float) $ahmad->commissionProfile->base_salary_myr)->toEqual(2000.00);
    expect((float) $ahmad->commissionProfile->per_live_rate_myr)->toEqual(30.00);
    expect((float) $ahmad->commissionProfile->override_rate_l1_percent)->toEqual(10.00);
    expect((float) $ahmad->commissionProfile->override_rate_l2_percent)->toEqual(5.00);
    expect($ahmad->commissionProfile->upline_user_id)->toBeNull();

    expect((float) $sarah->commissionProfile->base_salary_myr)->toEqual(1800.00);
    expect((float) $sarah->commissionProfile->per_live_rate_myr)->toEqual(25.00);
    expect($sarah->commissionProfile->upline_user_id)->toBe($ahmad->id);

    expect((float) $amin->commissionProfile->base_salary_myr)->toEqual(0.00);
    expect((float) $amin->commissionProfile->per_live_rate_myr)->toEqual(50.00);
    expect($amin->commissionProfile->upline_user_id)->toBe($sarah->id);

    // TikTok platform rates
    $tiktok = Platform::where('slug', 'tiktok')->first()
        ?? Platform::where('name', 'like', '%TikTok%')->first();
    expect($tiktok)->not->toBeNull();

    expect((float) $ahmad->platformCommissionRates()->where('platform_id', $tiktok->id)->value('commission_rate_percent'))->toEqual(4.00);
    expect((float) $sarah->platformCommissionRates()->where('platform_id', $tiktok->id)->value('commission_rate_percent'))->toEqual(5.00);
    expect((float) $amin->platformCommissionRates()->where('platform_id', $tiktok->id)->value('commission_rate_percent'))->toEqual(6.00);
});

it('LiveHostCommissionSeeder is idempotent (running twice does not duplicate users)', function () {
    $this->seed(\Database\Seeders\LiveHostCommissionSeeder::class);
    $this->seed(\Database\Seeders\LiveHostCommissionSeeder::class);
    expect(User::where('email', 'ahmad@livehost.com')->count())->toBe(1);
});
