<?php

declare(strict_types=1);

use App\Models\ActualLiveRecord;
use App\Models\LiveAccount;
use App\Models\LiveScheduleAssignment;
use App\Models\LiveSession;
use App\Models\LiveSessionVerificationEvent;
use App\Models\LiveTimeSlot;
use App\Models\PlatformAccount;
use App\Models\User;
use App\Services\LiveHost\AutoVerifyService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * All timestamps are KL wall-clock (config app.timezone = Asia/Kuala_Lumpur), so
 * the slot minute-of-day matching lines up 1:1 with the stored launched_time.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-04-20 20:00:00');

    $this->host = User::factory()->create(['role' => 'live_host']);
    $this->platform = PlatformAccount::factory()->create();
    $this->account = LiveAccount::factory()->create(['creator_user_id' => '900900900']);

    $this->morningSlot = LiveTimeSlot::factory()->create(['start_time' => '09:00:00', 'end_time' => '11:00:00']);

    $this->assignment = LiveScheduleAssignment::factory()->create([
        'platform_account_id' => $this->platform->id,
        'live_account_id' => $this->account->id,
        'live_host_id' => $this->host->id,
        'time_slot_id' => $this->morningSlot->id,
        'is_template' => false,
        'schedule_date' => '2026-04-20',
        'day_of_week' => Carbon::parse('2026-04-20')->dayOfWeek,
    ]);

    // The observer materialises a scheduled, pending LiveSession for the slot.
    $this->session = LiveSession::where('live_schedule_assignment_id', $this->assignment->id)->firstOrFail();
});

afterEach(fn () => Carbon::setTestNow());

function makeLive(array $overrides = []): ActualLiveRecord
{
    return ActualLiveRecord::factory()->apiSync()->create(array_merge([
        'platform_account_id' => test()->platform->id,
        'creator_platform_user_id' => '900900900',
        'creator_handle' => 'someshop',
        'launched_time' => '2026-04-20 09:00:00',
        'ended_time' => '2026-04-20 10:30:00',
        'duration_seconds' => 5400,
        'live_attributed_gmv_myr' => 1000.00,
        'viewers' => 1200,
    ], $overrides));
}

function service(): AutoVerifyService
{
    return app(AutoVerifyService::class);
}

function windowRun(): array
{
    return service()->run(CarbonImmutable::parse('2026-04-19'), CarbonImmutable::now());
}

function windowRefresh(): array
{
    return service()->refresh(CarbonImmutable::parse('2026-04-19'), CarbonImmutable::now());
}

it('auto-verifies a settled matched live, locking the summed GMV', function () {
    makeLive();

    $stats = windowRun();

    expect($stats['sessions_verified'])->toBe(1);

    $this->session->refresh();
    expect($this->session->verification_status)->toBe('verified');
    expect((bool) $this->session->auto_verified)->toBeTrue();
    expect((float) $this->session->gmv_amount)->toBe(1000.0);
    expect($this->session->status)->toBe('ended');
});

it('backlog sweep verifies an old clean pending session the short live-scan window skips', function () {
    // Five days on, the schedule slot + live from 2026-04-20 are long settled.
    Carbon::setTestNow('2026-04-25 20:00:00');
    $live = makeLive(); // 2026-04-20 09:00–10:30 on the morning slot

    // The scheduled run only scans the last 2 days of lives, so it never revisits
    // the 04-20 session: both the live-driven and in-window session passes exclude it.
    $stats = service()->run(CarbonImmutable::parse('2026-04-24'), CarbonImmutable::now());
    expect($stats['sessions_verified'])->toBe(0);
    expect($this->session->refresh()->verification_status)->toBe('pending');

    // The backlog sweep gives it a fresh evaluation through the same gates.
    $sweep = service()->sweepPending(CarbonImmutable::parse('2026-04-01'));

    expect($sweep['sweep_verified'])->toBe(1);

    $this->session->refresh();
    expect($this->session->verification_status)->toBe('verified');
    expect((bool) $this->session->auto_verified)->toBeTrue();
    expect((float) $this->session->gmv_amount)->toBe(1000.0);
});

