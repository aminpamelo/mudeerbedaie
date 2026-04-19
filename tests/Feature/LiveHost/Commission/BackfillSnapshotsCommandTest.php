<?php

use App\Models\LiveHostPlatformAccount;
use App\Models\LiveSession;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Factory helper for this command test. Defaults match the observer-test
 * fixture but each test overrides verification_status / gmv_locked_at /
 * commission_snapshot_json to exercise the command's selection criteria.
 *
 * @param  array<string, mixed>  $overrides
 */
function makeBackfillSession(User $host, Platform $platform, array $overrides = []): LiveSession
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

it('backfills commission_snapshot_json on verified sessions that have no snapshot', function () {
    // Create with verification_status=pending so observer does not fire,
    // then force the "legacy verified, no snapshot" state directly.
    $session1 = makeBackfillSession($this->ahmad, $this->tiktok, [
        'gmv_amount' => 1000,
        'verification_status' => 'pending',
        'gmv_locked_at' => null,
        'commission_snapshot_json' => null,
    ]);
    $session2 = makeBackfillSession($this->ahmad, $this->tiktok, [
        'gmv_amount' => 500,
        'verification_status' => 'pending',
        'gmv_locked_at' => null,
        'commission_snapshot_json' => null,
    ]);

    LiveSession::withoutEvents(function () use ($session1, $session2) {
        $session1->forceFill([
            'verification_status' => 'verified',
            'gmv_locked_at' => now(),
            'commission_snapshot_json' => null,
        ])->saveQuietly();
        $session2->forceFill([
            'verification_status' => 'verified',
            'gmv_locked_at' => now(),
            'commission_snapshot_json' => null,
        ])->saveQuietly();
    });

    $this->artisan('livehost:backfill-commission-snapshots')
        ->expectsOutput('Processed 2 sessions')
        ->assertSuccessful();

    $session1->refresh();
    $session2->refresh();

    expect($session1->commission_snapshot_json)->toBeArray();
    expect($session1->commission_snapshot_json)->toHaveKey('net_gmv');
    expect($session1->commission_snapshot_json)->toHaveKey('session_total');
    expect($session1->commission_snapshot_json)->toHaveKey('snapshotted_at');

    expect($session2->commission_snapshot_json)->toBeArray();
    expect($session2->commission_snapshot_json)->toHaveKey('net_gmv');
});

it('skips sessions that already have a snapshot', function () {
    $session = makeBackfillSession($this->ahmad, $this->tiktok, [
        'gmv_amount' => 1000,
        'verification_status' => 'pending',
        'gmv_locked_at' => null,
        'commission_snapshot_json' => null,
    ]);

    $existingSnapshot = [
        'net_gmv' => 9999,
        'platform_rate_percent' => 1.0,
        'gmv_commission' => 99.99,
        'per_live_rate' => 10.0,
        'session_total' => 109.99,
        'warnings' => [],
        'snapshotted_at' => now()->subDay()->toIso8601String(),
        'snapshotted_by_user_id' => null,
        'rate_source' => null,
    ];

    LiveSession::withoutEvents(function () use ($session, $existingSnapshot) {
        $session->forceFill([
            'verification_status' => 'verified',
            'gmv_locked_at' => now()->subDay(),
            'commission_snapshot_json' => $existingSnapshot,
        ])->saveQuietly();
    });

    $this->artisan('livehost:backfill-commission-snapshots')
        ->expectsOutput('Processed 0 sessions')
        ->assertSuccessful();

    $session->refresh();

    // Snapshot untouched — still has the sentinel 9999 value.
    expect($session->commission_snapshot_json['net_gmv'])->toBe(9999);
});

it('skips unverified sessions (gmv_locked_at null)', function () {
    $session = makeBackfillSession($this->ahmad, $this->tiktok, [
        'gmv_amount' => 1000,
        'verification_status' => 'pending',
        'gmv_locked_at' => null,
        'commission_snapshot_json' => null,
    ]);

    $this->artisan('livehost:backfill-commission-snapshots')
        ->expectsOutput('Processed 0 sessions')
        ->assertSuccessful();

    $session->refresh();

    expect($session->gmv_locked_at)->toBeNull();
    expect($session->commission_snapshot_json)->toBeNull();
});
