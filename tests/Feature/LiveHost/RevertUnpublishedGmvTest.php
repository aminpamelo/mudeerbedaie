<?php

declare(strict_types=1);

use App\Models\ActualLiveRecord;
use App\Models\LiveAccount;
use App\Models\LiveScheduleAssignment;
use App\Models\LiveSession;
use App\Models\LiveTimeSlot;
use App\Models\PlatformAccount;
use App\Models\User;
use App\Services\LiveHost\AutoVerifyService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-04-25 20:00:00');

    $this->host = User::factory()->create(['role' => 'live_host']);
    $this->platform = PlatformAccount::factory()->create();
    $this->account = LiveAccount::factory()->create(['creator_user_id' => '900900900']);
    $this->slot = LiveTimeSlot::factory()->create(['start_time' => '09:00:00', 'end_time' => '11:00:00']);

    $this->assignment = LiveScheduleAssignment::factory()->create([
        'platform_account_id' => $this->platform->id,
        'live_account_id' => $this->account->id,
        'live_host_id' => $this->host->id,
        'time_slot_id' => $this->slot->id,
        'is_template' => false,
        'schedule_date' => '2026-04-20',
        'day_of_week' => Carbon::parse('2026-04-20')->dayOfWeek,
    ]);
    $this->session = LiveSession::where('live_schedule_assignment_id', $this->assignment->id)->firstOrFail();
});

afterEach(fn () => Carbon::setTestNow());

function lockWithUnpublishedGmv(): ActualLiveRecord
{
    // A settled live still carrying TikTok's -1 "GMV unavailable" sentinel.
    $live = ActualLiveRecord::factory()->apiSync()->create([
        'platform_account_id' => test()->platform->id,
        'creator_platform_user_id' => '900900900',
        'creator_handle' => 'someshop',
        'launched_time' => '2026-04-20 09:00:00',
        'ended_time' => '2026-04-20 10:30:00',
        'duration_seconds' => 5400,
        'live_attributed_gmv_myr' => -1.00,
        'viewers' => 12000,
    ]);

    // Simulate the OLD buggy auto-verify having locked it at RM0.
    test()->session->actualLiveRecords()->sync([$live->id => [
        'is_primary' => true,
        'live_attributed_gmv_myr' => 0,
        'linked_at' => now(),
    ]]);
    test()->session->update([
        'matched_actual_live_record_id' => $live->id,
        'gmv_amount' => 0,
        'gmv_source' => 'tiktok_actual',
        'gmv_locked_at' => now(),
        'verification_status' => 'verified',
        'auto_verified' => true,
        'status' => 'ended',
    ]);

    return $live;
}

it('dry-run lists the fake-RM0 session without changing it', function () {
    lockWithUnpublishedGmv();

    $this->artisan('livehost:revert-unpublished-gmv')->assertSuccessful();

    expect($this->session->refresh()->verification_status)->toBe('verified');
});

it('reverts a false-RM0 (unpublished GMV) session to pending with --apply', function () {
    lockWithUnpublishedGmv();

    $this->artisan('livehost:revert-unpublished-gmv --apply')->assertSuccessful();

    $this->session->refresh();
    expect($this->session->verification_status)->toBe('pending');
    expect($this->session->auto_verified)->toBeFalsy();
    expect((float) $this->session->gmv_amount)->toBe(0.0);
    expect($this->session->actualLiveRecords()->count())->toBe(0);
});

it('does NOT revert a session with real published GMV', function () {
    ActualLiveRecord::factory()->apiSync()->create([
        'platform_account_id' => $this->platform->id,
        'creator_platform_user_id' => '900900900',
        'creator_handle' => 'someshop',
        'launched_time' => '2026-04-20 09:00:00',
        'ended_time' => '2026-04-20 10:30:00',
        'duration_seconds' => 5400,
        'live_attributed_gmv_myr' => 1500.00,
        'viewers' => 12000,
    ]);
    app(AutoVerifyService::class)->verifyIfClear($this->session->refresh());
    expect($this->session->refresh()->verification_status)->toBe('verified');

    $this->artisan('livehost:revert-unpublished-gmv --apply')->assertSuccessful();

    expect($this->session->refresh()->verification_status)->toBe('verified');
    expect((float) $this->session->gmv_amount)->toBe(1500.0);
});
