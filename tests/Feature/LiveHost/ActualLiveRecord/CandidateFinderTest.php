<?php

use App\Models\ActualLiveRecord;
use App\Models\LiveAccount;
use App\Models\LiveHostPlatformAccount;
use App\Models\LiveScheduleAssignment;
use App\Models\LiveSession;
use App\Models\LiveTimeSlot;
use App\Models\PlatformAccount;
use App\Services\LiveHost\ActualLiveRecordCandidateFinder;
use Carbon\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-04-24 12:00:00');
});

it('finds candidates via the session live account creator id', function () {
    $account = PlatformAccount::factory()->create();
    $live = LiveAccount::factory()->create(['creator_user_id' => 'creator_acct']);
    $session = LiveSession::factory()->create([
        'platform_account_id' => $account->id,
        'live_account_id' => $live->id,
        'live_host_platform_account_id' => null,
        'scheduled_start_at' => Carbon::parse('2026-04-24 14:00:00', 'Asia/Kuala_Lumpur'),
    ]);

    $hit = ActualLiveRecord::factory()->create([
        'platform_account_id' => $account->id,
        'creator_platform_user_id' => 'creator_acct',
        'launched_time' => Carbon::parse('2026-04-24 14:10:00', 'Asia/Kuala_Lumpur'),
    ]);

    $results = app(ActualLiveRecordCandidateFinder::class)->forSession($session);
    expect($results->pluck('id')->all())->toContain($hit->id);
});

