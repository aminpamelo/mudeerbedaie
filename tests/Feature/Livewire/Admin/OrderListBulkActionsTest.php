<?php

declare(strict_types=1);

use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
});

it('hides the bulk action bar when nothing is selected', function () {
    $this->actingAs($this->admin);

    Volt::test('admin.orders.order-list')
        ->assertDontSee('Assign to Class');
});

it('shows the bulk action bar with the Assign to Class action when orders are selected', function () {
    $this->actingAs($this->admin);

    Volt::test('admin.orders.order-list')
        ->set('selectedOrderIds', [1])
        ->assertSee('order selected')
        ->assertSee('Assign to Class');
});

it('pluralizes the selected-order label for multiple orders', function () {
    $this->actingAs($this->admin);

    Volt::test('admin.orders.order-list')
        ->set('selectedOrderIds', [1, 2, 3])
        ->assertSee('orders selected')
        ->assertSee('Assign to Class');
});
