<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

it('serves a dedicated student login page to guests', function () {
    $this->get('/student/login')
        ->assertOk()
        ->assertSee('Portal Pelajar')
        ->assertSee('Sambung pembelajaran anda.');
});

it('keeps the student login guest-only', function () {
    $this->actingAs(User::factory()->create(['role' => 'student']))
        ->get('/student/login')
        ->assertRedirect();
});

it('logs a student in via the dedicated page with email', function () {
    $user = User::factory()->create([
        'role' => 'student',
        'email' => 'pelajar@bedaie.test',
        'password' => bcrypt('password'),
    ]);

    Volt::test('auth.student-login')
        ->set('login', 'pelajar@bedaie.test')
        ->set('password', 'password')
        ->call('authenticate')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
});

it('rejects bad credentials on the dedicated page', function () {
    User::factory()->create([
        'role' => 'student',
        'email' => 'pelajar@bedaie.test',
        'password' => bcrypt('password'),
    ]);

    Volt::test('auth.student-login')
        ->set('login', 'pelajar@bedaie.test')
        ->set('password', 'salah')
        ->call('authenticate')
        ->assertHasErrors('login');

    $this->assertGuest();
});