it('finds candidates via normalized handle when the account has no creator id', function () {
    $account = PlatformAccount::factory()->create();
    $live = LiveAccount::factory()->create([
        'creator_user_id' => null,
        'normalized_handle' => 'amarmirzabedaie',
    ]);
    $session = LiveSession::factory()->create([
        'platform_account_id' => $account->id,
        'live_account_id' => $live->id,
        'live_host_platform_account_id' => null,
        'scheduled_start_at' => Carbon::parse('2026-04-24 14:00:00', 'Asia/Kuala_Lumpur'),
    ]);

    $hit = ActualLiveRecord::factory()->create([
        'platform_account_id' => $account->id,
        'creator_platform_user_id' => null,
        'creator_handle' => 'AmarMirzaBeDaie',
        'launched_time' => Carbon::parse('2026-04-24 14:10:00', 'Asia/Kuala_Lumpur'),
    ]);

    $results = app(ActualLiveRecordCandidateFinder::class)->forSession($session);
    expect($results->pluck('id')->all())->toContain($hit->id);
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

it('suggests only the contiguous split cluster near the scheduled slot', function () {
    $account = PlatformAccount::factory()->create();
    $live = LiveAccount::factory()->create(['creator_user_id' => 'c_split']);
    $session = LiveSession::factory()->create([
        'platform_account_id' => $account->id,
        'live_account_id' => $live->id,
        'live_host_platform_account_id' => null,
        'scheduled_start_at' => Carbon::parse('2026-04-24 14:00:00', 'Asia/Kuala_Lumpur'),
    ]);

    $mk = function (string $launch, int $durationMin) use ($account) {
        $start = Carbon::parse("2026-04-24 {$launch}", 'Asia/Kuala_Lumpur');

        return ActualLiveRecord::factory()->create([
            'platform_account_id' => $account->id,
            'creator_platform_user_id' => 'c_split',
            'launched_time' => $start,
            'ended_time' => $start->copy()->addMinutes($durationMin),
            'duration_seconds' => $durationMin * 60,
        ]);
    };

    // A 14:00 live that blipped: segment A ends 14:40, segment B restarts 14:43.
    $segA = $mk('14:00:00', 40);
    $segB = $mk('14:43:00', 50);
    // Unrelated separate lives hours away on the same day — must NOT be clustered.
    $morning = $mk('08:00:00', 60);
    $evening = $mk('21:00:00', 60);

    $finder = app(ActualLiveRecordCandidateFinder::class);
    $candidates = $finder->forSession($session);
    $cluster = $finder->suggestedClusterIds($candidates, $session);

    sort($cluster);
    $expected = [$segA->id, $segB->id];
    sort($expected);

    expect($cluster)->toBe($expected)
        ->and($cluster)->not->toContain($morning->id)
        ->and($cluster)->not->toContain($evening->id);
});

it('does not chain long back-to-back lives into one cluster', function () {
    $account = PlatformAccount::factory()->create();
    $live = LiveAccount::factory()->create(['creator_user_id' => 'c_b2b']);
    $session = LiveSession::factory()->create([
        'platform_account_id' => $account->id,
        'live_account_id' => $live->id,
        'live_host_platform_account_id' => null,
        'scheduled_start_at' => Carbon::parse('2026-04-24 09:00:00', 'Asia/Kuala_Lumpur'),
    ]);

    $mk = function (string $launch, int $durationMin) use ($account) {
        $start = Carbon::parse("2026-04-24 {$launch}", 'Asia/Kuala_Lumpur');

        return ActualLiveRecord::factory()->create([
            'platform_account_id' => $account->id,
            'creator_platform_user_id' => 'c_b2b',
            'launched_time' => $start,
            'ended_time' => $start->copy()->addMinutes($durationMin),
            'duration_seconds' => $durationMin * 60,
        ]);
    };

    // Three 2h lives back-to-back with tiny gaps — separate sessions, not a split.
    $mk('07:00:00', 120);
    $mk('09:05:00', 120);
    $mk('11:10:00', 120);

    $finder = app(ActualLiveRecordCandidateFinder::class);
    $cluster = $finder->suggestedClusterIds($finder->forSession($session), $session);

    // The span cap keeps the cluster small (never the whole chain).
    expect(count($cluster))->toBeLessThanOrEqual(2);
});

it('constrains the suggested cluster to the slot window when hosts share an account', function () {
    $account = PlatformAccount::factory()->create();
    $live = LiveAccount::factory()->create(['creator_user_id' => 'c_shared']);

    $sessionFor = function (string $slotStart, string $slotEnd, string $scheduled) use ($account, $live) {
        $slot = LiveTimeSlot::factory()->create([
            'platform_account_id' => $account->id,
            'start_time' => $slotStart,
            'end_time' => $slotEnd,
        ]);
        $assignment = LiveScheduleAssignment::factory()->create([
            'platform_account_id' => $account->id,
            'time_slot_id' => $slot->id,
            'live_host_id' => null,
            'is_template' => false,
            'schedule_date' => '2026-04-24',
        ]);

        return LiveSession::factory()->create([
            'platform_account_id' => $account->id,
            'live_account_id' => $live->id,
            'live_host_platform_account_id' => null,
            'live_schedule_assignment_id' => $assignment->id,
            'scheduled_start_at' => Carbon::parse("2026-04-24 {$scheduled}", 'Asia/Kuala_Lumpur'),
        ]);
    };

    // Two adjacent slots on the SAME account. The early slot has no live of its
    // own; the actual live sits squarely inside the late slot's window.
    $early = $sessionFor('06:00:00', '07:00:00', '06:00:00');
    $late = $sessionFor('07:00:00', '09:00:00', '07:00:00');

    $start = Carbon::parse('2026-04-24 07:20:00', 'Asia/Kuala_Lumpur');
    $lateLive = ActualLiveRecord::factory()->create([
        'platform_account_id' => $account->id,
        'creator_platform_user_id' => 'c_shared',
        'launched_time' => $start,
        'ended_time' => $start->copy()->addMinutes(70),
        'duration_seconds' => 70 * 60,
    ]);

    $finder = app(ActualLiveRecordCandidateFinder::class);

    // The early slot must NOT swallow the 07:20 live even though it is the only
    // candidate and would win on proximity alone.
    $earlyCluster = $finder->suggestedClusterIds($finder->forSession($early), $early);
    expect($earlyCluster)->toBe([]);

    // The late slot, whose window contains it, gets it.
    $lateCluster = $finder->suggestedClusterIds($finder->forSession($late), $late);
    expect($lateCluster)->toBe([$lateLive->id]);
});

it('picks the run with the greatest overlap when two runs touch the slot window', function () {
    $account = PlatformAccount::factory()->create();
    $live = LiveAccount::factory()->create(['creator_user_id' => 'c_overlap']);

    $slot = LiveTimeSlot::factory()->create([
        'platform_account_id' => $account->id,
        'start_time' => '14:00:00',
        'end_time' => '16:00:00',
    ]);
    $assignment = LiveScheduleAssignment::factory()->create([
        'platform_account_id' => $account->id,
        'time_slot_id' => $slot->id,
        'is_template' => false,
        'schedule_date' => '2026-04-24',
    ]);
    $session = LiveSession::factory()->create([
        'platform_account_id' => $account->id,
        'live_account_id' => $live->id,
        'live_host_platform_account_id' => null,
        'live_schedule_assignment_id' => $assignment->id,
        'scheduled_start_at' => Carbon::parse('2026-04-24 14:00:00', 'Asia/Kuala_Lumpur'),
    ]);

    $mk = function (string $launch, int $durationMin) use ($account) {
        $start = Carbon::parse("2026-04-24 {$launch}", 'Asia/Kuala_Lumpur');

        return ActualLiveRecord::factory()->create([
            'platform_account_id' => $account->id,
            'creator_platform_user_id' => 'c_overlap',
            'launched_time' => $start,
            'ended_time' => $start->copy()->addMinutes($durationMin),
            'duration_seconds' => $durationMin * 60,
        ]);
    };

    // A brief blip barely clipping the window start, then — after a >20-min gap so
    // it is a SEPARATE run — the real 80-minute live deep inside the window.
    $edge = $mk('13:55:00', 8);          // 13:55–14:03, ~3 min inside the window
    $realLive = $mk('14:30:00', 80);     // 14:30–15:50, deep inside the window

    $finder = app(ActualLiveRecordCandidateFinder::class);
    $cluster = $finder->suggestedClusterIds($finder->forSession($session), $session);

    expect($cluster)->toBe([$realLive->id])
        ->and($cluster)->not->toContain($edge->id);
});
