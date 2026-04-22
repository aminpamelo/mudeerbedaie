<?php

use App\Models\LiveSession;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->host = User::factory()->create(['role' => 'live_host']);
});

it('requires authentication to view the go-live page', function () {
    $this->get('/live-host/go-live')->assertRedirect('/login');
});

it('renders the live state when the host has a currently-live session', function () {
    $session = LiveSession::factory()->create([
        'live_host_id' => $this->host->id,
        'status' => 'live',
        'actual_start_at' => now()->subMinutes(37),
        'title' => 'Morning TikTok drop',
    ]);

    actingAs($this->host)
        ->get('/live-host/go-live')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $p) => $p
            ->component('GoLive', false)
            ->where('state', 'live')
            ->where('session.id', $session->id)
            ->where('session.title', 'Morning TikTok drop')
            ->where('session.status', 'live'));
});

it('renders the imminent state when the next scheduled session is within 30 minutes', function () {
    $session = LiveSession::factory()->create([
        'live_host_id' => $this->host->id,
        'status' => 'scheduled',
        'scheduled_start_at' => now()->addMinutes(12),
        'title' => 'Evening Shopee live',
    ]);

    actingAs($this->host)
        ->get('/live-host/go-live')
        ->assertInertia(fn (Assert $p) => $p
            ->where('state', 'imminent')
            ->where('session.id', $session->id));
});

it('still treats a session as imminent within the 2-hour grace window after scheduled start', function () {
    // Host is 45 minutes late — still inside the grace window, so we keep
    // them on the launch pad rather than bumping them to "upcoming".
    $session = LiveSession::factory()->create([
        'live_host_id' => $this->host->id,
        'status' => 'scheduled',
        'scheduled_start_at' => now()->subMinutes(45),
    ]);

    actingAs($this->host)
        ->get('/live-host/go-live')
        ->assertInertia(fn (Assert $p) => $p
            ->where('state', 'imminent')
            ->where('session.id', $session->id));
});

it('renders the upcoming state when the next scheduled session is further out', function () {
    $session = LiveSession::factory()->create([
        'live_host_id' => $this->host->id,
        'status' => 'scheduled',
        'scheduled_start_at' => now()->addHours(3),
    ]);

    actingAs($this->host)
        ->get('/live-host/go-live')
        ->assertInertia(fn (Assert $p) => $p
            ->where('state', 'upcoming')
            ->where('session.id', $session->id));
});

it('renders the none state when the host has no upcoming sessions', function () {
    // Only a stale scheduled session beyond the 2-hour grace window — this
    // should be treated as if no upcoming session exists.
    LiveSession::factory()->create([
        'live_host_id' => $this->host->id,
        'status' => 'scheduled',
        'scheduled_start_at' => now()->subHours(5),
    ]);

    actingAs($this->host)
        ->get('/live-host/go-live')
        ->assertInertia(fn (Assert $p) => $p
            ->where('state', 'none')
            ->where('session', null));
});

it('live state wins over an imminent scheduled session', function () {
    // Two sessions: one live, one imminent. The live one must win so the
    // host sees the end-stream controls instead of a countdown.
    LiveSession::factory()->create([
        'live_host_id' => $this->host->id,
        'status' => 'scheduled',
        'scheduled_start_at' => now()->addMinutes(10),
    ]);
    $liveSession = LiveSession::factory()->create([
        'live_host_id' => $this->host->id,
        'status' => 'live',
        'actual_start_at' => now()->subMinutes(5),
    ]);

    actingAs($this->host)
        ->get('/live-host/go-live')
        ->assertInertia(fn (Assert $p) => $p
            ->where('state', 'live')
            ->where('session.id', $liveSession->id));
});

it('picks the soonest imminent session when multiple are in the window', function () {
    LiveSession::factory()->create([
        'live_host_id' => $this->host->id,
        'status' => 'scheduled',
        'scheduled_start_at' => now()->addMinutes(25),
    ]);
    $soonest = LiveSession::factory()->create([
        'live_host_id' => $this->host->id,
        'status' => 'scheduled',
        'scheduled_start_at' => now()->addMinutes(8),
    ]);

    actingAs($this->host)
        ->get('/live-host/go-live')
        ->assertInertia(fn (Assert $p) => $p
            ->where('state', 'imminent')
            ->where('session.id', $soonest->id));
});

it('does not leak another host\'s sessions', function () {
    $otherHost = User::factory()->create(['role' => 'live_host']);
    LiveSession::factory()->create([
        'live_host_id' => $otherHost->id,
        'status' => 'live',
        'actual_start_at' => now()->subMinutes(10),
    ]);

    actingAs($this->host)
        ->get('/live-host/go-live')
        ->assertInertia(fn (Assert $p) => $p
            ->where('state', 'none')
            ->where('session', null));
});
