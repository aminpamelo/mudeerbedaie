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
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-04-21 20:00:00');

    $this->platform = PlatformAccount::factory()->create();
    $this->account = LiveAccount::factory()->create(['creator_user_id' => '900900900']);
    $this->hostA = User::factory()->create(['role' => 'live_host', 'name' => 'Host A Morning']);
    $this->hostB = User::factory()->create(['role' => 'live_host', 'name' => 'Host B Evening']);
    $this->morning = LiveTimeSlot::factory()->create(['start_time' => '09:00:00', 'end_time' => '11:00:00']);
    $this->evening = LiveTimeSlot::factory()->create(['start_time' => '20:00:00', 'end_time' => '22:00:00']);

    $this->assignA = LiveScheduleAssignment::factory()->create([
        'platform_account_id' => $this->platform->id, 'live_account_id' => $this->account->id,
        'live_host_id' => $this->hostA->id, 'time_slot_id' => $this->morning->id,
        'is_template' => false, 'schedule_date' => '2026-04-20', 'day_of_week' => Carbon::parse('2026-04-20')->dayOfWeek,
    ]);
    $this->assignB = LiveScheduleAssignment::factory()->create([
        'platform_account_id' => $this->platform->id, 'live_account_id' => $this->account->id,
        'live_host_id' => $this->hostB->id, 'time_slot_id' => $this->evening->id,
        'is_template' => false, 'schedule_date' => '2026-04-20', 'day_of_week' => Carbon::parse('2026-04-20')->dayOfWeek,
    ]);
    $this->sessionA = LiveSession::where('live_schedule_assignment_id', $this->assignA->id)->firstOrFail();
    $this->sessionB = LiveSession::where('live_schedule_assignment_id', $this->assignB->id)->firstOrFail();
});

afterEach(fn () => Carbon::setTestNow());

function attachVerified(LiveSession $session, ActualLiveRecord $live): void
{
    $session->actualLiveRecords()->attach($live->id, [
        'is_primary' => true, 'live_attributed_gmv_myr' => max(0, (float) $live->live_attributed_gmv_myr), 'linked_at' => now(),
    ]);
    $session->update([
        'matched_actual_live_record_id' => $live->id,
        'gmv_amount' => max(0, (float) $live->live_attributed_gmv_myr),
        'gmv_source' => 'tiktok_actual', 'verification_status' => 'verified',
        'auto_verified' => true, 'status' => 'ended',
        'actual_start_at' => $live->launched_time, 'actual_end_at' => $live->ended_time,
    ]);
}

function mkLive(array $o = []): ActualLiveRecord
{
    return ActualLiveRecord::factory()->apiSync()->create(array_merge([
        'platform_account_id' => test()->platform->id,
        'creator_platform_user_id' => '900900900', 'creator_handle' => 'someshop',
        'launched_time' => '2026-04-20 20:30:00', 'ended_time' => '2026-04-20 21:30:00',
        'duration_seconds' => 3600, 'live_attributed_gmv_myr' => 900.00, 'viewers' => 3000,
    ], $o));
}

function rebuild(): array
{
    return app(AutoVerifyService::class)
        ->auditRebuild(CarbonImmutable::parse('2026-04-20'), CarbonImmutable::parse('2026-04-20')->endOfDay(), true);
}

it('re-homes a trustworthy mis-attributed live to the correct host', function () {
    $live = mkLive(); // 20:30 → belongs to Host B, but linked to Host A
    attachVerified($this->sessionA, $live);

    $stats = rebuild();

    expect($stats['reattributed'])->toBe(1);
    expect($this->sessionA->refresh()->verification_status)->toBe('pending');
    $this->sessionB->refresh();
    expect($this->sessionB->verification_status)->toBe('verified');
    expect((float) $this->sessionB->gmv_amount)->toBe(900.0);
});

it('quarantines a placeholder-time live (collides with another distinct live)', function () {
    // Two DIFFERENT lives stamped with the same launch second — placeholder.
    $a = mkLive(['source_record_id' => '1111111111111111', 'launched_time' => '2026-04-20 20:30:00']);
    mkLive(['source_record_id' => '2222222222222222', 'launched_time' => '2026-04-20 20:30:00', 'live_attributed_gmv_myr' => 300]);
    attachVerified($this->sessionB, $a); // linked (correctly by slot, but time is fake)

    $stats = rebuild();

    expect($stats['quarantined_placeholder'])->toBe(1);
    // Unlinked → session reverts to pending; the fake-time credit is removed.
    $this->sessionB->refresh();
    expect($this->sessionB->actualLiveRecords()->count())->toBe(0);
    expect($this->sessionB->verification_status)->toBe('pending');
});

it('quarantines a live whose GMV is still unpublished (-1)', function () {
    $live = mkLive(['live_attributed_gmv_myr' => -1.00]);
    attachVerified($this->sessionB, $live);

    $stats = rebuild();

    expect($stats['quarantined_gmv'])->toBe(1);
    expect($this->sessionB->refresh()->actualLiveRecords()->count())->toBe(0);
});

it('leaves a correctly-attributed trustworthy live untouched (idempotent)', function () {
    $live = mkLive();
    attachVerified($this->sessionB, $live); // already on the correct host

    $stats = rebuild();

    expect($stats['reattributed'])->toBe(0);
    expect($stats['quarantined_placeholder'])->toBe(0);
    expect($this->sessionB->refresh()->verification_status)->toBe('verified');
});
