<?php

use App\Models\LiveSchedule;
use App\Models\LiveSession;
use App\Models\User;

it('renders sessions index for host', function () {
    $host = User::factory()->create(['role' => 'live_host']);
    LiveSession::factory()->create([
        'live_host_id' => $host->id,
        'status' => 'ended',
        'title' => 'Skincare Bundle Launch',
    ]);

    $this->actingAs($host);

    visit('/live-host/sessions')
        ->assertSee('Your sessions')
        ->assertSee('Skincare Bundle Launch')
        ->assertNoJavascriptErrors();
});

it('renders schedule for host', function () {
    $host = User::factory()->create(['role' => 'live_host']);
    LiveSchedule::factory()->create([
        'live_host_id' => $host->id,
        'day_of_week' => 1,
        'is_active' => true,
    ]);

    $this->actingAs($host);

    visit('/live-host/schedule')
        ->assertSee('Your schedule')
        ->assertNoJavascriptErrors();
});

it('renders session detail recap page for host', function () {
    $host = User::factory()->create(['role' => 'live_host']);
    $session = LiveSession::factory()->create([
        'live_host_id' => $host->id,
        'status' => 'ended',
        'title' => 'Morning Live Skincare',
    ]);

    $this->actingAs($host);

    visit("/live-host/sessions/{$session->id}")
        ->assertSee('Morning Live Skincare')
        ->assertSee('Save recap')
        ->assertNoJavascriptErrors();
});

it('renders profile page for host', function () {
    $host = User::factory()->create([
        'role' => 'live_host',
        'name' => 'Wan Azman',
        'email' => 'wan@example.com',
    ]);

    $this->actingAs($host);

    visit('/live-host/me')
        ->assertSee('Wan Azman')
        ->assertSee('Sign out')
        ->assertNoJavascriptErrors();
});
