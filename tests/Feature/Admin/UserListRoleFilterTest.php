<?php

declare(strict_types=1);

use App\Models\User;
use Livewire\Volt\Volt;

it('offers the Fighter role as a filter option', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin);

    Volt::test('admin.user-list')
        ->assertSee('Fighter')
        ->assertSee('fighter');
});

it('filters the user list down to fighters', function () {
    $admin = User::factory()->create(['role' => 'admin', 'name' => 'Ada Admin']);
    User::factory()->create(['role' => 'fighter', 'name' => 'Fio Fighter']);
    User::factory()->create(['role' => 'student', 'name' => 'Sam Student']);
    $this->actingAs($admin);

    Volt::test('admin.user-list')
        ->set('roleFilter', 'fighter')
        ->assertSee('Fio Fighter')
        ->assertDontSee('Sam Student');
});
