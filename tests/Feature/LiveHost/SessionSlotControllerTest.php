<?php

use App\Models\LiveScheduleAssignment;
use App\Models\LiveTimeSlot;
use App\Models\PlatformAccount;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->pic = User::factory()->create(['role' => 'admin_livehost']);
});

it('lists session slots with pagination (15 per page)', function () {
    LiveScheduleAssignment::factory()->count(20)->create();

    actingAs($this->pic)
        ->get('/livehost/session-slots')
        ->assertInertia(fn (Assert $p) => $p
            ->component('session-slots/Index', false)
            ->has('sessionSlots.data', 15)
            ->has('sessionSlots.links')
            ->has('filters')
            ->has('hosts')
            ->has('platformAccounts'));
});

it('maps session slot DTO fields', function () {
    $host = User::factory()->create(['role' => 'live_host', 'name' => 'Host One']);
    $account = PlatformAccount::factory()->create(['name' => 'TikTok Main']);
    $slot = LiveTimeSlot::factory()->create([
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
    ]);

    LiveScheduleAssignment::factory()->create([
        'platform_account_id' => $account->id,
        'time_slot_id' => $slot->id,
        'live_host_id' => $host->id,
        'day_of_week' => 3,
        'is_template' => true,
        'status' => 'confirmed',
        'remarks' => 'Flagship slot',
    ]);

    actingAs($this->pic)
        ->get('/livehost/session-slots')
        ->assertInertia(fn (Assert $p) => $p
            ->has('sessionSlots.data', 1)
            ->where('sessionSlots.data.0.dayOfWeek', 3)
            ->where('sessionSlots.data.0.dayName', 'Wednesday')
            ->where('sessionSlots.data.0.platformAccount', 'TikTok Main')
            ->where('sessionSlots.data.0.hostName', 'Host One')
            ->where('sessionSlots.data.0.startTime', '09:00')
            ->where('sessionSlots.data.0.endTime', '11:00')
            ->where('sessionSlots.data.0.timeSlotLabel', '09:00–11:00')
            ->where('sessionSlots.data.0.isTemplate', true)
            ->where('sessionSlots.data.0.status', 'confirmed')
            ->where('sessionSlots.data.0.remarks', 'Flagship slot')
            ->etc());
});

it('filters session slots by host', function () {
    $hostA = User::factory()->create(['role' => 'live_host']);
    $hostB = User::factory()->create(['role' => 'live_host']);
    LiveScheduleAssignment::factory()->count(2)->create(['live_host_id' => $hostA->id]);
    LiveScheduleAssignment::factory()->count(3)->create(['live_host_id' => $hostB->id]);

    actingAs($this->pic)
        ->get("/livehost/session-slots?host={$hostA->id}")
        ->assertInertia(fn (Assert $p) => $p
            ->has('sessionSlots.data', 2)
            ->where('filters.host', (string) $hostA->id));
});

it('filters session slots by unassigned host', function () {
    $host = User::factory()->create(['role' => 'live_host']);
    LiveScheduleAssignment::factory()->count(2)->create(['live_host_id' => $host->id]);
    LiveScheduleAssignment::factory()->count(3)->create(['live_host_id' => null]);

    actingAs($this->pic)
        ->get('/livehost/session-slots?host=unassigned')
        ->assertInertia(fn (Assert $p) => $p->has('sessionSlots.data', 3));
});

it('filters session slots by platform_account', function () {
    $accountA = PlatformAccount::factory()->create();
    $accountB = PlatformAccount::factory()->create();
    LiveScheduleAssignment::factory()->count(2)->create(['platform_account_id' => $accountA->id]);
    LiveScheduleAssignment::factory()->count(4)->create(['platform_account_id' => $accountB->id]);

    actingAs($this->pic)
        ->get("/livehost/session-slots?platform_account={$accountA->id}")
        ->assertInertia(fn (Assert $p) => $p->has('sessionSlots.data', 2));
});

