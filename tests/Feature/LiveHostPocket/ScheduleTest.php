<?php

use App\Models\LiveScheduleAssignment;
use App\Models\LiveTimeSlot;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->host = User::factory()->create(['role' => 'live_host']);
});

it('shows weekly schedule grouped by day', function () {
    LiveScheduleAssignment::factory()->count(2)->create([
        'live_host_id' => $this->host->id,
        'day_of_week' => 1,
    ]);
    LiveScheduleAssignment::factory()->create([
        'live_host_id' => $this->host->id,
        'day_of_week' => 5,
    ]);

    actingAs($this->host)
        ->get('/live-host/schedule')
        ->assertInertia(fn (Assert $p) => $p
            ->component('Schedule', false)
            ->where('totalSlots', 3)
            ->has('days', 7));
});

it('excludes cancelled schedules', function () {
    LiveScheduleAssignment::factory()->create([
        'live_host_id' => $this->host->id,
        'status' => 'cancelled',
    ]);

    actingAs($this->host)
        ->get('/live-host/schedule')
        ->assertInertia(fn (Assert $p) => $p->where('totalSlots', 0));
});

it('excludes other hosts schedules', function () {
    $otherHost = User::factory()->create(['role' => 'live_host']);
    LiveScheduleAssignment::factory()->count(3)->create([
        'live_host_id' => $otherHost->id,
    ]);

    actingAs($this->host)
        ->get('/live-host/schedule')
        ->assertInertia(fn (Assert $p) => $p->where('totalSlots', 0));
});

it('normalises start and end times to HH:MM from related time slot', function () {
    $slot = LiveTimeSlot::factory()->create([
        'start_time' => '09:30:00',
        'end_time' => '11:45:00',
    ]);
    LiveScheduleAssignment::factory()->create([
        'live_host_id' => $this->host->id,
        'day_of_week' => 2,
        'time_slot_id' => $slot->id,
    ]);

    actingAs($this->host)
        ->get('/live-host/schedule')
        ->assertInertia(fn (Assert $p) => $p
            ->where('days.2.schedules.0.startTime', '09:30')
            ->where('days.2.schedules.0.endTime', '11:45'));
});

it('requires auth to view schedule', function () {
    $this->get('/live-host/schedule')->assertRedirect('/login');
});

it('returns the requested week as a Sunday-anchored range with prev/next links', function () {
    actingAs($this->host)
        ->get('/live-host/schedule?week=2026-04-30')
        ->assertInertia(fn (Assert $p) => $p
            ->where('week.start', '2026-04-26')
            ->where('week.end', '2026-05-02')
            ->where('week.prev', '2026-04-19')
            ->where('week.next', '2026-05-03')
            ->where('week.isCurrent', false));
});

it('marks today and past dates on the day buckets', function () {
    \Illuminate\Support\Facades\Date::setTestNow('2026-04-25 10:00:00');

    actingAs($this->host)
        ->get('/live-host/schedule')
        ->assertInertia(fn (Assert $p) => $p
            ->where('week.start', '2026-04-19')
            ->where('week.isCurrent', true)
            ->where('days.0.isPast', true)   // Sunday Apr 19
            ->where('days.5.isPast', true)   // Friday Apr 24
            ->where('days.6.isToday', true)  // Saturday Apr 25
            ->where('days.6.isPast', false));
});

it('hides specific-date assignments outside the selected week', function () {
    LiveScheduleAssignment::factory()->forDate('2026-04-21')->create([
        'live_host_id' => $this->host->id,
        'day_of_week' => 2,
    ]);

    // Same week — should appear.
    actingAs($this->host)
        ->get('/live-host/schedule?week=2026-04-19')
        ->assertInertia(fn (Assert $p) => $p->where('totalSlots', 1));

    // Different week — should not appear.
    actingAs($this->host)
        ->get('/live-host/schedule?week=2026-04-26')
        ->assertInertia(fn (Assert $p) => $p->where('totalSlots', 0));
});

it('attaches a recapAction to slots whose linked session needs upload', function () {
    \Illuminate\Support\Facades\Date::setTestNow('2026-04-25 12:00:00');

    $assignment = LiveScheduleAssignment::factory()->forDate('2026-04-21')->create([
        'live_host_id' => $this->host->id,
        'day_of_week' => 2,
    ]);
    // The assignment observer auto-creates a scheduled session — replace it
    // with an ended-without-attachments one so the test exercises the
    // "needs upload" branch.
    \App\Models\LiveSession::where('live_schedule_assignment_id', $assignment->id)->update([
        'status' => 'ended',
        'actual_start_at' => '2026-04-21 06:30:00',
        'actual_end_at' => '2026-04-21 08:30:00',
    ]);

    actingAs($this->host)
        ->get('/live-host/schedule?week=2026-04-19')
        ->assertInertia(fn (Assert $p) => $p
            ->where('days.2.schedules.0.recapAction.needsUpload', true)
            ->has('days.2.schedules.0.recapAction.session')
            ->where('days.2.schedules.0.recapAction.session.status', 'ended')
            ->has('days.2.schedules.0.recapAction.attachments'));
});

it('marks recapAction as submitted when the session has been recapped with proof', function () {
    \Illuminate\Support\Facades\Date::setTestNow('2026-04-25 12:00:00');

    $assignment = LiveScheduleAssignment::factory()->forDate('2026-04-21')->create([
        'live_host_id' => $this->host->id,
        'day_of_week' => 2,
    ]);

    // Flip the auto-created session to ended *and* attach proof so the
    // controller treats it as fully submitted.
    $session = \App\Models\LiveSession::where('live_schedule_assignment_id', $assignment->id)->firstOrFail();
    $session->update([
        'status' => 'ended',
        'actual_start_at' => '2026-04-21 06:30:00',
        'actual_end_at' => '2026-04-21 08:30:00',
    ]);
    \App\Models\LiveSessionAttachment::factory()->create([
        'live_session_id' => $session->id,
        'file_type' => 'image/jpeg',
    ]);

    actingAs($this->host)
        ->get('/live-host/schedule?week=2026-04-19')
        ->assertInertia(fn (Assert $p) => $p
            ->where('days.2.schedules.0.recapAction.state', 'submitted')
            ->where('days.2.schedules.0.recapAction.submitted', true)
            ->where('days.2.schedules.0.recapAction.needsUpload', false));
});

it('leaves recapAction null on slots whose session is still upcoming', function () {
    \Illuminate\Support\Facades\Date::setTestNow('2026-04-25 12:00:00');

    // Future-dated assignment → observer creates a future scheduled session
    // → no recap action expected.
    LiveScheduleAssignment::factory()->forDate('2026-05-02')->create([
        'live_host_id' => $this->host->id,
        'day_of_week' => 6,
    ]);

    actingAs($this->host)
        ->get('/live-host/schedule?week=2026-04-26')
        ->assertInertia(fn (Assert $p) => $p
            ->where('days.6.schedules.0.recapAction', null));
});

it('includes assignments dated on the final day of the week', function () {
    // Saturday Apr 25 is the last day of the Sunday-anchored week. Date casts
    // can serialize as "YYYY-MM-DD 00:00:00", which a naive `whereBetween` on
    // the raw column silently excludes — guard against that regression.
    LiveScheduleAssignment::factory()->forDate('2026-04-25')->create([
        'live_host_id' => $this->host->id,
        'day_of_week' => 6,
    ]);

    actingAs($this->host)
        ->get('/live-host/schedule?week=2026-04-19')
        ->assertInertia(fn (Assert $p) => $p
            ->where('totalSlots', 1)
            ->where('days.6.schedules.0.date', '2026-04-25'));
});
