<?php

use App\Models\LiveSession;
use App\Models\User;

it('renders the dashboard shell without JS errors', function () {
    $pic = User::factory()->create(['role' => 'admin_livehost', 'name' => 'Ahmad Amin']);
    $this->actingAs($pic);

    visit('/livehost')
        ->assertSee('Good afternoon, Ahmad')
        ->assertSee('Dashboard')
        ->assertNoJavascriptErrors();
});

it('renders dashboard with live KPIs and panels', function () {
    $pic = User::factory()->create(['role' => 'admin_livehost', 'name' => 'Ahmad Amin']);
    User::factory()->count(3)->create(['role' => 'live_host']);
    LiveSession::factory()->create(['status' => 'live']);

    $this->actingAs($pic);

    visit('/livehost')
        ->assertSee('Active hosts')
        ->assertSee('Live now')
        ->assertSee('Sessions today')
        ->assertSee('Watch hours')
        ->assertSee('On Air now')
        ->assertSee('Recent activity')
        ->assertNoJavascriptErrors();
});
