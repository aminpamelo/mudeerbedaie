<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

it('shows a copyable storefront link for an active simple product', function () {
    $product = Product::factory()->create(['status' => 'active', 'type' => 'simple', 'show_on_storefront' => true]);

    Volt::test('admin.products.product-show', ['product' => $product])
        ->assertSee('Storefront Link')
        ->assertSee(route('storefront.product', $product->slug))
        ->assertSee('Copy Link')
        ->assertSee('Visible');
});

it('marks a hidden product as unlisted but still surfaces its direct link', function () {
    $product = Product::factory()->create(['status' => 'active', 'type' => 'simple', 'show_on_storefront' => false]);

    Volt::test('admin.products.product-show', ['product' => $product])
        ->assertSee(route('storefront.product', $product->slug))
        ->assertSee('Unlisted')
        ->assertSee('reachable by this direct link only');
});

it('explains why a non-simple product has no storefront link', function () {
    $product = Product::factory()->create(['status' => 'active', 'type' => 'variable', 'show_on_storefront' => true]);

    Volt::test('admin.products.product-show', ['product' => $product])
        ->assertSee('Storefront Link')
        ->assertSee('Not available on the storefront')
        ->assertDontSee('Copy Link');
});
