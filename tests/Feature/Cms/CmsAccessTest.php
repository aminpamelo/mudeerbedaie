<?php

use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('admin can access CMS page', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get(route('cms.dashboard'))
        ->assertSuccessful();
});

test('employee can access CMS page', function () {
    $employee = User::factory()->create(['role' => 'employee']);

    $this->actingAs($employee)
        ->get(route('cms.dashboard'))
        ->assertSuccessful();
});

test('student cannot access CMS page', function () {
    $student = User::factory()->create(['role' => 'student']);

    $this->actingAs($student)
        ->get(route('cms.dashboard'))
        ->assertForbidden();
});

test('teacher cannot access CMS page', function () {
    $teacher = User::factory()->create(['role' => 'teacher']);

    $this->actingAs($teacher)
        ->get(route('cms.dashboard'))
        ->assertForbidden();
});

test('guest is redirected from CMS page', function () {
    $this->get(route('cms.dashboard'))
        ->assertRedirect();
});
