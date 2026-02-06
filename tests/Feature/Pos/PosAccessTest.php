<?php

use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('admin can access POS page', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get(route('pos.index'))
        ->assertSuccessful();
});

test('sales user can access POS page', function () {
    $sales = User::factory()->create(['role' => 'sales']);

    $this->actingAs($sales)
        ->get(route('pos.index'))
        ->assertSuccessful();
});

test('student cannot access POS page', function () {
    $student = User::factory()->create(['role' => 'student']);

    $this->actingAs($student)
        ->get(route('pos.index'))
        ->assertForbidden();
});

test('teacher cannot access POS page', function () {
    $teacher = User::factory()->create(['role' => 'teacher']);

    $this->actingAs($teacher)
        ->get(route('pos.index'))
        ->assertForbidden();
});

test('guest is redirected from POS page', function () {
    $this->get(route('pos.index'))
        ->assertRedirect();
});

test('admin can access POS API endpoints', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->getJson(route('api.pos.products'))
        ->assertSuccessful();

    $this->actingAs($admin)
        ->getJson(route('api.pos.packages'))
        ->assertSuccessful();

    $this->actingAs($admin)
        ->getJson(route('api.pos.courses'))
        ->assertSuccessful();

    $this->actingAs($admin)
        ->getJson(route('api.pos.dashboard'))
        ->assertSuccessful();
});

test('sales user can access POS API endpoints', function () {
    $sales = User::factory()->create(['role' => 'sales']);

    $this->actingAs($sales)
        ->getJson(route('api.pos.products'))
        ->assertSuccessful();

    $this->actingAs($sales)
        ->getJson(route('api.pos.dashboard'))
        ->assertSuccessful();
});

test('guest cannot access POS API endpoints', function () {
    $this->getJson(route('api.pos.products'))
        ->assertUnauthorized();

    $this->getJson(route('api.pos.dashboard'))
        ->assertUnauthorized();
});
