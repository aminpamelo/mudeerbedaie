<?php

use App\Models\ActualLiveRecord;
use App\Models\LiveScheduleAssignment;
use App\Models\LiveSession;
use App\Models\PlatformAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->pic = User::factory()->create(['role' => 'admin_livehost']);
    $this->host = User::factory()->create(['role' => 'live_host']);
    $this->account = PlatformAccount::factory()->create();
    $this->assignment = LiveScheduleAssignment::factory()->create([
        'platform_account_id' => $this->account->id,
        'live_host_id' => $this->host->id,
        'is_template' => false,
        'schedule_date' => '2026-04-20',
        'day_of_week' => 1,
    ]);
    $this->live = ActualLiveRecord::factory()->create([
        'platform_account_id' => $this->account->id,
        'launched_time' => '2026-04-20 09:00:00',
        'ended_time' => '2026-04-20 11:00:00',
        'live_attributed_gmv_myr' => 1500.00,
        'viewers' => 4200,
    ]);
});

it('links a TikTok live to a slot, creating a verified session with the live GMV', function () {
    actingAs($this->pic)
        ->post('/livehost/session-slots/link-live', [
            'assignment_id' => $this->assignment->id,
            'actual_live_record_id' => $this->live->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $session = LiveSession::where('live_schedule_assignment_id', $this->assignment->id)->firstOrFail();
    expect($session->verification_status)->toBe('verified');
    expect((float) $session->gmv_amount)->toBe(1500.0);
    expect($session->gmv_source)->toBe('tiktok_actual');
    expect($session->matched_actual_live_record_id)->toBe($this->live->id);
    expect((bool) $session->auto_verified)->toBeFalse();

    // The record is linked via the pivot.
    $this->assertDatabaseHas('live_session_actual_live_record', [
        'live_session_id' => $session->id,
        'actual_live_record_id' => $this->live->id,
    ]);
});

it('accumulates a second live onto the same slot (split live), summing GMV', function () {
    // First live linked.
    actingAs($this->pic)->post('/livehost/session-slots/link-live', [
        'assignment_id' => $this->assignment->id,
        'actual_live_record_id' => $this->live->id,
    ])->assertSessionHas('success');

    // Same host went live again (violation → resumed) — a second record.
    $second = ActualLiveRecord::factory()->create([
        'platform_account_id' => $this->account->id,
        'launched_time' => '2026-04-20 12:00:00',
        'ended_time' => '2026-04-20 13:00:00',
        'live_attributed_gmv_myr' => 800.00,
    ]);

    actingAs($this->pic)->post('/livehost/session-slots/link-live', [
        'assignment_id' => $this->assignment->id,
        'actual_live_record_id' => $second->id,
    ])->assertSessionHas('success');

    $session = LiveSession::where('live_schedule_assignment_id', $this->assignment->id)->firstOrFail();
    // Both records linked, GMV summed, not replaced.
    expect($session->actualLiveRecords()->count())->toBe(2);
    expect((float) $session->gmv_amount)->toBe(2300.0); // 1500 + 800
});

it('forbids a live_host from linking', function () {
    actingAs($this->host)
        ->post('/livehost/session-slots/link-live', [
            'assignment_id' => $this->assignment->id,
            'actual_live_record_id' => $this->live->id,
        ])
        ->assertForbidden();
});

it('errors when the slot has no host assigned', function () {
    $noHost = LiveScheduleAssignment::factory()->create([
        'platform_account_id' => $this->account->id,
        'live_host_id' => null,
        'is_template' => false,
        'schedule_date' => '2026-04-20',
    ]);

    actingAs($this->pic)
        ->post('/livehost/session-slots/link-live', [
            'assignment_id' => $noHost->id,
            'actual_live_record_id' => $this->live->id,
        ])
        ->assertSessionHasErrors('link');

    // A dated assignment auto-gets a session (observer); the link must NOT have
    // verified it.
    $session = LiveSession::where('live_schedule_assignment_id', $noHost->id)->first();
    expect($session?->verification_status)->not->toBe('verified');
});

it('errors when the live is already linked to another session', function () {
    // First link succeeds.
    actingAs($this->pic)->post('/livehost/session-slots/link-live', [
        'assignment_id' => $this->assignment->id,
        'actual_live_record_id' => $this->live->id,
    ])->assertSessionHas('success');

    // A second slot cannot claim the same live.
    $other = LiveScheduleAssignment::factory()->create([
        'platform_account_id' => $this->account->id,
        'live_host_id' => $this->host->id,
        'is_template' => false,
        'schedule_date' => '2026-04-20',
    ]);

    actingAs($this->pic)->post('/livehost/session-slots/link-live', [
        'assignment_id' => $other->id,
        'actual_live_record_id' => $this->live->id,
    ])->assertSessionHasErrors('link');
});
