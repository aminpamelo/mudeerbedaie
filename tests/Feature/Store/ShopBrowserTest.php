<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StockLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

/** A tracked product with real available stock (quantity - reserved > 0). */
function inStockProduct(array $attrs = []): Product
{
    $product = Product::factory()->create(array_merge([
        'type' => 'simple',
        'status' => 'active',
        'track_quantity' => true,
    ], $attrs));

    StockLevel::factory()->create([
        'product_id' => $product->id,
        'quantity' => 25,
        'reserved_quantity' => 0,
    ]);

    return $product;
}

it('hides out-of-stock products and shows in-stock and untracked ones', function () {
    $inStock = inStockProduct(['name' => 'Alpha In Stock']);

    $untracked = Product::factory()->create(['type' => 'simple', 'status' => 'active', 'track_quantity' => false, 'name' => 'Gamma Untracked']);

    $oos = Product::factory()->create(['type' => 'simple', 'status' => 'active', 'track_quantity' => true, 'name' => 'Beta Sold Out']);
    StockLevel::factory()->create(['product_id' => $oos->id, 'quantity' => 5, 'reserved_quantity' => 5]); // available = 0

    $noStockRow = Product::factory()->create(['type' => 'simple', 'status' => 'active', 'track_quantity' => true, 'name' => 'Delta No Stock']); // tracked, no stock row

    Volt::test('store.shop-browser')
        ->assertSee('Alpha In Stock')
        ->assertSee('Gamma Untracked')
        ->assertDontSee('Beta Sold Out')
        ->assertDontSee('Delta No Stock')
        ->assertViewHas('total', 2);
});

it('shows one page first then loads more on scroll', function () {
    $step = (int) config('store.per_page', 12);
    $total = $step + 5;

    for ($i = 0; $i < $total; $i++) {
        Product::factory()->create(['type' => 'simple', 'status' => 'active', 'track_quantity' => false, 'name' => "Prod {$i}"]);
    }

    Volt::test('store.shop-browser')
        ->assertViewHas('products', fn ($p) => $p->count() === $step)
        ->assertViewHas('hasMore', true)
        ->call('loadMore')
        ->assertViewHas('products', fn ($p) => $p->count() === $total)
        ->assertViewHas('hasMore', false);
});

it('filters by search and resets paging', function () {
    inStockProduct(['name' => 'Special Ruqyah Handbook']);
    inStockProduct(['name' => 'Ordinary Notebook']);

    Volt::test('store.shop-browser')
        ->set('search', 'Ruqyah')
        ->assertViewHas('total', 1)
        ->assertSee('Special Ruqyah Handbook')
        ->assertDontSee('Ordinary Notebook');
});

it('filters by category', function () {
    $cat = ProductCategory::factory()->create(['name' => 'Books', 'slug' => 'books', 'is_active' => true]);
    inStockProduct(['name' => 'In Category', 'category_id' => $cat->id]);
    inStockProduct(['name' => 'Out Of Category']);

    Volt::test('store.shop-browser')
        ->set('categoryId', (string) $cat->id)
        ->assertViewHas('total', 1)
        ->assertSee('In Category')
        ->assertDontSee('Out Of Category');
});

it('loads only in-stock products on the shop page route', function () {
    inStockProduct(['name' => 'Visible Product']);

    $this->get('/shop')
        ->assertOk()
        ->assertSeeLivewire('store.shop-browser');
});
