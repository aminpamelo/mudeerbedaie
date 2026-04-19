<?php

use App\Models\LiveHostPlatformAccount;
use App\Models\LiveScheduleAssignment;
use App\Models\LiveSession;
use App\Models\LiveTimeSlot;
use App\Models\PlatformAccount;
use App\Models\User;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->pic = User::factory()->create(['role' => 'admin_livehost']);
    $this->host = User::factory()->create(['role' => 'live_host']);
    $this->account = PlatformAccount::factory()->create();
    $this->timeSlot = LiveTimeSlot::factory()->create([
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
    ]);
    $this->pivot = LiveHostPlatformAccount::create([
        'user_id' => $this->host->id,
        'platform_account_id' => $this->account->id,
        'creator_handle' => '@amar',
        'creator_platform_user_id' => '6526684195492729856',
        'is_primary' => true,
    ]);
});

it('rejects a new session slot without live_host_platform_account_id', function () {
    actingAs($this->pic)
        ->post('/livehost/session-slots', [
            'platform_account_id' => $this->account->id,
            'time_slot_id' => $this->timeSlot->id,
            'live_host_id' => $this->host->id,
            'day_of_week' => 2,
            'is_template' => true,
        ])
        ->assertSessionHasErrors('live_host_platform_account_id');
});

it('rejects a new session slot with a non-existent live_host_platform_account_id', function () {
    actingAs($this->pic)
        ->post('/livehost/session-slots', [
            'platform_account_id' => $this->account->id,
            'time_slot_id' => $this->timeSlot->id,
            'live_host_id' => $this->host->id,
            'live_host_platform_account_id' => 999999,
            'day_of_week' => 2,
            'is_template' => true,
        ])
        ->assertSessionHasErrors('live_host_platform_account_id');
});

it('creates a session slot with pivot id and persists it', function () {
    actingAs($this->pic)
        ->post('/livehost/session-slots', [
            'platform_account_id' => $this->account->id,
            'time_slot_id' => $this->timeSlot->id,
            'live_host_id' => $this->host->id,
            'live_host_platform_account_id' => $this->pivot->id,
            'day_of_week' => 2,
            'is_template' => true,
            'status' => 'confirmed',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $created = LiveScheduleAssignment::latest('id')->first();
    expect($created)->not->toBeNull()
        ->and($created->live_host_platform_account_id)->toBe($this->pivot->id);
});

it('auto-propagates live_host_platform_account_id to LiveSession when slot materialises', function () {
    $assignment = LiveScheduleAssignment::create([
        'platform_account_id' => $this->account->id,
        'time_slot_id' => $this->timeSlot->id,
        'live_host_id' => $this->host->id,
        'live_host_platform_account_id' => $this->pivot->id,
        'day_of_week' => 1,
        'schedule_date' => '2026-04-25',
        'is_template' => false,
        'status' => 'scheduled',
    ]);

    $session = LiveSession::where('live_schedule_assignment_id', $assignment->id)->first();

    expect($session)->not->toBeNull()
        ->and($session->live_host_platform_account_id)->toBe($this->pivot->id);
});

it('allows updating an existing session slot without a pivot id (legacy migration path)', function () {
    $assignment = LiveScheduleAssignment::create([
        'platform_account_id' => $this->account->id,
        'time_slot_id' => $this->timeSlot->id,
        'live_host_id' => $this->host->id,
        'live_host_platform_account_id' => null,
        'day_of_week' => 1,
        'is_template' => true,
        'status' => 'scheduled',
    ]);

    actingAs($this->pic)
        ->put("/livehost/session-slots/{$assignment->id}", [
            'platform_account_id' => $this->account->id,
            'time_slot_id' => $this->timeSlot->id,
            'live_host_id' => $this->host->id,
            'day_of_week' => 1,
            'is_template' => true,
            'status' => 'confirmed',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($assignment->fresh()->status)->toBe('confirmed');
});

it('session-slot create page exposes host-platform pivot options to the UI', function () {
    $response = actingAs($this->pic)
        ->get('/livehost/session-slots/create');

    $response->assertInertia(fn ($page) => $page
        ->component('session-slots/Create', false)
        ->has('hostPlatformPivots', 1)
        ->where('hostPlatformPivots.0.id', $this->pivot->id)
        ->where('hostPlatformPivots.0.userId', $this->host->id)
        ->where('hostPlatformPivots.0.platformAccountId', $this->account->id)
        ->where('hostPlatformPivots.0.creatorHandle', '@amar')
        ->where('hostPlatformPivots.0.isPrimary', true)
        ->etc());
});
