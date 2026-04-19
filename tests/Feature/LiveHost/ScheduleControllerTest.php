<?php

use App\Models\LiveSchedule;
use App\Models\LiveSession;
use App\Models\PlatformAccount;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->pic = User::factory()->create(['role' => 'admin_livehost']);
});

it('lists schedules with pagination (15 per page)', function () {
    LiveSchedule::factory()->count(20)->create();

    actingAs($this->pic)
        ->get('/livehost/schedules')
        ->assertInertia(fn (Assert $p) => $p
            ->component('schedules/Index', false)
            ->has('schedules.data', 15)
            ->has('schedules.links')
            ->has('filters')
            ->has('hosts')
            ->has('platformAccounts'));
});

it('maps schedule DTO fields', function () {
    $host = User::factory()->create(['role' => 'live_host', 'name' => 'Host One']);
    $account = PlatformAccount::factory()->create(['name' => 'TikTok Main']);
    LiveSchedule::factory()->create([
        'live_host_id' => $host->id,
        'platform_account_id' => $account->id,
        'day_of_week' => 3,
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
        'is_active' => true,
        'is_recurring' => true,
        'remarks' => 'Note here',
    ]);

    actingAs($this->pic)
        ->get('/livehost/schedules')
        ->assertInertia(fn (Assert $p) => $p
            ->has('schedules.data', 1)
            ->where('schedules.data.0.dayOfWeek', 3)
            ->where('schedules.data.0.dayName', 'Wednesday')
            ->where('schedules.data.0.isActive', true)
            ->where('schedules.data.0.isRecurring', true)
            ->where('schedules.data.0.hostName', 'Host One')
            ->where('schedules.data.0.platformAccount', 'TikTok Main')
            ->where('schedules.data.0.remarks', 'Note here')
            ->etc());
});

it('filters schedules by host', function () {
    $hostA = User::factory()->create(['role' => 'live_host']);
    $hostB = User::factory()->create(['role' => 'live_host']);
    LiveSchedule::factory()->count(2)->create(['live_host_id' => $hostA->id]);
    LiveSchedule::factory()->count(3)->create(['live_host_id' => $hostB->id]);

    actingAs($this->pic)
        ->get("/livehost/schedules?host={$hostA->id}")
        ->assertInertia(fn (Assert $p) => $p
            ->has('schedules.data', 2)
            ->where('filters.host', (string) $hostA->id));
});

it('filters schedules by platform_account', function () {
    $accountA = PlatformAccount::factory()->create();
    $accountB = PlatformAccount::factory()->create();
    LiveSchedule::factory()->count(2)->create(['platform_account_id' => $accountA->id]);
    LiveSchedule::factory()->count(4)->create(['platform_account_id' => $accountB->id]);

    actingAs($this->pic)
        ->get("/livehost/schedules?platform_account={$accountA->id}")
        ->assertInertia(fn (Assert $p) => $p->has('schedules.data', 2));
});

it('filters schedules by day_of_week', function () {
    LiveSchedule::factory()->count(2)->create(['day_of_week' => 1]);
    LiveSchedule::factory()->count(5)->create(['day_of_week' => 5]);

    actingAs($this->pic)
        ->get('/livehost/schedules?day_of_week=5')
        ->assertInertia(fn (Assert $p) => $p->has('schedules.data', 5));
});

it('filters schedules by active flag', function () {
    LiveSchedule::factory()->count(3)->create(['is_active' => true]);
    LiveSchedule::factory()->count(4)->create(['is_active' => false]);

    actingAs($this->pic)
        ->get('/livehost/schedules?active=false')
        ->assertInertia(fn (Assert $p) => $p->has('schedules.data', 4));
});

it('renders the schedule create form with dropdown data', function () {
    User::factory()->count(2)->create(['role' => 'live_host']);
    PlatformAccount::factory()->count(3)->create();

    actingAs($this->pic)
        ->get('/livehost/schedules/create')
        ->assertInertia(fn (Assert $p) => $p
            ->component('schedules/Create', false)
            ->has('hosts', 2)
            ->has('platformAccounts', 3));
});

