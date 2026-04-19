<?php

use App\Models\User;

it('creates a new live host via the form', function () {
    $pic = User::factory()->create(['role' => 'admin_livehost']);
    $this->actingAs($pic);

    visit('/livehost/hosts/create')
        ->assertSee('New live host')
        ->fill('name', 'Browser Host')
        ->fill('email', 'browser@example.com')
        ->fill('phone', '60134567890')
        ->click('Create host')
        ->assertPathIs('/livehost/hosts')
        ->assertSee('Browser Host')
        ->assertNoJavascriptErrors();
});

it('shows validation errors when form is submitted empty', function () {
    $pic = User::factory()->create(['role' => 'admin_livehost']);
    $this->actingAs($pic);

    visit('/livehost/hosts/create')
        ->click('Create host')
        ->assertSee('name')
        ->assertNoJavascriptErrors();
});