it('filters session slots by status', function () {
    LiveScheduleAssignment::factory()->count(2)->create(['status' => 'confirmed']);
    LiveScheduleAssignment::factory()->count(3)->create(['status' => 'cancelled']);

    actingAs($this->pic)
        ->get('/livehost/session-slots?status=cancelled')
        ->assertInertia(fn (Assert $p) => $p->has('sessionSlots.data', 3));
});

it('filters session slots by template mode', function () {
    LiveScheduleAssignment::factory()->count(3)->template()->create();
    LiveScheduleAssignment::factory()->count(2)->forDate('2026-05-01')->create();

    actingAs($this->pic)
        ->get('/livehost/session-slots?mode=dated')
        ->assertInertia(fn (Assert $p) => $p->has('sessionSlots.data', 2));
});

it('renders the session slot create form with dropdown data', function () {
    User::factory()->count(2)->create(['role' => 'live_host']);
    PlatformAccount::factory()->count(3)->create();
    LiveTimeSlot::factory()->count(4)->create(['is_active' => true]);
    LiveTimeSlot::factory()->create(['is_active' => false]);

    actingAs($this->pic)
        ->get('/livehost/session-slots/create')
        ->assertInertia(fn (Assert $p) => $p
            ->component('session-slots/Create', false)
            ->has('hosts', 2)
            ->has('platformAccounts', 3)
            ->has('timeSlots', 4));
});

it('creates a new session slot', function () {
    $host = User::factory()->create(['role' => 'live_host']);
    $account = PlatformAccount::factory()->create();
    $slot = LiveTimeSlot::factory()->create();

    actingAs($this->pic)
        ->post('/livehost/session-slots', [
            'platform_account_id' => $account->id,
            'time_slot_id' => $slot->id,
            'live_host_id' => $host->id,
            'day_of_week' => 2,
            'is_template' => true,
            'status' => 'confirmed',
            'remarks' => 'Tuesday flagship',
        ])
        ->assertRedirect('/livehost/session-slots')
        ->assertSessionHas('success');

    $created = LiveScheduleAssignment::latest('id')->first();
    expect($created)->not->toBeNull();
    expect($created->platform_account_id)->toBe($account->id);
    expect($created->time_slot_id)->toBe($slot->id);
    expect($created->live_host_id)->toBe($host->id);
    expect($created->day_of_week)->toBe(2);
    expect($created->is_template)->toBeTrue();
    expect($created->status)->toBe('confirmed');
    expect($created->remarks)->toBe('Tuesday flagship');
    expect($created->created_by)->toBe($this->pic->id);
});

it('creates a session slot with no host assigned', function () {
    $account = PlatformAccount::factory()->create();
    $slot = LiveTimeSlot::factory()->create();

    actingAs($this->pic)
        ->post('/livehost/session-slots', [
            'platform_account_id' => $account->id,
            'time_slot_id' => $slot->id,
            'live_host_id' => null,
            'day_of_week' => 0,
            'is_template' => true,
        ])
        ->assertRedirect('/livehost/session-slots')
        ->assertSessionHas('success');

    $created = LiveScheduleAssignment::latest('id')->first();
    expect($created->live_host_id)->toBeNull();
    expect($created->status)->toBe('scheduled');
});

it('rejects session slot create with missing required fields', function () {
    actingAs($this->pic)
        ->post('/livehost/session-slots', [])
        ->assertSessionHasErrors(['platform_account_id', 'time_slot_id', 'day_of_week']);
});

it('rejects session slot create with out-of-range day_of_week', function () {
    $account = PlatformAccount::factory()->create();
    $slot = LiveTimeSlot::factory()->create();

    actingAs($this->pic)
        ->post('/livehost/session-slots', [
            'platform_account_id' => $account->id,
            'time_slot_id' => $slot->id,
            'day_of_week' => 9,
        ])
        ->assertSessionHasErrors('day_of_week');
});