it('creates a new schedule', function () {
    $host = User::factory()->create(['role' => 'live_host']);
    $account = PlatformAccount::factory()->create();

    actingAs($this->pic)
        ->post('/livehost/schedules', [
            'platform_account_id' => $account->id,
            'live_host_id' => $host->id,
            'day_of_week' => 2,
            'start_time' => '14:00',
            'end_time' => '16:00',
            'is_active' => true,
            'is_recurring' => true,
            'remarks' => 'Tuesday flagship',
        ])
        ->assertRedirect('/livehost/schedules')
        ->assertSessionHas('success');

    $created = LiveSchedule::latest('id')->first();
    expect($created)->not->toBeNull();
    expect($created->platform_account_id)->toBe($account->id);
    expect($created->live_host_id)->toBe($host->id);
    expect($created->day_of_week)->toBe(2);
    expect($created->is_active)->toBeTrue();
    expect($created->remarks)->toBe('Tuesday flagship');
    expect($created->created_by)->toBe($this->pic->id);
});

it('allows creating a schedule with no host assigned', function () {
    $account = PlatformAccount::factory()->create();

    actingAs($this->pic)
        ->post('/livehost/schedules', [
            'platform_account_id' => $account->id,
            'live_host_id' => null,
            'day_of_week' => 0,
            'start_time' => '09:00',
            'end_time' => '11:00',
            'is_active' => true,
            'is_recurring' => true,
        ])
        ->assertRedirect('/livehost/schedules')
        ->assertSessionHas('success');

    expect(LiveSchedule::latest('id')->first()->live_host_id)->toBeNull();
});

it('rejects schedule create with missing required fields', function () {
    actingAs($this->pic)
        ->post('/livehost/schedules', [])
        ->assertSessionHasErrors(['platform_account_id', 'day_of_week', 'start_time', 'end_time']);
});

it('rejects schedule create with end_time before start_time', function () {
    $account = PlatformAccount::factory()->create();

    actingAs($this->pic)
        ->post('/livehost/schedules', [
            'platform_account_id' => $account->id,
            'day_of_week' => 1,
            'start_time' => '16:00',
            'end_time' => '14:00',
        ])
        ->assertSessionHasErrors('end_time');
});

it('rejects schedule create with out-of-range day_of_week', function () {
    $account = PlatformAccount::factory()->create();

    actingAs($this->pic)
        ->post('/livehost/schedules', [
            'platform_account_id' => $account->id,
            'day_of_week' => 9,
            'start_time' => '09:00',
            'end_time' => '11:00',
        ])
        ->assertSessionHasErrors('day_of_week');
});

it('rejects schedule create with invalid platform_account_id', function () {
    actingAs($this->pic)
        ->post('/livehost/schedules', [
            'platform_account_id' => 99999,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '11:00',
        ])
        ->assertSessionHasErrors('platform_account_id');
});

it('shows a schedule detail page with related sessions', function () {
    $host = User::factory()->create(['role' => 'live_host']);
    $account = PlatformAccount::factory()->create();
    $schedule = LiveSchedule::factory()->create([
        'platform_account_id' => $account->id,
        'live_host_id' => $host->id,
        'day_of_week' => now()->dayOfWeek,
    ]);

    LiveSession::factory()->count(3)->create([
        'platform_account_id' => $account->id,
        'live_host_id' => $host->id,
    ]);

    actingAs($this->pic)
        ->get("/livehost/schedules/{$schedule->id}")
        ->assertInertia(fn (Assert $p) => $p
            ->component('schedules/Show', false)
            ->where('schedule.id', $schedule->id)
            ->has('schedule.dayName')
            ->has('schedule.hostName')
            ->has('recentSessions'));
});

