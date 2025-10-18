<?php

use App\Models\Student;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($this->admin);
});

test('student create page stores phone number with country code in correct format', function () {
    Volt::test('admin.student-create')
        ->set('name', 'Test Student')
        ->set('email', 'teststudent'.time().'@example.com')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->set('country_code', '+60')
        ->set('phone', '165756060')
        ->set('status', 'active')
        ->call('create')
        ->assertHasNoErrors();

    $student = Student::latest()->first();

    expect($student->phone)->toBe('60165756060')
        ->and($student->phone)->not->toContain('+')
        ->and($student->phone)->not->toContain(' ');
});

test('student create page stores phone number without plus sign', function () {
    Volt::test('admin.student-create')
        ->set('name', 'Test Student 2')
        ->set('email', 'teststudent2'.time().'@example.com')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->set('country_code', '+65')
        ->set('phone', '87654321')
        ->set('status', 'active')
        ->call('create')
        ->assertHasNoErrors();

    $student = Student::latest()->first();

    expect($student->phone)->toBe('6587654321');
});

test('student create page validates phone number must be numeric', function () {
    Volt::test('admin.student-create')
        ->set('name', 'Test Student')
        ->set('email', 'teststudent3'.time().'@example.com')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->set('country_code', '+60')
        ->set('phone', '165-756-060')
        ->set('status', 'active')
        ->call('create')
        ->assertHasErrors(['phone']);
});

test('student edit page correctly parses existing phone number', function () {
    $user = User::factory()->create(['role' => 'student']);
    $student = Student::factory()->create([
        'user_id' => $user->id,
        'phone' => '60123456789',
    ]);

    $component = Volt::test('admin.student-edit', ['student' => $student]);

    expect($component->get('country_code'))->toBe('+60')
        ->and($component->get('phone'))->toBe('123456789');
});

test('student edit page updates phone number in correct format', function () {
    $user = User::factory()->create(['role' => 'student']);
    $student = Student::factory()->create([
        'user_id' => $user->id,
        'phone' => '60123456789',
    ]);

    Volt::test('admin.student-edit', ['student' => $student])
        ->set('name', $user->name)
        ->set('email', $user->email)
        ->set('country_code', '+60')
        ->set('phone', '987654321')
        ->set('status', 'active')
        ->call('update')
        ->assertHasNoErrors();

    $student->refresh();

    expect($student->phone)->toBe('60987654321')
        ->and($student->phone)->not->toContain('+')
        ->and($student->phone)->not->toContain(' ');
});

test('student edit page handles different country codes correctly', function () {
    $user = User::factory()->create(['role' => 'student']);
    $student = Student::factory()->create([
        'user_id' => $user->id,
        'phone' => '6587654321',
    ]);

    $component = Volt::test('admin.student-edit', ['student' => $student]);

    expect($component->get('country_code'))->toBe('+65')
        ->and($component->get('phone'))->toBe('87654321');

    $component
        ->set('name', $user->name)
        ->set('email', $user->email)
        ->set('country_code', '+62')
        ->set('phone', '812345678')
        ->set('status', 'active')
        ->call('update')
        ->assertHasNoErrors();

    $student->refresh();

    expect($student->phone)->toBe('62812345678');
});

test('student edit page handles three digit country codes', function () {
    $user = User::factory()->create(['role' => 'student']);
    $student = Student::factory()->create([
        'user_id' => $user->id,
        'phone' => '85512345678',
    ]);

    $component = Volt::test('admin.student-edit', ['student' => $student]);

    expect($component->get('country_code'))->toBe('+855')
        ->and($component->get('phone'))->toBe('12345678');
});

test('student can be created without phone number', function () {
    Volt::test('admin.student-create')
        ->set('name', 'Test Student No Phone')
        ->set('email', 'testnophone'.time().'@example.com')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->set('status', 'active')
        ->call('create')
        ->assertHasNoErrors();

    $student = Student::latest()->first();

    expect($student->phone)->toBeNull();
});

test('student phone can be updated to null', function () {
    $user = User::factory()->create(['role' => 'student']);
    $student = Student::factory()->create([
        'user_id' => $user->id,
        'phone' => '60123456789',
    ]);

    Volt::test('admin.student-edit', ['student' => $student])
        ->set('name', $user->name)
        ->set('email', $user->email)
        ->set('phone', '')
        ->set('status', 'active')
        ->call('update')
        ->assertHasNoErrors();

    $student->refresh();

    expect($student->phone)->toBeNull();
});
