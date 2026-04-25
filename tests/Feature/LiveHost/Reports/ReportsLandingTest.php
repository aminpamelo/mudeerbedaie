<?php

use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('redirects guests away from the reports landing', function () {
    get('/livehost/reports')->assertRedirect('/login');
});

it('forbids non-admin users', function () {
    $user = User::factory()->create(['role' => 'student']);

    actingAs($user)->get('/livehost/reports')->assertForbidden();
});

it('renders the reports landing for admin_livehost', function () {
    $user = User::factory()->create(['role' => 'admin_livehost']);

    actingAs($user)
        ->get('/livehost/reports')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/Index', false)
            ->has('reports', 4)
            ->where('reports.0.available', true)
            ->where('reports.1.available', true)
            ->where('reports.2.available', true)
            ->where('reports.3.available', true)
        );
});
