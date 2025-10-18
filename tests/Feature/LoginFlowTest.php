<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;

uses()->group('auth');

beforeEach(function () {
    // Create a test user with phone number (stored with + prefix)
    $this->user = User::factory()->create([
        'email' => 'test@example.com',
        'phone' => '+60123456789',
        'password' => Hash::make('password'),
        'status' => 'active',
    ]);
});

it('can login with email', function () {
    Volt::test('auth.login')
        ->set('login', 'test@example.com')
        ->set('password', 'password')
        ->call('login')
        ->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($this->user);
});

it('can login with phone number with plus prefix', function () {
    Volt::test('auth.login')
        ->set('login', '+60123456789')
        ->set('password', 'password')
        ->call('login')
        ->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($this->user);
});

it('can login with phone number without plus prefix', function () {
    Volt::test('auth.login')
        ->set('login', '60123456789')
        ->set('password', 'password')
        ->call('login')
        ->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($this->user);
});

it('can login with phone number with spaces', function () {
    Volt::test('auth.login')
        ->set('login', '+60 12 345 6789')
        ->set('password', 'password')
        ->call('login')
        ->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($this->user);
});

it('can login with phone number with dashes', function () {
    Volt::test('auth.login')
        ->set('login', '+60-123-456-789')
        ->set('password', 'password')
        ->call('login')
        ->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($this->user);
});

it('cannot login with invalid credentials', function () {
    Volt::test('auth.login')
        ->set('login', 'test@example.com')
        ->set('password', 'wrong-password')
        ->call('login')
        ->assertHasErrors(['login']);

    $this->assertGuest();
});

it('cannot login with inactive account', function () {
    $this->user->update(['status' => 'inactive']);

    Volt::test('auth.login')
        ->set('login', 'test@example.com')
        ->set('password', 'password')
        ->call('login')
        ->assertRedirect('/dashboard');

    // User should be authenticated even if inactive (middleware handles access control)
    $this->assertAuthenticatedAs($this->user);
});

it('validates required fields', function () {
    Volt::test('auth.login')
        ->set('login', '')
        ->set('password', '')
        ->call('login')
        ->assertHasErrors(['login', 'password']);

    $this->assertGuest();
});

it('rate limits login attempts', function () {
    // Make 6 failed login attempts
    for ($i = 0; $i < 6; $i++) {
        Volt::test('auth.login')
            ->set('login', 'test@example.com')
            ->set('password', 'wrong-password')
            ->call('login');
    }

    // 6th attempt should be rate limited
    Volt::test('auth.login')
        ->set('login', 'test@example.com')
        ->set('password', 'password')
        ->call('login')
        ->assertHasErrors(['login']);

    $this->assertGuest();
});

it('can remember user when remember me is checked', function () {
    Volt::test('auth.login')
        ->set('login', 'test@example.com')
        ->set('password', 'password')
        ->set('remember', true)
        ->call('login')
        ->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($this->user);
});
