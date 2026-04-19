<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

it('renders the profile page for a live host', function () {
    $host = User::factory()->create([
        'role' => 'live_host',
        'name' => 'Wan Azman',
        'email' => 'wan@example.com',
    ]);

    actingAs($host)
        ->get('/live-host/me')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $p) => $p
            ->component('Profile', false)
            ->where('profile.name', 'Wan Azman')
            ->where('profile.email', 'wan@example.com')
            ->where('profile.role', 'live_host'));
});

it('forbids a non-live-host from viewing the pocket profile', function () {
    $user = User::factory()->create(['role' => 'student']);

    actingAs($user)
        ->get('/live-host/me')
        ->assertForbidden();
});

it('requires auth to view the profile page', function () {
    $this->get('/live-host/me')
        ->assertRedirect('/login');
});
