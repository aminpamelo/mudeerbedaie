<?php

use App\Models\LiveHostCommissionProfile;
use App\Models\LiveHostPlatformAccount;
use App\Models\LiveSession;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\User;
use App\Services\LiveHost\CommissionCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Build a verified live session for the given host on the given platform.
 *
 * Creates a PlatformAccount tied to the platform, attaches the host via the
 * live_host_platform_account pivot (is_primary=true), and creates the
 * LiveSession with sensible defaults (status=ended, verification_status=verified)
 * that can be overridden via $overrides.
 *
 * @param  array<string, mixed>  $overrides
 */
function makeSession(User $host, Platform $platform, array $overrides = []): LiveSession
{
    $account = PlatformAccount::factory()->create([
        'platform_id' => $platform->id,
        'user_id' => $host->id,
    ]);

    $pivot = LiveHostPlatformAccount::create([
        'user_id' => $host->id,
        'platform_account_id' => $account->id,
        'is_primary' => true,
    ]);

    return LiveSession::factory()->create(array_merge([
        'platform_account_id' => $account->id,
        'live_host_platform_account_id' => $pivot->id,
        'live_host_id' => $host->id,
        'status' => 'ended',
        'verification_status' => 'verified',
        'scheduled_start_at' => now()->subHour(),
        'actual_start_at' => now()->subHour(),
        'actual_end_at' => now()->subMinutes(10),
        'duration_minutes' => 50,
        'gmv_adjustment' => 0,
    ], $overrides));
}

beforeEach(function () {
    $this->seed(\Database\Seeders\LiveHostCommissionSeeder::class);
    $this->ahmad = User::where('email', 'ahmad@livehost.com')->first();
    $this->sarah = User::where('email', 'sarah@livehost.com')->first();
    $this->amin = User::where('email', 'amin@livehost.com')->first();
    $this->tiktok = Platform::where('slug', 'tiktok-shop')->firstOrFail();
});

it('computes full commission for a verified session with positive GMV', function () {
    $session = makeSession($this->ahmad, $this->tiktok, [
        'gmv_amount' => 1500,
        'gmv_adjustment' => -200,
    ]);

    $result = app(CommissionCalculator::class)->forSession($session);

    expect($result['net_gmv'])->toEqual(1300.00);
    expect($result['platform_rate_percent'])->toEqual(4.00);
    expect($result['gmv_commission'])->toEqual(52.00);
    expect($result['per_live_rate'])->toEqual(30.00);
    expect($result['session_total'])->toEqual(82.00);
    expect($result['warnings'])->toBe([]);
});

it('missed sessions earn zero per-live rate and zero commission', function () {
    $session = makeSession($this->ahmad, $this->tiktok, [
        'gmv_amount' => 0,
        'status' => 'missed',
    ]);

    $result = app(CommissionCalculator::class)->forSession($session);

    expect($result['net_gmv'])->toEqual(0.00);
    expect($result['gmv_commission'])->toEqual(0.00);
    expect($result['per_live_rate'])->toEqual(0.00);
    expect($result['session_total'])->toEqual(0.00);
});

it('emits missing_platform_rate warning when host has no rate for the session platform', function () {
    $host = User::factory()->create(['role' => 'live_host']);
    LiveHostCommissionProfile::factory()->for($host)->create([
        'per_live_rate_myr' => 25.00,
    ]);

    $session = makeSession($host, $this->tiktok, ['gmv_amount' => 500]);

    $result = app(CommissionCalculator::class)->forSession($session);

    expect($result['platform_rate_percent'])->toEqual(0.00);
    expect($result['gmv_commission'])->toEqual(0.00);
    expect($result['per_live_rate'])->toEqual(25.00);
    expect($result['warnings'])->toContain('missing_platform_rate');
});

it('handles zero GMV session with no warning', function () {
    $session = makeSession($this->sarah, $this->tiktok, ['gmv_amount' => 0]);

    $result = app(CommissionCalculator::class)->forSession($session);

    expect($result['net_gmv'])->toEqual(0.00);
    expect($result['gmv_commission'])->toEqual(0.00);
    expect($result['per_live_rate'])->toEqual(25.00);
    expect($result['session_total'])->toEqual(25.00);
    expect($result['warnings'])->toBe([]);
});

it('handles null gmv_amount as zero GMV', function () {
    $session = makeSession($this->amin, $this->tiktok, ['gmv_amount' => null]);

    $result = app(CommissionCalculator::class)->forSession($session);

    expect($result['net_gmv'])->toEqual(0.00);
    expect($result['gmv_commission'])->toEqual(0.00);
    expect($result['per_live_rate'])->toEqual(50.00);
});

it('rounds decimals to 2 places', function () {
    $session = makeSession($this->ahmad, $this->tiktok, [
        'gmv_amount' => 33.33,
        'gmv_adjustment' => 0,
    ]);

    $result = app(CommissionCalculator::class)->forSession($session);

    expect($result['gmv_commission'])->toEqual(1.33);
    expect($result['session_total'])->toEqual(31.33);
});

it('handles host with no commission profile — per_live_rate=0 and gmv_commission=0 with warning', function () {
    $orphan = User::factory()->create(['role' => 'live_host']);

    $session = makeSession($orphan, $this->tiktok, ['gmv_amount' => 1000]);

    $result = app(CommissionCalculator::class)->forSession($session);

    expect($result['gmv_commission'])->toEqual(0.00);
    expect($result['per_live_rate'])->toEqual(0.00);
    expect($result['warnings'])->toContain('missing_platform_rate');
});