it('backlog sweep leaves a human-touched pending session alone', function () {
    Carbon::setTestNow('2026-04-25 20:00:00');
    $live = makeLive();

    LiveSessionVerificationEvent::create([
        'live_session_id' => $this->session->id,
        'actual_live_record_id' => $live->id,
        'action' => 'verify_link',
        'user_id' => $this->host->id,
        'gmv_snapshot' => 1000.00,
        'notes' => 'touched by a human',
    ]);

    $sweep = service()->sweepPending(CarbonImmutable::parse('2026-04-01'));

    expect($sweep['sweep_verified'])->toBe(0);
    expect($this->session->refresh()->verification_status)->toBe('pending');
});

it('attributes a slot-spanning live to the host on duty when it STARTED, not the slot it spills into', function () {
    // Second host's slot immediately after the morning slot, same creator account.
    $eveningHost = User::factory()->create(['role' => 'live_host']);
    $noonSlot = LiveTimeSlot::factory()->create(['start_time' => '11:00:00', 'end_time' => '13:00:00']);
    $noonAssignment = LiveScheduleAssignment::factory()->create([
        'platform_account_id' => $this->platform->id,
        'live_account_id' => $this->account->id,
        'live_host_id' => $eveningHost->id,
        'time_slot_id' => $noonSlot->id,
        'is_template' => false,
        'schedule_date' => '2026-04-20',
        'day_of_week' => Carbon::parse('2026-04-20')->dayOfWeek,
    ]);
    $noonSession = LiveSession::where('live_schedule_assignment_id', $noonAssignment->id)->firstOrFail();

    // Launches 10:30 (inside the 09:00–11:00 morning slot) but runs 2h, spilling
    // into the 11:00–13:00 slot. It belongs to the MORNING host who started it.
    makeLive([
        'launched_time' => '2026-04-20 10:30:00',
        'ended_time' => '2026-04-20 12:30:00',
        'duration_seconds' => 7200,
        'live_attributed_gmv_myr' => 1000.00,
    ]);

    windowRun();

    $this->session->refresh();
    expect($this->session->verification_status)->toBe('verified');
    expect((float) $this->session->gmv_amount)->toBe(1000.0);

    // The spilled-into slot must stay empty — its commission is untouched.
    expect($noonSession->refresh()->verification_status)->toBe('pending');
    expect($noonSession->actualLiveRecords()->count())->toBe(0);
});

it('does NOT auto-verify a settled live whose GMV is still unavailable (-1 sentinel)', function () {
    // Real, finished live but TikTok has not published the 24h GMV yet: it returns
    // -1, which must NOT be locked as RM0.
    makeLive(['live_attributed_gmv_myr' => -1.00]);

    $stats = windowRun();

    expect($stats['gmv_unpublished'])->toBe(1);
    expect($stats['sessions_verified'])->toBe(0);

    $this->session->refresh();
    expect($this->session->verification_status)->toBe('pending');
    expect((float) $this->session->gmv_amount)->toBe(0.0);
});

it('auto-verifies once the previously -1 GMV is published on a later sync', function () {
    $live = makeLive(['live_attributed_gmv_myr' => -1.00]);
    expect(windowRun()['sessions_verified'])->toBe(0);

    // A later sync fills in the real 24h GMV.
    $live->update(['live_attributed_gmv_myr' => 1750.00]);

    $stats = windowRun();

    expect($stats['sessions_verified'])->toBe(1);
    $this->session->refresh();
    expect($this->session->verification_status)->toBe('verified');
    expect((float) $this->session->gmv_amount)->toBe(1750.0);
});

it('defers a live whose launch time is a placeholder (collides with another distinct live)', function () {
    // Two DIFFERENT lives (different source_record_id) stamped with the identical
    // launch second on one account — a sync placeholder, not a real start. Neither
    // can be trusted to a slot, so auto-verify must defer, not guess a host.
    makeLive(['source_record_id' => '1111111111111111', 'launched_time' => '2026-04-20 09:00:00', 'ended_time' => '2026-04-20 10:00:00', 'duration_seconds' => 3600]);
    ActualLiveRecord::factory()->apiSync()->create([
        'platform_account_id' => $this->platform->id,
        'creator_platform_user_id' => '900900900',
        'creator_handle' => 'someshop',
        'source_record_id' => '2222222222222222',
        'launched_time' => '2026-04-20 09:00:00',
        'ended_time' => '2026-04-20 10:00:00',
        'duration_seconds' => 3600,
        'live_attributed_gmv_myr' => 500,
        'viewers' => 800,
    ]);

    $stats = windowRun();

    expect($stats['placeholder_time'])->toBeGreaterThanOrEqual(1);
    expect($stats['sessions_verified'])->toBe(0);
    expect($this->session->refresh()->verification_status)->toBe('pending');
});

