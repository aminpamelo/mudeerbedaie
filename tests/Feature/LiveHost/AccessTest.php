<?php

use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('allows admin_livehost to access the dashboard', function () {
    $pic = User::factory()->create(['role' => 'admin_livehost']);
    actingAs($pic)->get('/livehost')->assertSuccessful();
});

it('allows admin to access the dashboard', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    actingAs($admin)->get('/livehost')->assertSuccessful();
});

it('forbids live_host from accessing the PIC dashboard', function () {
    $host = User::factory()->create(['role' => 'live_host']);
    actingAs($host)->get('/livehost')->assertForbidden();
});

it('forbids regular users from accessing the PIC dashboard', function () {
    $user = User::factory()->create(['role' => 'student']);
    actingAs($user)->get('/livehost')->assertForbidden();
});

it('redirects guests to login', function () {
    get('/livehost')->assertRedirect('/login');
});
