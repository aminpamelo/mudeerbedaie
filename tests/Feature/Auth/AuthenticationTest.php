<?php

use App\Models\User;
use Livewire\Volt\Volt as LivewireVolt;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

test('users can authenticate using phone number', function () {
    $user = User::factory()->create([
        'phone' => '60123456789',
        'email' => 'test@example.com',
    ]);

    $response = LivewireVolt::test('auth.login')
        ->set('login', $user->phone)
        ->set('password', 'password')
        ->call('authenticate');

    $response
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

test('users can authenticate using email', function () {
    $user = User::factory()->create([
        'phone' => '60123456789',
        'email' => 'test@example.com',
    ]);

    $response = LivewireVolt::test('auth.login')
        ->set('login', $user->email)
        ->set('password', 'password')
        ->call('authenticate');

    $response
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

test('users can authenticate with phone number only without email', function () {
    $user = User::factory()->create([
        'phone' => '60165756060',
        'email' => null,
    ]);

    $response = LivewireVolt::test('auth.login')
        ->set('login', $user->phone)
        ->set('password', 'password')
        ->call('authenticate');

    $response
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create([
        'phone' => '60123456789',
        'email' => 'test@example.com',
    ]);

    $response = LivewireVolt::test('auth.login')
        ->set('login', $user->phone)
        ->set('password', 'wrong-password')
        ->call('authenticate');

    $response->assertHasErrors('login');

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $response->assertRedirect('/');

    $this->assertGuest();
});
