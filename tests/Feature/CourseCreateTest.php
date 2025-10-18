<?php

use App\Models\User;
use Livewire\Volt\Volt;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    actingAs($this->admin);
});

test('course name is required', function () {
    Volt::test('admin.course-create')
        ->set('name', '')
        ->set('description', 'Test description')
        ->call('nextStep')
        ->assertHasErrors(['name' => 'required']);
});

test('course description is required', function () {
    Volt::test('admin.course-create')
        ->set('name', 'Test Course')
        ->set('description', '')
        ->call('nextStep')
        ->assertHasErrors(['description' => 'required']);
});

test('course name must be at least 3 characters', function () {
    Volt::test('admin.course-create')
        ->set('name', 'ab')
        ->set('description', 'Test description')
        ->call('nextStep')
        ->assertHasErrors(['name' => 'min']);
});

test('course description cannot exceed 1000 characters', function () {
    Volt::test('admin.course-create')
        ->set('name', 'Test Course')
        ->set('description', str_repeat('a', 1001))
        ->call('nextStep')
        ->assertHasErrors(['description' => 'max']);
});

test('can proceed to step 2 with valid course info', function () {
    Volt::test('admin.course-create')
        ->set('name', 'Test Course')
        ->set('description', 'This is a test course description')
        ->call('nextStep')
        ->assertHasNoErrors()
        ->assertSet('step', 2);
});

test('can go back to step 1 from step 2', function () {
    Volt::test('admin.course-create')
        ->set('step', 2)
        ->call('previousStep')
        ->assertSet('step', 1);
});
