<?php

use App\Models\LiveAnalytics;
use App\Models\LiveSession;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->host = User::factory()->create(['role' => 'live_host']);
});

it('lists ended sessions by default', function () {
    LiveSession::factory()->count(3)->create([
        'live_host_id' => $this->host->id,
        'status' => 'ended',
    ]);
    LiveSession::factory()->count(2)->create([
        'live_host_id' => $this->host->id,
        'status' => 'scheduled',
    ]);

    actingAs($this->host)
        ->get('/live-host/sessions')
        ->assertInertia(fn (Assert $p) => $p
            ->component('Sessions', false)
            ->where('filter', 'ended')
            ->has('sessions.data', 3));
});

it('filters upcoming sessions', function () {
    LiveSession::factory()->count(2)->create([
        'live_host_id' => $this->host->id,
        'status' => 'scheduled',
    ]);
    LiveSession::factory()->create([
        'live_host_id' => $this->host->id,
        'status' => 'live',
    ]);
    LiveSession::factory()->count(4)->create([
        'live_host_id' => $this->host->id,
        'status' => 'ended',
    ]);

    actingAs($this->host)
        ->get('/live-host/sessions?filter=upcoming')
        ->assertInertia(fn (Assert $p) => $p
            ->where('filter', 'upcoming')
            ->has('sessions.data', 3));
});

it('returns all sessions on the All tab', function () {
    LiveSession::factory()->count(2)->create([
        'live_host_id' => $this->host->id,
        'status' => 'scheduled',
    ]);
    LiveSession::factory()->count(3)->create([
        'live_host_id' => $this->host->id,
        'status' => 'ended',
    ]);

    actingAs($this->host)
        ->get('/live-host/sessions?filter=all')
        ->assertInertia(fn (Assert $p) => $p
            ->where('filter', 'all')
            ->has('sessions.data', 5));
});

it('includes analytics in DTO when present', function () {
    $session = LiveSession::factory()->create([
        'live_host_id' => $this->host->id,
        'status' => 'ended',
    ]);
    LiveAnalytics::factory()->create([
        'live_session_id' => $session->id,
        'viewers_peak' => 412,
        'total_likes' => 1284,
        'gifts_value' => 86.50,
    ]);

    actingAs($this->host)
        ->get('/live-host/sessions?filter=ended')
        ->assertInertia(fn (Assert $p) => $p
            ->has('sessions.data.0.analytics', fn (Assert $a) => $a
                ->where('viewersPeak', 412)
                ->where('totalLikes', 1284)
                ->where('giftsValue', 86.5)));
});

it('returns null analytics when not present', function () {
    LiveSession::factory()->create([
        'live_host_id' => $this->host->id,
        'status' => 'ended',
    ]);

    actingAs($this->host)
        ->get('/live-host/sessions?filter=ended')
        ->assertInertia(fn (Assert $p) => $p
            ->where('sessions.data.0.analytics', null));
});

it('excludes other hosts sessions', function () {
    $otherHost = User::factory()->create(['role' => 'live_host']);
    LiveSession::factory()->count(5)->create(['live_host_id' => $otherHost->id]);

    actingAs($this->host)
        ->get('/live-host/sessions')
        ->assertInertia(fn (Assert $p) => $p->has('sessions.data', 0));
});

it('requires auth to view sessions list', function () {
    $this->get('/live-host/sessions')->assertRedirect('/login');
});
