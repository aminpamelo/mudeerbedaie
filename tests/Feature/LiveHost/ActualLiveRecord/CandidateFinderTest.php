<?php

use App\Models\ActualLiveRecord;
use App\Models\LiveHostPlatformAccount;
use App\Models\LiveSession;
use App\Models\PlatformAccount;
use App\Services\LiveHost\ActualLiveRecordCandidateFinder;
use Carbon\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-04-24 12:00:00');
});

it('returns same-host same-day records ordered by time proximity', function () {
    $account = PlatformAccount::factory()->create();
    $pivot = LiveHostPlatformAccount::factory()->create([
        'platform_account_id' => $account->id,
        'creator_platform_user_id' => 'creator_x',
    ]);
    $session = LiveSession::factory()->create([
        'platform_account_id' => $account->id,
        'live_host_platform_account_id' => $pivot->id,
        'scheduled_start_at' => Carbon::parse('2026-04-24 14:00:00', 'Asia/Kuala_Lumpur'),
    ]);

    $near = ActualLiveRecord::factory()->create([
        'platform_account_id' => $account->id,
        'creator_platform_user_id' => 'creator_x',
        'launched_time' => Carbon::parse('2026-04-24 14:15:00', 'Asia/Kuala_Lumpur'),
    ]);
    $far = ActualLiveRecord::factory()->create([
        'platform_account_id' => $account->id,
        'creator_platform_user_id' => 'creator_x',
        'launched_time' => Carbon::parse('2026-04-24 22:00:00', 'Asia/Kuala_Lumpur'),
    ]);

    $results = app(ActualLiveRecordCandidateFinder::class)->forSession($session);

    expect($results->pluck('id')->values()->all())->toBe([$near->id, $far->id]);
});

it('excludes records already linked to another session', function () {
    $account = PlatformAccount::factory()->create();
    $pivot = LiveHostPlatformAccount::factory()->create([
        'platform_account_id' => $account->id,
        'creator_platform_user_id' => 'host_y',
    ]);
    $sessionA = LiveSession::factory()->create([
        'platform_account_id' => $account->id,
        'live_host_platform_account_id' => $pivot->id,
        'scheduled_start_at' => Carbon::parse('2026-04-24 14:00:00', 'Asia/Kuala_Lumpur'),
    ]);
    $sessionB = LiveSession::factory()->create([
        'platform_account_id' => $account->id,
        'live_host_platform_account_id' => $pivot->id,
        'scheduled_start_at' => Carbon::parse('2026-04-24 15:00:00', 'Asia/Kuala_Lumpur'),
    ]);

    $linkedElsewhere = ActualLiveRecord::factory()->create([
        'platform_account_id' => $account->id,
        'creator_platform_user_id' => 'host_y',
        'launched_time' => Carbon::parse('2026-04-24 14:30:00', 'Asia/Kuala_Lumpur'),
    ]);
    $sessionA->update(['matched_actual_live_record_id' => $linkedElsewhere->id]);

    $results = app(ActualLiveRecordCandidateFinder::class)->forSession($sessionB);
    expect($results->pluck('id')->all())->not->toContain($linkedElsewhere->id);
});

it('returns empty when host has no creator_platform_user_id', function () {
    $account = PlatformAccount::factory()->create();
    $pivot = LiveHostPlatformAccount::factory()->create([
        'platform_account_id' => $account->id,
        'creator_platform_user_id' => null,
    ]);
    $session = LiveSession::factory()->create([
        'platform_account_id' => $account->id,
        'live_host_platform_account_id' => $pivot->id,
        'scheduled_start_at' => now(),
    ]);

    $results = app(ActualLiveRecordCandidateFinder::class)->forSession($session);
    expect($results)->toBeEmpty();
});

it('respects asia kuala lumpur timezone for day boundary', function () {
    $account = PlatformAccount::factory()->create();
    $pivot = LiveHostPlatformAccount::factory()->create([
        'platform_account_id' => $account->id,
        'creator_platform_user_id' => 'host_tz',
    ]);
    $session = LiveSession::factory()->create([
        'platform_account_id' => $account->id,
        'live_host_platform_account_id' => $pivot->id,
        'scheduled_start_at' => Carbon::parse('2026-04-24 07:00', 'Asia/Kuala_Lumpur'),
    ]);

    // Record launched at 2026-04-24 KL time (equivalent to 2026-04-23 16:30 UTC)
    $sameDayKl = ActualLiveRecord::factory()->create([
        'platform_account_id' => $account->id,
        'creator_platform_user_id' => 'host_tz',
        'launched_time' => Carbon::parse('2026-04-24 00:30', 'Asia/Kuala_Lumpur'),
    ]);

    $results = app(ActualLiveRecordCandidateFinder::class)->forSession($session);
    expect($results->pluck('id')->all())->toContain($sameDayKl->id);
});
