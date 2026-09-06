<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

it('shows the storefront visibility state on the products list', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    Product::factory()->create(['show_on_storefront' => true, 'name' => 'Public Product']);

    Volt::actingAs($admin)->test('admin.products.product-list')
        ->assertSee('Public Product')
        ->assertSee('Visible');
});

it('toggles a product off the storefront from the list', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $product = Product::factory()->create(['show_on_storefront' => true]);

    Volt::actingAs($admin)->test('admin.products.product-list')
        ->call('toggleStorefront', $product->id);

    expect($product->fresh()->show_on_storefront)->toBeFalse();
});

it('toggles a hidden product back onto the storefront', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $product = Product::factory()->create(['show_on_storefront' => false]);

    Volt::actingAs($admin)->test('admin.products.product-list')
        ->call('toggleStorefront', $product->id);

    expect($product->fresh()->show_on_storefront)->toBeTrue();
});
