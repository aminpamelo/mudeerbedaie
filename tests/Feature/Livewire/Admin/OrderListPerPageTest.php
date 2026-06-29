<?php

declare(strict_types=1);

use App\Models\ProductOrder;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
});

it('defaults to 30 rows per page', function () {
    $this->actingAs($this->admin);

    Volt::test('admin.orders.order-list')
        ->assertSet('perPage', 30);
});

it('paginates by the selected page size', function () {
    $this->actingAs($this->admin);

    ProductOrder::factory()->count(65)->create();

    $component = Volt::test('admin.orders.order-list');

    expect($component->instance()->getOrders()->perPage())->toBe(30);

    $component->set('perPage', 50);

    expect($component->instance()->getOrders()->perPage())->toBe(50);
});

it('exposes the expected page-size options', function () {
    $this->actingAs($this->admin);

    Volt::test('admin.orders.order-list')
        ->assertSet('perPageOptions', [30, 50, 100, 200, 300, 500]);
});

it('resets pagination when page size changes', function () {
    $this->actingAs($this->admin);

    ProductOrder::factory()->count(65)->create();

    Volt::test('admin.orders.order-list')
        ->set('paginators.page', 2)
        ->set('perPage', 100)
        ->assertSet('paginators.page', 1);
});

it('falls back to 30 when given an invalid page size', function () {
    $this->actingAs($this->admin);

    ProductOrder::factory()->count(5)->create();

    $component = Volt::test('admin.orders.order-list')
        ->set('perPage', 999);

    expect($component->instance()->getOrders()->perPage())->toBe(30);
});
