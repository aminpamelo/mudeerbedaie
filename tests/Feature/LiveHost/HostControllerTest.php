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

it('renders the create form via Inertia', function () {
    actingAs($this->pic)
        ->get('/livehost/hosts/create')
        ->assertInertia(fn (Assert $p) => $p->component('hosts/Create', false));
});

it('creates a new live host', function () {
    actingAs($this->pic)
        ->post('/livehost/hosts', [
            'name' => 'Test Host',
            'email' => 'test@example.com',
            'phone' => '60123456789',
            'status' => 'active',
        ])
        ->assertRedirect('/livehost/hosts')
        ->assertSessionHas('success');

    $created = User::where('email', 'test@example.com')->first();
    expect($created)->not->toBeNull();
    expect($created->role)->toBe('live_host');
    expect($created->status)->toBe('active');
    expect($created->password)->not->toBeNull();
});

it('rejects host create with missing required fields', function () {
    actingAs($this->pic)
        ->post('/livehost/hosts', [])
        ->assertSessionHasErrors(['name', 'email', 'status']);
});

it('rejects host create with duplicate email', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    actingAs($this->pic)
        ->post('/livehost/hosts', [
            'name' => 'X',
            'email' => 'taken@example.com',
            'phone' => '60199999999',
            'status' => 'active',
        ])
        ->assertSessionHasErrors('email');
});

it('rejects host create with duplicate phone', function () {
    User::factory()->create(['phone' => '60111111111']);

    actingAs($this->pic)
        ->post('/livehost/hosts', [
            'name' => 'X',
            'email' => 'unique@example.com',
            'phone' => '60111111111',
            'status' => 'active',
        ])
        ->assertSessionHasErrors('phone');
});

it('rejects host create with invalid status', function () {
    actingAs($this->pic)
        ->post('/livehost/hosts', [
            'name' => 'X',
            'email' => 'x@example.com',
            'phone' => '60100000000',
            'status' => 'banana',
        ])
        ->assertSessionHasErrors('status');
});

it('shows a live host detail page', function () {
    $host = User::factory()->create(['role' => 'live_host']);

    actingAs($this->pic)
        ->get("/livehost/hosts/{$host->id}")
        ->assertInertia(fn (Assert $p) => $p
            ->component('hosts/Show', false)
            ->where('host.id', $host->id)
            ->where('host.name', $host->name)
            ->has('platformAccounts')
            ->has('recentSessions')
            ->has('stats'));
});

it('returns 404 when showing a non-live-host user', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    actingAs($this->pic)
        ->get("/livehost/hosts/{$admin->id}")
        ->assertNotFound();
});

it('renders the edit form with pre-filled host data', function () {
    $host = User::factory()->create(['role' => 'live_host', 'name' => 'Original Name']);

    actingAs($this->pic)
        ->get("/livehost/hosts/{$host->id}/edit")
        ->assertInertia(fn (Assert $p) => $p
            ->component('hosts/Edit', false)
            ->where('host.id', $host->id)
            ->where('host.name', 'Original Name'));
});

it('updates a live host', function () {
    $host = User::factory()->create(['role' => 'live_host', 'name' => 'Old']);

    actingAs($this->pic)
        ->put("/livehost/hosts/{$host->id}", [
            'name' => 'New Name',
            'email' => $host->email,
            'phone' => $host->phone,
            'status' => 'inactive',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($host->fresh())
        ->name->toBe('New Name')
        ->status->toBe('inactive')
        ->role->toBe('live_host');
});

it('allows updating a host to keep its own email and phone', function () {
    $host = User::factory()->create(['role' => 'live_host']);

    actingAs($this->pic)
        ->put("/livehost/hosts/{$host->id}", [
            'name' => $host->name,
            'email' => $host->email,
            'phone' => $host->phone,
            'status' => $host->status,
        ])
        ->assertSessionHasNoErrors();
});

it('prevents updating a host to another users email', function () {
    $host = User::factory()->create(['role' => 'live_host']);
    User::factory()->create(['email' => 'taken@example.com']);

    actingAs($this->pic)
        ->put("/livehost/hosts/{$host->id}", [
            'name' => $host->name,
            'email' => 'taken@example.com',
            'phone' => $host->phone,
            'status' => $host->status,
        ])
        ->assertSessionHasErrors('email');
});

it('ignores role field in update mass-assign', function () {
    $host = User::factory()->create(['role' => 'live_host']);

    actingAs($this->pic)
        ->put("/livehost/hosts/{$host->id}", [
            'name' => $host->name,
            'email' => $host->email,
            'phone' => $host->phone,
            'status' => $host->status,
            'role' => 'admin',
        ]);

    expect($host->fresh()->role)->toBe('live_host');
});

it('returns 404 when editing a non-live-host user', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    actingAs($this->pic)
        ->get("/livehost/hosts/{$admin->id}/edit")
        ->assertNotFound();
});