it('auto-verifies once the colliding placeholder is resolved to a distinct real time', function () {
    $a = makeLive(['source_record_id' => '1111111111111111', 'launched_time' => '2026-04-20 09:00:00', 'ended_time' => '2026-04-20 10:00:00', 'duration_seconds' => 3600]);
    $b = ActualLiveRecord::factory()->apiSync()->create([
        'platform_account_id' => $this->platform->id,
        'creator_platform_user_id' => '900900900',
        'creator_handle' => 'someshop',
        'source_record_id' => '2222222222222222',
        'launched_time' => '2026-04-20 09:00:00',
        'ended_time' => '2026-04-20 10:00:00',
        'duration_seconds' => 3600,
        'live_attributed_gmv_myr' => 500,
        'viewers' => 800,
    ]);
    expect(windowRun()['sessions_verified'])->toBe(0);

    // A re-sync gives the second live its real, distinct start (a different slot's
    // window). The first no longer collides → auto-verify can trust it.
    $b->update(['launched_time' => '2026-04-20 13:00:00', 'ended_time' => '2026-04-20 14:00:00']);

    $stats = windowRun();

    expect($stats['sessions_verified'])->toBe(1);
    expect($this->session->refresh()->verification_status)->toBe('verified');
});

it('does NOT auto-verify a live that is still running / just ended (settle delay)', function () {
    // Launched 20 min ago, no end yet → computed end is in the future → unsettled.
    makeLive([
        'launched_time' => Carbon::now()->subMinutes(20),
        'ended_time' => null,
        'duration_seconds' => null,
    ]);

    // Slot must still overlap "now" for the match to reach the settle check.
    $this->morningSlot->update(['start_time' => '00:00:00', 'end_time' => '23:59:00']);

    $stats = windowRun();

    expect($stats['unsettled'])->toBe(1);
    expect($stats['sessions_verified'])->toBe(0);

    $this->session->refresh();
    expect($this->session->verification_status)->toBe('pending');
});

it('refreshes the locked GMV upward as the live 24h attribution grows', function () {
    $live = makeLive();
    windowRun();

    expect((float) $this->session->refresh()->gmv_amount)->toBe(1000.0);

    // A later sync re-writes the same record with a larger 24h GMV.
    $live->update(['live_attributed_gmv_myr' => 2500.00]);

    $result = windowRefresh();

    expect($result['stats']['gmv_updated'])->toBe(1);
    expect((float) $this->session->refresh()->gmv_amount)->toBe(2500.0);
});

it('pulls a newly-synced reconnect segment into the session on refresh, re-summing GMV', function () {
    makeLive();
    windowRun();
    expect($this->session->refresh()->actualLiveRecords()->count())->toBe(1);

    // The host reconnected; that second segment only synced after the first lock.
    makeLive([
        'source_record_id' => (string) fake()->numerify('################'),
        'launched_time' => '2026-04-20 10:35:00',
        'ended_time' => '2026-04-20 11:00:00',
        'duration_seconds' => 1500,
        'live_attributed_gmv_myr' => 400.00,
    ]);

    $result = windowRefresh();

    expect($result['stats']['segments_added'])->toBe(1);

    $this->session->refresh();
    expect($this->session->actualLiveRecords()->count())->toBe(2);
    expect((float) $this->session->gmv_amount)->toBe(1400.0);
});

it('does not refresh a session a human has since touched', function () {
    $live = makeLive();
    windowRun();

    // A human left a verification event (e.g. unverified then re-verified).
    LiveSessionVerificationEvent::create([
        'live_session_id' => $this->session->id,
        'actual_live_record_id' => $live->id,
        'action' => 'verify_link',
        'user_id' => $this->host->id,
        'gmv_snapshot' => 1000.00,
        'notes' => 'manual',
    ]);

    $live->update(['live_attributed_gmv_myr' => 9999.00]);

    $result = windowRefresh();

    expect($result['stats']['gmv_updated'])->toBe(0);
    expect((float) $this->session->refresh()->gmv_amount)->toBe(1000.0);
});

