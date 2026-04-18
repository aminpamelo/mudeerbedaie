<?php

use App\Models\User;

it('renders the dashboard shell without JS errors', function () {
    $pic = User::factory()->create(['role' => 'admin_livehost', 'name' => 'Ahmad Amin']);
    $this->actingAs($pic);

    visit('/livehost')
        ->assertSee('Good afternoon, Ahmad')
        ->assertSee('Dashboard')
        ->assertNoJavascriptErrors();
});
