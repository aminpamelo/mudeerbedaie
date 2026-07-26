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

beforeEach(function () {
    Carbon::setTestNow('2026-07-25 20:00:00');

    $this->platform = PlatformAccount::factory()->create();
    $this->account = LiveAccount::factory()->create(['creator_user_id' => '900900900']);
    $this->host = User::factory()->create(['role' => 'live_host']);
    $this->slot = LiveTimeSlot::factory()->create(['start_time' => '09:00:00', 'end_time' => '11:00:00']);
});

afterEach(fn () => Carbon::setTestNow());

function verifiedSessionOn(string $date): LiveSession
{
    $assignment = LiveScheduleAssignment::factory()->create([
        'platform_account_id' => test()->platform->id, 'live_account_id' => test()->account->id,
        'live_host_id' => test()->host->id, 'time_slot_id' => test()->slot->id,
        'is_template' => false, 'schedule_date' => $date, 'day_of_week' => Carbon::parse($date)->dayOfWeek,
    ]);
    $session = LiveSession::where('live_schedule_assignment_id', $assignment->id)->firstOrFail();
    $live = ActualLiveRecord::factory()->apiSync()->create([
        'platform_account_id' => test()->platform->id, 'creator_platform_user_id' => '900900900',
        'creator_handle' => 'someshop', 'launched_time' => "{$date} 09:15:00", 'ended_time' => "{$date} 10:30:00",
        'duration_seconds' => 4500, 'live_attributed_gmv_myr' => 500, 'viewers' => 1000,
    ]);
    $session->actualLiveRecords()->attach($live->id, ['is_primary' => true, 'live_attributed_gmv_myr' => 500, 'linked_at' => now()]);
    $session->update([
        'matched_actual_live_record_id' => $live->id, 'gmv_amount' => 500, 'gmv_source' => 'tiktok_actual',
        'gmv_locked_at' => now(), 'verification_status' => 'verified', 'auto_verified' => true, 'status' => 'ended',
        'actual_start_at' => $live->launched_time, 'actual_end_at' => $live->ended_time,
    ]);

    return $session;
}

it('resets verified sessions in the range to pending with --apply', function () {
    $june = verifiedSessionOn('2026-06-10');
    $july = verifiedSessionOn('2026-07-05');
    $august = verifiedSessionOn('2026-08-02'); // outside range

    $this->artisan('livehost:unverify-range --from=2026-06-01 --until=2026-07-31 --apply')->assertSuccessful();

    expect($june->refresh()->verification_status)->toBe('pending');
    expect((float) $june->gmv_amount)->toBe(0.0);
    expect($june->actualLiveRecords()->count())->toBe(0);
    expect($july->refresh()->verification_status)->toBe('pending');
    // Out of range — untouched.
    expect($august->refresh()->verification_status)->toBe('verified');
});

it('dry-run changes nothing', function () {
    $june = verifiedSessionOn('2026-06-10');

    $this->artisan('livehost:unverify-range --from=2026-06-01 --until=2026-07-31')->assertSuccessful();

    expect($june->refresh()->verification_status)->toBe('verified');
});

it('preserves human-verified sessions with --keep-human', function () {
    $auto = verifiedSessionOn('2026-06-10');
    $human = verifiedSessionOn('2026-06-11');
    LiveSessionVerificationEvent::create([
        'live_session_id' => $human->id, 'actual_live_record_id' => $human->matched_actual_live_record_id,
        'action' => 'verify_link', 'user_id' => $this->host->id, 'gmv_snapshot' => 500, 'notes' => 'manual',
    ]);

    $this->artisan('livehost:unverify-range --from=2026-06-01 --until=2026-07-31 --keep-human --apply')->assertSuccessful();

    expect($auto->refresh()->verification_status)->toBe('pending');
    expect($human->refresh()->verification_status)->toBe('verified');
});
