<?php

use App\Models\LiveHostPlatformAccount;
use App\Models\LiveSession;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Mirror of makeSession() fixture but with verification_status pending so each
 * test can flip it to 'verified' to exercise the observer.
 *
 * @param  array<string, mixed>  $overrides
 */
function makeObserverSession(User $host, Platform $platform, array $overrides = []): LiveSession
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
        'verification_status' => 'pending',
        'gmv_locked_at' => null,
        'commission_snapshot_json' => null,
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
    $this->tiktok = Platform::where('slug', 'tiktok-shop')->firstOrFail();
});

it('flipping verification_status from pending to verified triggers snapshot and gmv_locked_at', function () {
    $session = makeObserverSession($this->ahmad, $this->tiktok, [
        'gmv_amount' => 1000,
    ]);

    $session->verification_status = 'verified';
    $session->save();

    $session->refresh();

    expect($session->gmv_locked_at)->not->toBeNull();
    expect($session->commission_snapshot_json)->toBeArray();

    $snapshot = $session->commission_snapshot_json;
    expect($snapshot)->toHaveKey('net_gmv');
    expect($snapshot)->toHaveKey('platform_rate_percent');
    expect($snapshot)->toHaveKey('gmv_commission');
    expect($snapshot)->toHaveKey('per_live_rate');
    expect($snapshot)->toHaveKey('session_total');
    expect($snapshot)->toHaveKey('warnings');
    expect($snapshot)->toHaveKey('snapshotted_at');
    expect($snapshot)->toHaveKey('snapshotted_by_user_id');
    expect($snapshot)->toHaveKey('rate_source');
});

it('already-verified session with gmv_locked_at set remains untouched on next save (idempotent)', function () {
    $session = makeObserverSession($this->ahmad, $this->tiktok, [
        'gmv_amount' => 1000,
    ]);

    // First verify
    $session->verification_status = 'verified';
    $session->save();
    $session->refresh();

    $originalLockedAt = $session->gmv_locked_at->toIso8601String();
    $originalSnapshot = $session->commission_snapshot_json;

    // Travel forward and save again — possibly touching other fields —
    // but since gmv_locked_at is set, observer should no-op.
    \Carbon\Carbon::setTestNow(now()->addMinutes(5));

    $session->verification_notes = 'Added after-the-fact notes';
    $session->save();
    $session->refresh();

    expect($session->gmv_locked_at->toIso8601String())->toBe($originalLockedAt);
    expect($session->commission_snapshot_json)->toBe($originalSnapshot);

    \Carbon\Carbon::setTestNow();
});

it('saving with verification_status unchanged does not trigger snapshot', function () {
    $session = makeObserverSession($this->ahmad, $this->tiktok, [
        'gmv_amount' => 1000,
    ]);

    // status stays pending — touch notes only
    $session->verification_notes = 'just a note';
    $session->save();

    $session->refresh();

    expect($session->gmv_locked_at)->toBeNull();
    expect($session->commission_snapshot_json)->toBeNull();
});

it('saving with verification_status=rejected does not trigger snapshot', function () {
    $session = makeObserverSession($this->ahmad, $this->tiktok, [
        'gmv_amount' => 1000,
    ]);

    $session->verification_status = 'rejected';
    $session->save();

    $session->refresh();

    expect($session->gmv_locked_at)->toBeNull();
    expect($session->commission_snapshot_json)->toBeNull();
});
