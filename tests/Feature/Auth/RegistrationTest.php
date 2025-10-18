<?php

use App\Models\User;
use Livewire\Volt\Volt;

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users can register with phone number', function () {
    $response = Volt::test('auth.register')
        ->set('name', 'Test User')
        ->set('phone', '60123456789')
        ->set('email', 'test@example.com')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('register');

    $response
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();

    // Verify user was created with correct data
    expect(User::where('phone', '60123456789')->first())
        ->name->toBe('Test User')
        ->email->toBe('test@example.com')
        ->phone->toBe('60123456789')
        ->role->toBe('student');
});

test('new users can register with phone number only without email', function () {
    $response = Volt::test('auth.register')
        ->set('name', 'Test User')
        ->set('phone', '60123456788')
        ->set('email', '')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('register');

    $response
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();

    // Verify user was created without email
    $user = User::where('phone', '60123456788')->first();
    expect($user)
        ->name->toBe('Test User')
        ->email->toBeNull()
        ->phone->toBe('60123456788')
        ->role->toBe('student');
});

test('phone number is required for registration', function () {
    $response = Volt::test('auth.register')
        ->set('name', 'Test User')
        ->set('phone', '')
        ->set('email', 'test@example.com')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('register');

    $response->assertHasErrors('phone');

    $this->assertGuest();
});

test('phone number must be unique', function () {
    User::factory()->create([
        'phone' => '60123456789',
    ]);

    $response = Volt::test('auth.register')
        ->set('name', 'Test User')
        ->set('phone', '60123456789')
        ->set('email', 'test@example.com')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('register');

    $response->assertHasErrors('phone');

    $this->assertGuest();
});

test('email must be unique when provided', function () {
    User::factory()->create([
        'phone' => '60123456789',
        'email' => 'test@example.com',
    ]);

    $response = Volt::test('auth.register')
        ->set('name', 'Test User')
        ->set('phone', '60123456788')
        ->set('email', 'test@example.com')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('register');

    $response->assertHasErrors('email');

    $this->assertGuest();
});

test('phone number must be valid format', function () {
    $response = Volt::test('auth.register')
        ->set('name', 'Test User')
        ->set('phone', 'invalid-phone')
        ->set('email', 'test@example.com')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('register');

    $response->assertHasErrors('phone');

    $this->assertGuest();
});
