<?php

use App\Models\User;

it('renders the Pocket shell for a live_host without JS errors', function () {
    $host = User::factory()->create(['role' => 'live_host', 'name' => 'Wan Mokhtar']);
    $this->actingAs($host);

    visit('/live-host')
        ->assertSee('Welcome, Wan')
        ->assertNoJavascriptErrors();
});
