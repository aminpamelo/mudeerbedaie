<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->pic = User::factory()->create(['role' => 'admin_livehost']);
});

it('prefills the host create form when given a live_host user_id', function () {
    $liveHost = User::factory()->create([
        'role' => 'live_host',
        'name' => 'Ahmad Rahman',
        'email' => 'ahmad@livehost.test',
        'phone' => '60187654321',
    ]);

    actingAs($this->pic)
        ->get("/livehost/hosts/create?user_id={$liveHost->id}")
        ->assertInertia(
            fn (Assert $p) => $p
                ->component('hosts/Create', false)
                ->where('prefilledUser.id', $liveHost->id)
                ->where('prefilledUser.name', 'Ahmad Rahman')
                ->where('prefilledUser.email', 'ahmad@livehost.test')
                ->where('prefilledUser.phone', '60187654321')
        );
});

it('does not prefill when user_id is missing', function () {
    actingAs($this->pic)
        ->get('/livehost/hosts/create')
        ->assertInertia(
            fn (Assert $p) => $p
                ->component('hosts/Create', false)
                ->where('prefilledUser', null)
        );
});

it('does not prefill when user_id does not match an existing user', function () {
    actingAs($this->pic)
        ->get('/livehost/hosts/create?user_id=999999')
        ->assertInertia(
            fn (Assert $p) => $p
                ->component('hosts/Create', false)
                ->where('prefilledUser', null)
        );
});

it('does not prefill when the user_id belongs to a non-live_host user', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'name' => 'Admin Person',
        'email' => 'admin@example.com',
    ]);

    actingAs($this->pic)
        ->get("/livehost/hosts/create?user_id={$admin->id}")
        ->assertInertia(
            fn (Assert $p) => $p
                ->component('hosts/Create', false)
                ->where('prefilledUser', null)
        );
});

it('attaches to the existing user when storing a host with user_id', function () {
    $liveHost = User::factory()->create([
        'role' => 'live_host',
        'name' => 'Old Name',
        'email' => 'hired@livehost.test',
        'phone' => '60111111111',
    ]);
    $originalId = $liveHost->id;
    $beforeCount = User::count();

    actingAs($this->pic)
        ->post('/livehost/hosts', [
            'user_id' => $liveHost->id,
            'name' => 'New Name',
            'email' => 'hired@livehost.test',
            'phone' => '60111111111',
            'status' => 'active',
        ])
        ->assertRedirect('/livehost/hosts');

    expect(User::count())->toBe($beforeCount);

    $liveHost->refresh();
    expect($liveHost->id)->toBe($originalId);
    expect($liveHost->name)->toBe('New Name');
    expect($liveHost->status)->toBe('active');
    expect($liveHost->role)->toBe('live_host');
});

it('creates a new user when no user_id is supplied', function () {
    $beforeCount = User::count();

    actingAs($this->pic)
        ->post('/livehost/hosts', [
            'name' => 'Brand New',
            'email' => 'brandnew@livehost.test',
            'phone' => '60199999999',
            'status' => 'active',
        ])
        ->assertRedirect('/livehost/hosts');

    expect(User::count())->toBe($beforeCount + 1);
    expect(User::where('email', 'brandnew@livehost.test')->value('role'))->toBe('live_host');
});

it('rejects store when user_id points to a non-live_host user', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'email' => 'admin@other.test',
        'phone' => '60100000000',
    ]);

    actingAs($this->pic)
        ->post('/livehost/hosts', [
            'user_id' => $admin->id,
            'name' => 'Ignored',
            'email' => 'admin@other.test',
            'phone' => '60100000000',
            'status' => 'active',
        ])
        ->assertSessionHasErrors(['email', 'phone']);
});
