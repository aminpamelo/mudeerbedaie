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
