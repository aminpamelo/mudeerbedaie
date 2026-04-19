<?php

use App\Models\User;

it('renders hosts index page with data', function () {
    $pic = User::factory()->create(['role' => 'admin_livehost']);
    User::factory()->create(['role' => 'live_host', 'name' => 'Wan Amir']);
    User::factory()->create(['role' => 'live_host', 'name' => 'Haliza Tan']);

    $this->actingAs($pic);

    visit('/livehost/hosts')
        ->assertSee('Live Hosts')
        ->assertSee('Wan Amir')
        ->assertSee('Haliza Tan')
        ->assertNoJavascriptErrors();
});
