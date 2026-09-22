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
        ->assertSee('Sambung pembelajaran anda.')
        ->assertSee('Nombor telefon');
});

it('keeps the student login guest-only', function () {
    $this->actingAs(User::factory()->create(['role' => 'student']))
        ->get('/student/login')
        ->assertRedirect();
});

it('logs a student in with their exact registered phone', function () {
    $user = User::factory()->create([
        'role' => 'student',
        'phone' => '+600123456789',
    ]);

    Volt::test('auth.student-login')
        ->set('phone', '+600123456789')
        ->call('authenticate')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
});

it('normalises common phone variants to the stored form', function (string $typed) {
    $user = User::factory()->create([
        'role' => 'student',
        'phone' => '+600123456789',
    ]);

    Volt::test('auth.student-login')
        ->set('phone', $typed)
        ->call('authenticate')
        ->assertHasNoErrors();

    $this->assertAuthenticatedAs($user);
})->with([
    'local with leading zero' => '0123456789',
    'spaced local' => '012-345 6789',
    'full with country code' => '600123456789',
    'plus prefixed' => '+600123456789',
    'bare 60 prefix' => '60123456789',
]);

it('rejects an unknown phone number', function () {
    User::factory()->create([
        'role' => 'student',
        'phone' => '+600123456789',
    ]);

    Volt::test('auth.student-login')
        ->set('phone', '+600999999999')
        ->call('authenticate')
        ->assertHasErrors('phone');

    $this->assertGuest();
});

it('never signs in a non-student via phone-only (privileged accounts stay password-gated)', function () {
    // An admin who happens to share the phone shape must NOT be reachable here.
    User::factory()->create([
        'role' => 'admin',
        'phone' => '+600123456789',
    ]);

    Volt::test('auth.student-login')
        ->set('phone', '+600123456789')
        ->call('authenticate')
        ->assertHasErrors('phone');

    $this->assertGuest();
});

it('requires a phone number', function () {
    Volt::test('auth.student-login')
        ->set('phone', '')
        ->call('authenticate')
        ->assertHasErrors('phone');

    $this->assertGuest();
});
