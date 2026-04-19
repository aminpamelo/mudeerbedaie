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
