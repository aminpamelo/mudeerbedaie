<?php

use App\Models\LiveSession;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->host = User::factory()->create(['role' => 'live_host', 'name' => 'Wan Amir']);
});

it('shows today stats with sessions, liveNow, upcoming', function () {
    LiveSession::factory()->count(2)->create([
        'live_host_id' => $this->host->id,
        'status' => 'ended',
        'scheduled_start_at' => now()->startOfDay()->addHours(9),
        'actual_end_at' => now(),
        'duration_minutes' => 90,
    ]);

    LiveSession::factory()->create([
        'live_host_id' => $this->host->id,
        'status' => 'live',
        'scheduled_start_at' => now()->startOfDay()->addHours(12),
        'actual_start_at' => now()->subHours(2),
    ]);

    LiveSession::factory()->create([
        'live_host_id' => $this->host->id,
        'status' => 'scheduled',
        'scheduled_start_at' => now()->addHours(3),
    ]);

    actingAs($this->host)
        ->get('/live-host')
        ->assertInertia(fn (Assert $p) => $p
            ->component('Today', false)
            ->has('stats', fn (Assert $s) => $s
                ->where('sessionsDoneToday', 2)
                ->where('watchMinutesToday', 180)
                ->etc())
            ->has('liveNow', 1)
            ->has('upcoming', 1));
});

it('omits other hosts sessions', function () {
    $otherHost = User::factory()->create(['role' => 'live_host']);
    LiveSession::factory()->count(3)->create([
        'live_host_id' => $otherHost->id,
        'status' => 'live',
        'actual_start_at' => now()->subHour(),
    ]);

    actingAs($this->host)
        ->get('/live-host')
        ->assertInertia(fn (Assert $p) => $p->has('liveNow', 0));
});

it('ends a live session', function () {
    $session = LiveSession::factory()->create([
        'live_host_id' => $this->host->id,
        'status' => 'live',
        'actual_start_at' => now()->subHour(),
    ]);

    actingAs($this->host)
        ->post("/live-host/sessions/{$session->id}/end")
        ->assertRedirect('/live-host');

    expect($session->fresh())
        ->status->toBe('ended')
        ->actual_end_at->not->toBeNull();
});

it('forbids ending another hosts session', function () {
    $otherHost = User::factory()->create(['role' => 'live_host']);
    $session = LiveSession::factory()->create([
        'live_host_id' => $otherHost->id,
        'status' => 'live',
    ]);

    actingAs($this->host)
        ->post("/live-host/sessions/{$session->id}/end")
        ->assertForbidden();
});

it('rejects end-session when status is not live', function () {
    $session = LiveSession::factory()->create([
        'live_host_id' => $this->host->id,
        'status' => 'scheduled',
    ]);

    actingAs($this->host)
        ->post("/live-host/sessions/{$session->id}/end")
        ->assertStatus(409);
});
