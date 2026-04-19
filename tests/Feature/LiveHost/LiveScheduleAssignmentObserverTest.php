<?php

use App\Models\LiveScheduleAssignment;
use App\Models\LiveSession;
use App\Models\LiveTimeSlot;
use App\Models\PlatformAccount;
use App\Models\User;

beforeEach(function () {
    $this->host = User::factory()->create(['role' => 'live_host']);
    $this->account = PlatformAccount::factory()->create();
    $this->slot = LiveTimeSlot::factory()->create([
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
    ]);
});

it('creates a LiveSession when a dated assignment is created', function () {
    $assignment = LiveScheduleAssignment::create([
        'platform_account_id' => $this->account->id,
        'time_slot_id' => $this->slot->id,
        'live_host_id' => $this->host->id,
        'day_of_week' => 1,
        'schedule_date' => '2026-04-20',
        'is_template' => false,
        'status' => 'scheduled',
    ]);

    $session = LiveSession::where('live_schedule_assignment_id', $assignment->id)->first();

    expect($session)->not->toBeNull()
        ->and($session->live_host_id)->toBe($this->host->id)
        ->and($session->platform_account_id)->toBe($this->account->id)
        ->and($session->scheduled_start_at->format('Y-m-d H:i'))->toBe('2026-04-20 09:00')
        ->and($session->duration_minutes)->toBe(120)
        ->and($session->status)->toBe('scheduled');
});

it('does not create a LiveSession for template assignments', function () {
    $assignment = LiveScheduleAssignment::create([
        'platform_account_id' => $this->account->id,
        'time_slot_id' => $this->slot->id,
        'live_host_id' => $this->host->id,
        'day_of_week' => 1,
        'schedule_date' => null,
        'is_template' => true,
        'status' => 'scheduled',
    ]);

    expect(LiveSession::where('live_schedule_assignment_id', $assignment->id)->exists())->toBeFalse();
});

it('updates the linked LiveSession when assignment is updated', function () {
    $assignment = LiveScheduleAssignment::create([
        'platform_account_id' => $this->account->id,
        'time_slot_id' => $this->slot->id,
        'live_host_id' => $this->host->id,
        'day_of_week' => 1,
        'schedule_date' => '2026-04-20',
        'is_template' => false,
        'status' => 'scheduled',
    ]);

    $otherHost = User::factory()->create(['role' => 'live_host']);
    $assignment->update(['live_host_id' => $otherHost->id, 'status' => 'confirmed']);

    $session = LiveSession::where('live_schedule_assignment_id', $assignment->id)->first();

    expect($session->live_host_id)->toBe($otherHost->id)
        ->and($session->status)->toBe('scheduled');
});

it('maps in_progress assignment status to live session status', function () {
    $assignment = LiveScheduleAssignment::create([
        'platform_account_id' => $this->account->id,
        'time_slot_id' => $this->slot->id,
        'live_host_id' => $this->host->id,
        'day_of_week' => 1,
        'schedule_date' => '2026-04-20',
        'is_template' => false,
        'status' => 'in_progress',
    ]);

    $session = LiveSession::where('live_schedule_assignment_id', $assignment->id)->first();

    expect($session->status)->toBe('live');
});

it('removes scheduled LiveSession when assignment is deleted', function () {
    $assignment = LiveScheduleAssignment::create([
        'platform_account_id' => $this->account->id,
        'time_slot_id' => $this->slot->id,
        'live_host_id' => $this->host->id,
        'day_of_week' => 1,
        'schedule_date' => '2026-04-20',
        'is_template' => false,
        'status' => 'scheduled',
    ]);

    $sessionId = LiveSession::where('live_schedule_assignment_id', $assignment->id)->value('id');

    $assignment->delete();

    expect(LiveSession::find($sessionId))->toBeNull();
});

it('removes scheduled LiveSession when assignment flips to template', function () {
    $assignment = LiveScheduleAssignment::create([
        'platform_account_id' => $this->account->id,
        'time_slot_id' => $this->slot->id,
        'live_host_id' => $this->host->id,
        'day_of_week' => 1,
        'schedule_date' => '2026-04-20',
        'is_template' => false,
        'status' => 'scheduled',
    ]);

    $assignment->update(['is_template' => true, 'schedule_date' => null]);

    expect(LiveSession::where('live_schedule_assignment_id', $assignment->id)->exists())->toBeFalse();
});
