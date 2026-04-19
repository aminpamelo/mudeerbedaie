<?php

use App\Models\LiveSession;
use App\Models\User;

it('renders the Pocket shell for a live_host without JS errors', function () {
    $host = User::factory()->create(['role' => 'live_host', 'name' => 'Wan Mokhtar']);
    $this->actingAs($host);

    visit('/live-host')
        ->assertSee('Wan')
        ->assertNoJavascriptErrors();
});

it('renders today screen with live card when session is live', function () {
    $host = User::factory()->create(['role' => 'live_host', 'name' => 'Wan Amir']);
    LiveSession::factory()->create([
        'live_host_id' => $host->id,
        'status' => 'live',
        'actual_start_at' => now()->subHours(1)->subMinutes(42),
        'title' => 'Fashion Friday Flash Sale',
    ]);

    $this->actingAs($host);

    visit('/live-host')
        ->assertSee('Fashion Friday Flash Sale')
        ->assertSee('LIVE NOW')
        ->assertNoJavascriptErrors();
});

it('renders today screen empty state without live session', function () {
    $host = User::factory()->create(['role' => 'live_host', 'name' => 'Wan Amir']);
    $this->actingAs($host);

    visit('/live-host')
        ->assertSee('Wan')
        ->assertDontSee('LIVE NOW')
        ->assertNoJavascriptErrors();
});
