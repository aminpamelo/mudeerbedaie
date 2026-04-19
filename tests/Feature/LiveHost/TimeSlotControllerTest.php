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

it('lists time slots with pagination (15 per page)', function () {
    LiveTimeSlot::factory()->count(20)->create();

    actingAs($this->pic)
        ->get('/livehost/time-slots')
        ->assertInertia(fn (Assert $p) => $p
            ->component('time-slots/Index', false)
            ->has('timeSlots.data', 15)
            ->has('timeSlots.links')
            ->has('filters')
            ->has('platformAccounts'));
});

it('maps time slot DTO fields', function () {
    $account = PlatformAccount::factory()->create(['name' => 'TikTok Main']);

    LiveTimeSlot::factory()->create([
        'platform_account_id' => $account->id,
        'day_of_week' => 3,
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
        'is_active' => true,
        'sort_order' => 2,
    ]);

    actingAs($this->pic)
        ->get('/livehost/time-slots')
        ->assertInertia(fn (Assert $p) => $p
            ->has('timeSlots.data', 1)
            ->where('timeSlots.data.0.dayOfWeek', 3)
            ->where('timeSlots.data.0.dayName', 'Wednesday')
            ->where('timeSlots.data.0.platformAccount', 'TikTok Main')
            ->where('timeSlots.data.0.startTime', '09:00')
            ->where('timeSlots.data.0.endTime', '11:00')
            ->where('timeSlots.data.0.isActive', true)
            ->where('timeSlots.data.0.sortOrder', 2)
            ->etc());
});

it('filters time slots by platform account', function () {
    $accountA = PlatformAccount::factory()->create();
    $accountB = PlatformAccount::factory()->create();
    LiveTimeSlot::factory()->count(2)->create(['platform_account_id' => $accountA->id]);
    LiveTimeSlot::factory()->count(4)->create(['platform_account_id' => $accountB->id]);

    actingAs($this->pic)
        ->get("/livehost/time-slots?platform_account={$accountA->id}")
        ->assertInertia(fn (Assert $p) => $p->has('timeSlots.data', 2));
});

it('filters time slots for global (null) platform account', function () {
    LiveTimeSlot::factory()->count(3)->create(['platform_account_id' => null]);
    LiveTimeSlot::factory()->count(4)->create([
        'platform_account_id' => PlatformAccount::factory(),
    ]);

    actingAs($this->pic)
        ->get('/livehost/time-slots?platform_account=global')
        ->assertInertia(fn (Assert $p) => $p->has('timeSlots.data', 3));
});

it('filters time slots by day_of_week', function () {
    LiveTimeSlot::factory()->count(2)->create(['day_of_week' => 1]);
    LiveTimeSlot::factory()->count(5)->create(['day_of_week' => 5]);

    actingAs($this->pic)
        ->get('/livehost/time-slots?day_of_week=5')
        ->assertInertia(fn (Assert $p) => $p->has('timeSlots.data', 5));
});

it('filters time slots by active flag', function () {
    LiveTimeSlot::factory()->count(3)->create(['is_active' => true]);
    LiveTimeSlot::factory()->count(4)->create(['is_active' => false]);

    actingAs($this->pic)
        ->get('/livehost/time-slots?active=false')
        ->assertInertia(fn (Assert $p) => $p->has('timeSlots.data', 4));
});

it('renders the time slot create form with dropdown data', function () {
    PlatformAccount::factory()->count(3)->create();

    actingAs($this->pic)
        ->get('/livehost/time-slots/create')
        ->assertInertia(fn (Assert $p) => $p
            ->component('time-slots/Create', false)
            ->has('platformAccounts', 3));
});

it('creates a new time slot', function () {
    $account = PlatformAccount::factory()->create();

    actingAs($this->pic)
        ->post('/livehost/time-slots', [
            'platform_account_id' => $account->id,
            'day_of_week' => 2,
            'start_time' => '14:00',
            'end_time' => '16:00',
            'is_active' => true,
            'sort_order' => 3,
        ])
        ->assertRedirect('/livehost/time-slots')
        ->assertSessionHas('success');

    $created = LiveTimeSlot::latest('id')->first();
    expect($created)->not->toBeNull();
    expect($created->platform_account_id)->toBe($account->id);
    expect($created->day_of_week)->toBe(2);
    expect($created->is_active)->toBeTrue();
    expect($created->sort_order)->toBe(3);
    expect($created->created_by)->toBe($this->pic->id);
});