it('renders edit form pre-filled with schedule data', function () {
    $schedule = LiveSchedule::factory()->create([
        'day_of_week' => 4,
        'start_time' => '10:00:00',
        'end_time' => '12:00:00',
        'remarks' => 'Thursday',
    ]);

    actingAs($this->pic)
        ->get("/livehost/schedules/{$schedule->id}/edit")
        ->assertInertia(fn (Assert $p) => $p
            ->component('schedules/Edit', false)
            ->where('schedule.id', $schedule->id)
            ->where('schedule.day_of_week', 4)
            ->where('schedule.remarks', 'Thursday')
            ->has('hosts')
            ->has('platformAccounts'));
});

it('updates a schedule', function () {
    $schedule = LiveSchedule::factory()->create([
        'day_of_week' => 1,
        'is_active' => true,
    ]);
    $newAccount = PlatformAccount::factory()->create();

    actingAs($this->pic)
        ->put("/livehost/schedules/{$schedule->id}", [
            'platform_account_id' => $newAccount->id,
            'live_host_id' => null,
            'day_of_week' => 5,
            'start_time' => '20:00',
            'end_time' => '22:00',
            'is_active' => false,
            'is_recurring' => false,
            'remarks' => 'Updated',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($schedule->fresh())
        ->platform_account_id->toBe($newAccount->id)
        ->day_of_week->toBe(5)
        ->is_active->toBeFalse()
        ->is_recurring->toBeFalse()
        ->remarks->toBe('Updated');
});

it('rejects schedule update with invalid data', function () {
    $schedule = LiveSchedule::factory()->create();

    actingAs($this->pic)
        ->put("/livehost/schedules/{$schedule->id}", [
            'platform_account_id' => $schedule->platform_account_id,
            'day_of_week' => 1,
            'start_time' => '16:00',
            'end_time' => '15:00',
        ])
        ->assertSessionHasErrors('end_time');
});

it('deletes a schedule', function () {
    $schedule = LiveSchedule::factory()->create();

    actingAs($this->pic)
        ->delete("/livehost/schedules/{$schedule->id}")
        ->assertRedirect('/livehost/schedules')
        ->assertSessionHas('success');

    expect(LiveSchedule::find($schedule->id))->toBeNull();
});

it('returns non-paginated schedules in calendar view', function () {
    LiveSchedule::factory()->count(25)->create();

    actingAs($this->pic)
        ->get('/livehost/schedules?view=calendar')
        ->assertInertia(fn (Assert $p) => $p
            ->component('schedules/Index', false)
            ->where('viewMode', 'calendar')
            ->has('schedules', 25));
});

it('keeps list view as the default', function () {
    LiveSchedule::factory()->count(3)->create();

    actingAs($this->pic)
        ->get('/livehost/schedules')
        ->assertInertia(fn (Assert $p) => $p
            ->where('viewMode', 'list')
            ->has('schedules.data'));
});

it('applies filters in calendar view', function () {
    $targetHost = User::factory()->create(['role' => 'live_host']);
    LiveSchedule::factory()->count(3)->create(['live_host_id' => $targetHost->id]);
    LiveSchedule::factory()->count(5)->create();

    actingAs($this->pic)
        ->get("/livehost/schedules?view=calendar&host={$targetHost->id}")
        ->assertInertia(fn (Assert $p) => $p
            ->where('viewMode', 'calendar')
            ->has('schedules', 3));
});

it('forbids live_host from accessing the schedules index', function () {
    $host = User::factory()->create(['role' => 'live_host']);

    $this->actingAs($host)
        ->get('/livehost/schedules')
        ->assertForbidden();
});

it('forbids live_host from creating a schedule', function () {
    $host = User::factory()->create(['role' => 'live_host']);
    $account = PlatformAccount::factory()->create();

    $this->actingAs($host)
        ->post('/livehost/schedules', [
            'platform_account_id' => $account->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '11:00',
        ])
        ->assertForbidden();
});
