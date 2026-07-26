<?php

declare(strict_types=1);

use App\Models\ActualLiveRecord;
use App\Models\LiveAccount;
use App\Models\LiveScheduleAssignment;
use App\Models\LiveSession;
use App\Models\LiveTimeSlot;
use App\Models\PlatformAccount;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-04-21 20:00:00');

    $this->platform = PlatformAccount::factory()->create();
    // One creator account shared by two hosts across the day (punca kuasa).
    $this->account = LiveAccount::factory()->create(['creator_user_id' => '900900900']);

    $this->hostA = User::factory()->create(['role' => 'live_host', 'name' => 'Host A Morning']);
    $this->hostB = User::factory()->create(['role' => 'live_host', 'name' => 'Host B Evening']);

    $this->morning = LiveTimeSlot::factory()->create(['start_time' => '09:00:00', 'end_time' => '11:00:00']);
    $this->evening = LiveTimeSlot::factory()->create(['start_time' => '20:00:00', 'end_time' => '22:00:00']);

    $this->assignA = LiveScheduleAssignment::factory()->create([
        'platform_account_id' => $this->platform->id,
        'live_account_id' => $this->account->id,
        'live_host_id' => $this->hostA->id,
        'time_slot_id' => $this->morning->id,
        'is_template' => false,
        'schedule_date' => '2026-04-20',
        'day_of_week' => Carbon::parse('2026-04-20')->dayOfWeek,
    ]);
    $this->assignB = LiveScheduleAssignment::factory()->create([
        'platform_account_id' => $this->platform->id,
        'live_account_id' => $this->account->id,
        'live_host_id' => $this->hostB->id,
        'time_slot_id' => $this->evening->id,
        'is_template' => false,
        'schedule_date' => '2026-04-20',
        'day_of_week' => Carbon::parse('2026-04-20')->dayOfWeek,
    ]);

    $this->sessionA = LiveSession::where('live_schedule_assignment_id', $this->assignA->id)->firstOrFail();
    $this->sessionB = LiveSession::where('live_schedule_assignment_id', $this->assignB->id)->firstOrFail();

    // A 20:30 live (belongs in Host B's evening slot) was mis-attributed to Host
    // A's morning session by the old proximity matcher — Host A wrongly credited.
    $this->live = ActualLiveRecord::factory()->apiSync()->create([
        'platform_account_id' => $this->platform->id,
        'creator_platform_user_id' => '900900900',
        'creator_handle' => 'someshop',
        'launched_time' => '2026-04-20 20:30:00',
        'ended_time' => '2026-04-20 21:30:00',
        'duration_seconds' => 3600,
        'live_attributed_gmv_myr' => 900.00,
        'viewers' => 3000,
    ]);
    $this->sessionA->actualLiveRecords()->attach($this->live->id, [
        'is_primary' => true,
        'live_attributed_gmv_myr' => 900.00,
        'linked_at' => now(),
    ]);
    $this->sessionA->update([
        'matched_actual_live_record_id' => $this->live->id,
        'gmv_amount' => 900.00,
        'gmv_source' => 'tiktok_actual',
        'verification_status' => 'verified',
        'auto_verified' => true,
        'status' => 'ended',
        'actual_start_at' => $this->live->launched_time,
        'actual_end_at' => $this->live->ended_time,
    ]);
});

afterEach(fn () => Carbon::setTestNow());

it('dry-run reports the mis-attribution without moving it', function () {
    $this->artisan('livehost:reattribute-lives --from=2026-04-20 --until=2026-04-20')
        ->assertSuccessful();

    // Nothing moved.
    expect($this->sessionA->refresh()->actualLiveRecords()->count())->toBe(1);
    expect($this->sessionB->refresh()->actualLiveRecords()->count())->toBe(0);
});

it('moves the live to the correct host and re-credits the GMV with --apply', function () {
    $this->artisan('livehost:reattribute-lives --from=2026-04-20 --until=2026-04-20 --apply')
        ->assertSuccessful();

    // Host A (wrong) loses the live and its GMV; reverts to pending (now empty).
    $this->sessionA->refresh();
    expect($this->sessionA->actualLiveRecords()->count())->toBe(0);
    expect((float) $this->sessionA->gmv_amount)->toBe(0.0);
    expect($this->sessionA->verification_status)->toBe('pending');

    // Host B (correct) now holds the live and the RM900 commission.
    $this->sessionB->refresh();
    expect($this->sessionB->actualLiveRecords()->count())->toBe(1);
    expect((float) $this->sessionB->gmv_amount)->toBe(900.0);
    expect($this->sessionB->verification_status)->toBe('verified');
    expect($this->sessionB->matched_actual_live_record_id)->toBe($this->live->id);
});

it('does NOT move a mis-attributed live whose GMV is still unpublished (-1)', function () {
    // The same wrong-host live, but its GMV is the -1 sentinel — untrustworthy, so
    // it must be left for revert-unpublished-gmv, not re-homed on a guessed host.
    $this->live->update(['live_attributed_gmv_myr' => -1.00]);

    $this->artisan('livehost:reattribute-lives --from=2026-04-20 --until=2026-04-20 --apply')
        ->assertSuccessful();

    // Untouched — still on Host A's (wrong) session; nothing moved to Host B.
    expect($this->sessionA->refresh()->actualLiveRecords()->count())->toBe(1);
    expect($this->sessionB->refresh()->actualLiveRecords()->count())->toBe(0);
});

it('leaves a correctly-attributed live untouched', function () {
    // Move the live onto Host B properly first, then re-run: no further moves.
    $this->artisan('livehost:reattribute-lives --from=2026-04-20 --until=2026-04-20 --apply')->assertSuccessful();

    $this->artisan('livehost:reattribute-lives --from=2026-04-20 --until=2026-04-20 --apply')
        ->expectsOutputToContain('no mis-attributed commission found')
        ->assertSuccessful();
});