it('creates a global time slot with null platform_account_id and day_of_week', function () {
    actingAs($this->pic)
        ->post('/livehost/time-slots', [
            'platform_account_id' => '',
            'day_of_week' => '',
            'start_time' => '09:00',
            'end_time' => '11:00',
            'is_active' => true,
        ])
        ->assertRedirect('/livehost/time-slots');

    $created = LiveTimeSlot::latest('id')->first();
    expect($created->platform_account_id)->toBeNull();
    expect($created->day_of_week)->toBeNull();
});

it('rejects time slot create with missing required fields', function () {
    actingAs($this->pic)
        ->post('/livehost/time-slots', [])
        ->assertSessionHasErrors(['start_time', 'end_time']);
});

it('rejects time slot create with end_time before start_time', function () {
    actingAs($this->pic)
        ->post('/livehost/time-slots', [
            'start_time' => '16:00',
            'end_time' => '14:00',
        ])
        ->assertSessionHasErrors('end_time');
});

it('rejects time slot create with out-of-range day_of_week', function () {
    actingAs($this->pic)
        ->post('/livehost/time-slots', [
            'day_of_week' => 9,
            'start_time' => '09:00',
            'end_time' => '11:00',
        ])
        ->assertSessionHasErrors('day_of_week');
});

it('renders edit form pre-filled with time slot data', function () {
    $slot = LiveTimeSlot::factory()->create([
        'day_of_week' => 4,
        'start_time' => '10:00:00',
        'end_time' => '12:00:00',
    ]);

    actingAs($this->pic)
        ->get("/livehost/time-slots/{$slot->id}/edit")
        ->assertInertia(fn (Assert $p) => $p
            ->component('time-slots/Edit', false)
            ->where('timeSlot.id', $slot->id)
            ->where('timeSlot.day_of_week', 4)
            ->where('timeSlot.start_time', '10:00')
            ->where('timeSlot.end_time', '12:00')
            ->has('platformAccounts'));
});

it('updates a time slot', function () {
    $slot = LiveTimeSlot::factory()->create([
        'day_of_week' => 1,
        'is_active' => true,
    ]);
    $newAccount = PlatformAccount::factory()->create();

    actingAs($this->pic)
        ->put("/livehost/time-slots/{$slot->id}", [
            'platform_account_id' => $newAccount->id,
            'day_of_week' => 5,
            'start_time' => '20:00',
            'end_time' => '22:00',
            'is_active' => false,
            'sort_order' => 7,
        ])
        ->assertRedirect('/livehost/time-slots')
        ->assertSessionHas('success');

    expect($slot->fresh())
        ->platform_account_id->toBe($newAccount->id)
        ->day_of_week->toBe(5)
        ->is_active->toBeFalse()
        ->sort_order->toBe(7);
});

it('deletes a time slot with no assignments', function () {
    $slot = LiveTimeSlot::factory()->create();

    actingAs($this->pic)
        ->delete("/livehost/time-slots/{$slot->id}")
        ->assertRedirect('/livehost/time-slots')
        ->assertSessionHas('success');

    expect(LiveTimeSlot::find($slot->id))->toBeNull();
});

it('blocks deleting a time slot still referenced by schedule assignments', function () {
    $slot = LiveTimeSlot::factory()->create();
    LiveScheduleAssignment::create([
        'platform_account_id' => PlatformAccount::factory()->create()->id,
        'time_slot_id' => $slot->id,
        'day_of_week' => 1,
        'is_template' => true,
        'status' => 'scheduled',
    ]);

    actingAs($this->pic)
        ->delete("/livehost/time-slots/{$slot->id}")
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(LiveTimeSlot::find($slot->id))->not->toBeNull();
});

it('forbids live_host from accessing the time slots index', function () {
    $host = User::factory()->create(['role' => 'live_host']);

    $this->actingAs($host)
        ->get('/livehost/time-slots')
        ->assertForbidden();
});

it('forbids live_host from creating a time slot', function () {
    $host = User::factory()->create(['role' => 'live_host']);

    $this->actingAs($host)
        ->post('/livehost/time-slots', [
            'start_time' => '09:00',
            'end_time' => '11:00',
        ])
        ->assertForbidden();
});
