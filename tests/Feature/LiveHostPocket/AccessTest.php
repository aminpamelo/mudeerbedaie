<?php

use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('allows a live_host to access the Pocket dashboard', function () {
    $host = User::factory()->create(['role' => 'live_host']);

    actingAs($host)->get('/live-host')->assertSuccessful();
});

it('forbids admin_livehost from the host-side Pocket', function () {
    $pic = User::factory()->create(['role' => 'admin_livehost']);

    actingAs($pic)->get('/live-host')->assertForbidden();
});

it('forbids regular users from the host-side Pocket', function () {
    $user = User::factory()->create(['role' => 'student']);

    actingAs($user)->get('/live-host')->assertForbidden();
});

it('redirects guests to login', function () {
    get('/live-host')->assertRedirect('/login');
});
