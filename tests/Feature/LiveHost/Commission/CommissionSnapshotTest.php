<?php

use App\Models\LiveHostPlatformAccount;
use App\Models\LiveHostPlatformCommissionRate;
use App\Models\LiveSession;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\User;
use App\Services\LiveHost\CommissionCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Mirror of makeSession() in CommissionCalculatorPerSessionTest so this file
 * can stand alone if run in isolation.
 *
 * @param  array<string, mixed>  $overrides
 */
function makeSnapshotSession(User $host, Platform $platform, array $overrides = []): LiveSession
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
    $this->tiktok = Platform::where('slug', 'tiktok-shop')->firstOrFail();
});

it('returns all keys from forSession plus the 3 metadata keys', function () {
    $session = makeSnapshotSession($this->ahmad, $this->tiktok, [
        'gmv_amount' => 1000,
    ]);

    $actor = User::factory()->create();

    $snapshot = app(CommissionCalculator::class)->snapshot($session, $actor);

    // forSession keys
    expect($snapshot)->toHaveKey('net_gmv');
    expect($snapshot)->toHaveKey('platform_rate_percent');
    expect($snapshot)->toHaveKey('gmv_commission');
    expect($snapshot)->toHaveKey('per_live_rate');
    expect($snapshot)->toHaveKey('session_total');
    expect($snapshot)->toHaveKey('warnings');

    // Snapshot metadata keys
    expect($snapshot)->toHaveKey('snapshotted_at');
    expect($snapshot)->toHaveKey('snapshotted_by_user_id');
    expect($snapshot)->toHaveKey('rate_source');

    // snapshotted_at is an ISO 8601 string for "now"
    expect($snapshot['snapshotted_at'])->toBeString();
    expect(\Carbon\Carbon::parse($snapshot['snapshotted_at'])->isToday())->toBeTrue();
});

it('snapshotted_by_user_id is null when actor is null', function () {
    $session = makeSnapshotSession($this->ahmad, $this->tiktok, [
        'gmv_amount' => 500,
    ]);

    $snapshot = app(CommissionCalculator::class)->snapshot($session, null);

    expect($snapshot['snapshotted_by_user_id'])->toBeNull();
});

it('snapshotted_by_user_id is the actor id when provided', function () {
    $session = makeSnapshotSession($this->ahmad, $this->tiktok, [
        'gmv_amount' => 500,
    ]);

    $actor = User::factory()->create();

    $snapshot = app(CommissionCalculator::class)->snapshot($session, $actor);

    expect($snapshot['snapshotted_by_user_id'])->toBe($actor->id);
});

it('rate_source is the id of the rate that was used', function () {
    $session = makeSnapshotSession($this->ahmad, $this->tiktok, [
        'gmv_amount' => 1000,
    ]);

    $rate = LiveHostPlatformCommissionRate::query()
        ->where('user_id', $this->ahmad->id)
        ->where('platform_id', $this->tiktok->id)
        ->where('is_active', true)
        ->first();

    expect($rate)->not->toBeNull();

    $snapshot = app(CommissionCalculator::class)->snapshot($session);

    expect($snapshot['rate_source'])->toBe($rate->id);
});

it('rate_source is null when no rate applied (no commission)', function () {
    // Fresh host with no platform commission rate row
    $orphan = User::factory()->create(['role' => 'live_host']);

    $session = makeSnapshotSession($orphan, $this->tiktok, [
        'gmv_amount' => 1000,
    ]);

    $snapshot = app(CommissionCalculator::class)->snapshot($session);

    expect($snapshot['rate_source'])->toBeNull();
});

it('does not persist the snapshot to the session', function () {
    // Use pending status so the observer (Task 13) does not fire; we want
    // to isolate snapshot()'s behaviour.
    $session = makeSnapshotSession($this->ahmad, $this->tiktok, [
        'gmv_amount' => 1000,
        'verification_status' => 'pending',
        'commission_snapshot_json' => null,
        'gmv_locked_at' => null,
    ]);

    app(CommissionCalculator::class)->snapshot($session);

    $fresh = $session->fresh();
    expect($fresh->commission_snapshot_json)->toBeNull();
    expect($fresh->gmv_locked_at)->toBeNull();
});
