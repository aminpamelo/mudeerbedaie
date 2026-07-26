<?php

declare(strict_types=1);

use App\Models\ActualLiveRecord;
use App\Models\PlatformAccount;
use App\Models\TiktokLiveReport;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('backfills a mis-stamped launched_time from the matching report', function () {
    $platform = PlatformAccount::factory()->create();

    // Report holds the REAL start; the ALR was mis-stamped with the sync moment.
    TiktokLiveReport::create([
        'platform_account_id' => $platform->id,
        'tiktok_live_id' => '7650341066930981650',
        'launched_time' => '2026-06-12 11:11:04',
        'duration_seconds' => 7247,
        'live_attributed_gmv_myr' => 291.46,
        'source' => 'api',
        'synced_at' => '2026-07-12 23:45:14',
    ]);
    $alr = ActualLiveRecord::factory()->apiSync()->create([
        'platform_account_id' => $platform->id,
        'source_record_id' => '7650341066930981650',
        'launched_time' => '2026-07-12 23:45:14', // wrong = sync moment
        'duration_seconds' => 7247,
        'live_attributed_gmv_myr' => 291.46,
    ]);

    $this->artisan('livehost:repair-launch-times --apply')->assertSuccessful();

    $alr->refresh();
    expect($alr->launched_time->format('Y-m-d H:i:s'))->toBe('2026-06-12 11:11:04');
    // ended_time must be recomputed from the REAL start + duration (11:11:04 + 7247s),
    // not left stale from the old wrong start.
    expect($alr->ended_time->format('Y-m-d H:i:s'))->toBe('2026-06-12 13:11:51');
});

it('recomputes a stale ended_time even when launched_time is already correct', function () {
    $platform = PlatformAccount::factory()->create();
    TiktokLiveReport::create([
        'platform_account_id' => $platform->id, 'tiktok_live_id' => '777', 'launched_time' => '2026-06-12 11:11:04',
        'duration_seconds' => 7200, 'live_attributed_gmv_myr' => 100, 'source' => 'api', 'synced_at' => '2026-06-12 12:00:00',
    ]);
    $alr = ActualLiveRecord::factory()->apiSync()->create([
        'platform_account_id' => $platform->id, 'source_record_id' => '777',
        'launched_time' => '2026-06-12 11:11:04', // already correct
        'ended_time' => '2026-07-21 13:14:00',    // stale (from a past wrong start)
        'duration_seconds' => 7200, 'live_attributed_gmv_myr' => 100,
    ]);

    $this->artisan('livehost:repair-launch-times --apply')->assertSuccessful();

    expect($alr->refresh()->ended_time->format('Y-m-d H:i:s'))->toBe('2026-06-12 13:11:04');
});

it('dry-run changes nothing', function () {
    $platform = PlatformAccount::factory()->create();
    TiktokLiveReport::create([
        'platform_account_id' => $platform->id, 'tiktok_live_id' => '999', 'launched_time' => '2026-06-12 11:11:04',
        'duration_seconds' => 3600, 'live_attributed_gmv_myr' => 100, 'source' => 'api', 'synced_at' => '2026-07-12 23:45:14',
    ]);
    $alr = ActualLiveRecord::factory()->apiSync()->create([
        'platform_account_id' => $platform->id, 'source_record_id' => '999',
        'launched_time' => '2026-07-12 23:45:14', 'duration_seconds' => 3600, 'live_attributed_gmv_myr' => 100,
    ]);

    $this->artisan('livehost:repair-launch-times')->assertSuccessful();

    expect($alr->refresh()->launched_time->format('Y-m-d H:i:s'))->toBe('2026-07-12 23:45:14');
});

it('leaves a correctly-stamped record untouched', function () {
    $platform = PlatformAccount::factory()->create();
    TiktokLiveReport::create([
        'platform_account_id' => $platform->id, 'tiktok_live_id' => '555', 'launched_time' => '2026-06-12 11:11:04',
        'duration_seconds' => 3600, 'live_attributed_gmv_myr' => 100, 'source' => 'api', 'synced_at' => '2026-06-12 12:00:00',
    ]);
    ActualLiveRecord::factory()->apiSync()->create([
        'platform_account_id' => $platform->id, 'source_record_id' => '555',
        'launched_time' => '2026-06-12 11:11:04', 'ended_time' => '2026-06-12 12:11:04', // launched + duration = correct
        'duration_seconds' => 3600, 'live_attributed_gmv_myr' => 100,
    ]);

    $this->artisan('livehost:repair-launch-times')
        ->expectsOutputToContain('No mis-stamped times found')
        ->assertSuccessful();
});