it('freezes GMV once the live is older than the refresh window', function () {
    $live = makeLive();
    windowRun();

    // Two days later the 24h attribution window is long closed.
    Carbon::setTestNow('2026-04-22 20:00:00');
    $live->update(['live_attributed_gmv_myr' => 5000.00]);

    $result = service()->refresh(CarbonImmutable::parse('2026-04-20'), CarbonImmutable::now());

    expect($result['stats']['refresh_scanned'])->toBe(0);
    expect((float) $this->session->refresh()->gmv_amount)->toBe(1000.0);
});

it('auto-heals drift: moves a live back to the correct slot when its own slot no longer overlaps', function () {
    $live = makeLive();
    windowRun();
    expect($this->session->refresh()->verification_status)->toBe('verified');

    // The schedule was edited AFTER the lock: this assignment's slot moved to the
    // afternoon, so it no longer overlaps the 09:00–10:30 live.
    $afternoon = LiveTimeSlot::factory()->create(['start_time' => '14:00:00', 'end_time' => '16:00:00']);
    $this->assignment->update(['time_slot_id' => $afternoon->id]);

    // The correct (morning) slot now lives on a fresh, empty assignment.
    $correct = LiveScheduleAssignment::factory()->create([
        'platform_account_id' => $this->platform->id,
        'live_account_id' => $this->account->id,
        'live_host_id' => $this->host->id,
        'time_slot_id' => $this->morningSlot->id,
        'is_template' => false,
        'schedule_date' => '2026-04-20',
        'day_of_week' => Carbon::parse('2026-04-20')->dayOfWeek,
    ]);
    $targetSession = LiveSession::where('live_schedule_assignment_id', $correct->id)->firstOrFail();

    $result = windowRefresh();

    expect($result['stats']['drift_fixed'])->toBe(1);

    // Source reverted to pending, target now carries the verified live + GMV.
    $this->session->refresh();
    expect($this->session->verification_status)->toBe('pending');
    expect((float) $this->session->gmv_amount)->toBe(0.0);

    $targetSession->refresh();
    expect($targetSession->verification_status)->toBe('verified');
    expect((float) $targetSession->gmv_amount)->toBe(1000.0);
    expect($targetSession->matched_actual_live_record_id)->toBe($live->id);
});

it('flags drift instead of moving when the correct slot is already occupied', function () {
    makeLive();
    windowRun();

    // Move this assignment off the live's window.
    $afternoon = LiveTimeSlot::factory()->create(['start_time' => '14:00:00', 'end_time' => '16:00:00']);
    $this->assignment->update(['time_slot_id' => $afternoon->id]);

    // The correct morning slot exists but is already occupied by a different live.
    $correct = LiveScheduleAssignment::factory()->create([
        'platform_account_id' => $this->platform->id,
        'live_account_id' => $this->account->id,
        'live_host_id' => $this->host->id,
        'time_slot_id' => $this->morningSlot->id,
        'is_template' => false,
        'schedule_date' => '2026-04-20',
        'day_of_week' => Carbon::parse('2026-04-20')->dayOfWeek,
    ]);
    $targetSession = LiveSession::where('live_schedule_assignment_id', $correct->id)->firstOrFail();
    $other = makeLive([
        'source_record_id' => (string) fake()->numerify('################'),
        'launched_time' => '2026-04-20 09:30:00',
    ]);
    $targetSession->actualLiveRecords()->attach($other->id, [
        'is_primary' => true,
        'live_attributed_gmv_myr' => 500,
        'linked_at' => now(),
    ]);
    $targetSession->update(['matched_actual_live_record_id' => $other->id, 'verification_status' => 'verified']);

    $result = windowRefresh();

    expect($result['stats']['drift_fixed'])->toBe(0);
    expect($result['stats']['drift_flagged'])->toBe(1);
    expect(collect($result['findings'])->firstWhere('type', 'conflict'))->not->toBeNull();

    // Source untouched — still verified, still holding its live.
    expect($this->session->refresh()->verification_status)->toBe('verified');
});