it('rejects session slot create with invalid status', function () {
    $account = PlatformAccount::factory()->create();
    $slot = LiveTimeSlot::factory()->create();

    actingAs($this->pic)
        ->post('/livehost/session-slots', [
            'platform_account_id' => $account->id,
            'time_slot_id' => $slot->id,
            'day_of_week' => 1,
            'status' => 'bogus_status',
        ])
        ->assertSessionHasErrors('status');
});

it('shows a session slot detail page', function () {
    $assignment = LiveScheduleAssignment::factory()->create([
        'day_of_week' => 4,
        'status' => 'confirmed',
        'remarks' => 'Important slot',
    ]);

    actingAs($this->pic)
        ->get("/livehost/session-slots/{$assignment->id}")
        ->assertInertia(fn (Assert $p) => $p
            ->component('session-slots/Show', false)
            ->where('sessionSlot.id', $assignment->id)
            ->where('sessionSlot.status', 'confirmed')
            ->where('sessionSlot.remarks', 'Important slot')
            ->has('sessionSlot.dayName'));
});

it('renders edit form pre-filled with session slot data', function () {
    $assignment = LiveScheduleAssignment::factory()->create([
        'day_of_week' => 5,
        'status' => 'confirmed',
        'remarks' => 'Friday prime time',
    ]);

    actingAs($this->pic)
        ->get("/livehost/session-slots/{$assignment->id}/edit")
        ->assertInertia(fn (Assert $p) => $p
            ->component('session-slots/Edit', false)
            ->where('sessionSlot.id', $assignment->id)
            ->where('sessionSlot.day_of_week', 5)
            ->where('sessionSlot.status', 'confirmed')
            ->where('sessionSlot.remarks', 'Friday prime time')
            ->has('hosts')
            ->has('platformAccounts')
            ->has('timeSlots'));
});

it('updates a session slot', function () {
    $assignment = LiveScheduleAssignment::factory()->create([
        'day_of_week' => 1,
        'status' => 'scheduled',
    ]);
    $newAccount = PlatformAccount::factory()->create();
    $newSlot = LiveTimeSlot::factory()->create();
    $newHost = User::factory()->create(['role' => 'live_host']);

    actingAs($this->pic)
        ->put("/livehost/session-slots/{$assignment->id}", [
            'platform_account_id' => $newAccount->id,
            'time_slot_id' => $newSlot->id,
            'live_host_id' => $newHost->id,
            'day_of_week' => 5,
            'is_template' => true,
            'status' => 'confirmed',
            'remarks' => 'Updated note',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($assignment->fresh())
        ->platform_account_id->toBe($newAccount->id)
        ->time_slot_id->toBe($newSlot->id)
        ->live_host_id->toBe($newHost->id)
        ->day_of_week->toBe(5)
        ->status->toBe('confirmed')
        ->remarks->toBe('Updated note');
});

it('deletes a session slot', function () {
    $assignment = LiveScheduleAssignment::factory()->create();

    actingAs($this->pic)
        ->delete("/livehost/session-slots/{$assignment->id}")
        ->assertRedirect('/livehost/session-slots')
        ->assertSessionHas('success');

    expect(LiveScheduleAssignment::find($assignment->id))->toBeNull();
});

it('forbids live_host from accessing the session slots index', function () {
    $host = User::factory()->create(['role' => 'live_host']);

    $this->actingAs($host)
        ->get('/livehost/session-slots')
        ->assertForbidden();
});

it('forbids live_host from creating a session slot', function () {
    $host = User::factory()->create(['role' => 'live_host']);
    $account = PlatformAccount::factory()->create();
    $slot = LiveTimeSlot::factory()->create();

    $this->actingAs($host)
        ->post('/livehost/session-slots', [
            'platform_account_id' => $account->id,
            'time_slot_id' => $slot->id,
            'day_of_week' => 1,
        ])
        ->assertForbidden();
});
