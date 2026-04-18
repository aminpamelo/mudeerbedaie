<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->pic = User::factory()->create(['role' => 'admin_livehost']);
});

it('lists live hosts with pagination (15 per page)', function () {
    User::factory()->count(25)->create(['role' => 'live_host']);

    actingAs($this->pic)
        ->get('/livehost/hosts')
        ->assertInertia(fn (Assert $p) => $p
            ->component('hosts/Index', false)
            ->has('hosts.data', 15)
            ->has('hosts.links')
            ->has('filters'));
});

it('filters hosts by name search', function () {
    User::factory()->create(['role' => 'live_host', 'name' => 'Wan Amir']);
    User::factory()->create(['role' => 'live_host', 'name' => 'Haliza Tan']);

    actingAs($this->pic)
        ->get('/livehost/hosts?search=Wan')
        ->assertInertia(fn (Assert $p) => $p
            ->has('hosts.data', 1)
            ->where('filters.search', 'Wan'));
});

it('filters hosts by email search', function () {
    User::factory()->create(['role' => 'live_host', 'email' => 'wan@example.com']);
    User::factory()->create(['role' => 'live_host', 'email' => 'haliza@example.com']);

    actingAs($this->pic)
        ->get('/livehost/hosts?search=wan')
        ->assertInertia(fn (Assert $p) => $p->has('hosts.data', 1));
});

it('filters hosts by status', function () {
    User::factory()->count(3)->create(['role' => 'live_host', 'status' => 'active']);
    User::factory()->count(2)->create(['role' => 'live_host', 'status' => 'suspended']);

    actingAs($this->pic)
        ->get('/livehost/hosts?status=suspended')
        ->assertInertia(fn (Assert $p) => $p
            ->has('hosts.data', 2)
            ->where('filters.status', 'suspended'));
});

it('excludes non-live_host users from the list', function () {
    User::factory()->count(2)->create(['role' => 'live_host']);
    User::factory()->count(5)->create(['role' => 'admin']);
    User::factory()->count(3)->create(['role' => 'student']);

    actingAs($this->pic)
        ->get('/livehost/hosts')
        ->assertInertia(fn (Assert $p) => $p->has('hosts.data', 2));
});

it('excludes soft-deleted hosts from the list', function () {
    User::factory()->count(2)->create(['role' => 'live_host']);
    $toDelete = User::factory()->create(['role' => 'live_host']);
    $toDelete->delete();

    actingAs($this->pic)
        ->get('/livehost/hosts')
        ->assertInertia(fn (Assert $p) => $p->has('hosts.data', 2));
});
