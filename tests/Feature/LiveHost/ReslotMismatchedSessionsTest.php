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
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * All timestamps are KL wall-clock (config app.timezone = Asia/Kuala_Lumpur).
 */
beforeEach(function () {
    Carbon::setTestNow('2026-04-25 12:00:00');

    $this->host = User::factory()->create(['role' => 'live_host']);
    $this->platform = PlatformAccount::factory()->create();
    $this->account = LiveAccount::factory()->create(['creator_user_id' => '900900900']);

    $slotFor = function (string $start, string $end) {
        $slot = LiveTimeSlot::factory()->create([
            'platform_account_id' => $this->platform->id,
            'start_time' => $start,
            'end_time' => $end,
        ]);
        $assignment = LiveScheduleAssignment::factory()->create([
            'platform_account_id' => $this->platform->id,
            'live_account_id' => $this->account->id,
            'live_host_id' => $this->host->id,
            'time_slot_id' => $slot->id,
            'is_template' => false,
            'schedule_date' => '2026-04-24',
            'day_of_week' => Carbon::parse('2026-04-24')->dayOfWeek,
        ]);

        // The observer materialises a scheduled, pending session for the slot.
        return LiveSession::where('live_schedule_assignment_id', $assignment->id)->firstOrFail();
    };

    // Two adjacent slots sharing one creator account.
    $this->earlySession = $slotFor('06:00:00', '07:00:00');
    $this->lateSession = $slotFor('07:00:00', '09:00:00');

    // The real live sits inside the LATE slot's window (07:20–08:30).
    $this->live = ActualLiveRecord::factory()->apiSync()->create([
        'platform_account_id' => $this->platform->id,
        'creator_platform_user_id' => '900900900',
        'creator_handle' => 'someshop',
        'launched_time' => '2026-04-24 07:20:00',
        'ended_time' => '2026-04-24 08:30:00',
        'duration_seconds' => 4200,
        'live_attributed_gmv_myr' => 1000.00,
        'viewers' => 900,
    ]);

    // Simulate the old proximity bug: the live was auto-linked to the EARLY
    // (06:00–07:00) session, which its window does not contain.
    $this->earlySession->actualLiveRecords()->sync([
        $this->live->id => [
            'is_primary' => true,
            'live_attributed_gmv_myr' => 1000.00,
            'linked_by' => null,
            'linked_at' => now(),
        ],
    ]);
    $this->earlySession->update([
        'matched_actual_live_record_id' => $this->live->id,
        'gmv_amount' => 1000.00,
        'gmv_source' => 'tiktok_actual',
        'gmv_locked_at' => now(),
        'verification_status' => 'verified',
        'verified_at' => now(),
        'auto_verified' => true,
        'status' => 'ended',
        'actual_start_at' => '2026-04-24 07:20:00',
        'actual_end_at' => '2026-04-24 08:30:00',
    ]);
});

afterEach(fn () => Carbon::setTestNow());

function reslot(array $opts = []): int
{
    return Artisan::call('livehost:reslot-mismatched', array_merge([
        '--from' => '2026-04-24',
        '--until' => '2026-04-24',
    ], $opts));
}

it('leaves everything untouched on a dry run', function () {
    reslot();

    $this->earlySession->refresh();
    expect($this->earlySession->verification_status)->toBe('verified')
        ->and($this->earlySession->actualLiveRecords()->count())->toBe(1);
});

it('resets the mis-slotted session and relinks the live to the correct slot', function () {
    reslot(['--apply' => true, '--reverify' => true]);

    $this->earlySession->refresh();
    $this->lateSession->refresh();

    // The early (06:00) slot let go of the 07:20 live.
    expect($this->earlySession->verification_status)->toBe('pending')
        ->and((bool) $this->earlySession->auto_verified)->toBeFalse()
        ->and((float) $this->earlySession->gmv_amount)->toBe(0.0)
        ->and($this->earlySession->actualLiveRecords()->count())->toBe(0);

    // The late (07:00–09:00) slot, whose window contains it, now owns it.
    expect($this->lateSession->verification_status)->toBe('verified')
        ->and($this->lateSession->matched_actual_live_record_id)->toBe($this->live->id)
        ->and((float) $this->lateSession->gmv_amount)->toBe(1000.0);
});

it('does not reset a session a human has verified', function () {
    LiveSessionVerificationEvent::factory()->create([
        'live_session_id' => $this->earlySession->id,
    ]);

    reslot(['--apply' => true]);

    $this->earlySession->refresh();
    expect($this->earlySession->verification_status)->toBe('verified')
        ->and($this->earlySession->actualLiveRecords()->count())->toBe(1);
});

it('leaves a correctly-slotted session alone', function () {
    // Move the live into the early slot's own window — now it is correct.
    $this->live->update([
        'launched_time' => '2026-04-24 06:10:00',
        'ended_time' => '2026-04-24 06:50:00',
        'duration_seconds' => 2400,
    ]);
    $this->earlySession->update([
        'actual_start_at' => '2026-04-24 06:10:00',
        'actual_end_at' => '2026-04-24 06:50:00',
    ]);

    reslot(['--apply' => true]);

    $this->earlySession->refresh();
    expect($this->earlySession->verification_status)->toBe('verified')
        ->and($this->earlySession->actualLiveRecords()->count())->toBe(1);
});
