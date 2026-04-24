<?php

use App\Models\LiveHostCommissionProfile;
use App\Models\LiveHostPlatformAccount;
use App\Models\LiveHostPlatformCommissionTier;
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

/**
 * Seed a 3-tier schedule (15-30k / 30-60k / 60k+) on a fresh host+platform
 * for tier-based tests. Returns [host, platform].
 *
 * @return array{0: User, 1: Platform}
 */
function seedThreeTierSchedule(): array
{
    $host = User::factory()->create(['role' => 'live_host']);
    LiveHostCommissionProfile::factory()->for($host)->create([
        'per_live_rate_myr' => 30.00,
    ]);
    $platform = Platform::factory()->create();

    $common = [
        'user_id' => $host->id,
        'platform_id' => $platform->id,
        'effective_from' => '2026-01-01',
        'effective_to' => null,
        'is_active' => true,
    ];

    LiveHostPlatformCommissionTier::factory()->create($common + [
        'tier_number' => 1, 'min_gmv_myr' => 15000, 'max_gmv_myr' => 30000,
        'internal_percent' => 5.00, 'l1_percent' => 1.00, 'l2_percent' => 2.00,
    ]);
    LiveHostPlatformCommissionTier::factory()->create($common + [
        'tier_number' => 2, 'min_gmv_myr' => 30000, 'max_gmv_myr' => 60000,
        'internal_percent' => 6.00, 'l1_percent' => 1.30, 'l2_percent' => 2.30,
    ]);
    LiveHostPlatformCommissionTier::factory()->create($common + [
        'tier_number' => 3, 'min_gmv_myr' => 60000, 'max_gmv_myr' => null,
        'internal_percent' => 7.00, 'l1_percent' => 1.50, 'l2_percent' => 2.50,
    ]);

    return [$host, $platform];
}

it('computes session gmv_commission from tier internal_percent when tier matches monthly gmv', function () {
    [$host, $platform] = seedThreeTierSchedule();

    $session = makeSession($host, $platform, [
        'gmv_amount' => 40000,
        'gmv_adjustment' => 0,
        'scheduled_start_at' => now()->parse('2026-04-10 10:00:00'),
        'actual_start_at' => now()->parse('2026-04-10 10:00:00'),
    ]);

    $result = app(CommissionCalculator::class)->forSessionInMonthlyContext(
        $session,
        40000.00,
        now()->parse('2026-04-10 10:00:00'),
    );

    expect($result['net_gmv'])->toEqual(40000.00);
    expect($result['platform_rate_percent'])->toEqual(6.00);
    expect($result['gmv_commission'])->toEqual(2400.00);
    expect($result['per_live_rate'])->toEqual(30.00);
    expect($result['session_total'])->toEqual(2430.00);
    expect($result['warnings'])->toBe([]);

    $tier = LiveHostPlatformCommissionTier::where('user_id', $host->id)
        ->where('tier_number', 2)
        ->firstOrFail();

    expect($result['rate_source'])->toMatchArray([
        'tier_id' => $tier->id,
        'tier_number' => 2,
        'internal_percent' => 6.00,
        'monthly_gmv_myr' => 40000.00,
    ]);
});

it('returns zero gmv_commission when monthly gmv is below tier 1 floor', function () {
    [$host, $platform] = seedThreeTierSchedule();

    $session = makeSession($host, $platform, [
        'gmv_amount' => 10000,
        'gmv_adjustment' => 0,
        'scheduled_start_at' => now()->parse('2026-04-10 10:00:00'),
        'actual_start_at' => now()->parse('2026-04-10 10:00:00'),
    ]);

    $result = app(CommissionCalculator::class)->forSessionInMonthlyContext(
        $session,
        10000.00,
        now()->parse('2026-04-10 10:00:00'),
    );

    expect($result['net_gmv'])->toEqual(10000.00);
    expect($result['platform_rate_percent'])->toEqual(0.00);
    expect($result['gmv_commission'])->toEqual(0.00);
    expect($result['per_live_rate'])->toEqual(30.00);
    expect($result['session_total'])->toEqual(30.00);

    expect($result['rate_source'])->toMatchArray([
        'tier_id' => null,
        'reason' => 'below_tier_1_floor',
        'monthly_gmv_myr' => 10000.00,
    ]);
});
